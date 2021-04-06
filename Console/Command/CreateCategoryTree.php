<?php

namespace Jason\QuickCreateCli\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Class GreetingCommand
 */
class CreateCategoryTree extends Command {
    /**
     * Name argument
     */
    const NAME_ARGUMENT = 'name';

    /**
     * Parent argument
     */
    const PARENTID_OPTION = 'parent-id';

    /**
     * Delimiter in category path.
     */
    const DELIMITER_CATEGORY = '/';

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * @var CategoryFactory
     */
    private $categoryFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    protected $categoryColFactory;

    /**
     * @var \Magento\Catalog\Api\CategoryRepositoryInterface
     */
    private $categoryRepository;

    /**
     * Categories id to object cache.
     *
     * @var array
     */
    protected $categoriesCache = [];

    /**
     * 
     */
    protected $currentCategoryID;

    /**
     * @param \Magento\Catalog\Model\CategoryFactory $categoryFactory
     * @param \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryColFactory
     */
    public function __construct(
        \Magento\Catalog\Model\CategoryFactory $categoryFactory,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryColFactory,
        \Magento\Store\Model\StoreManagerInterface $StoreManagerInterface,
        \Magento\Catalog\Api\CategoryRepositoryInterface $categoryRepository
    ) {
        $this->categoryColFactory = $categoryColFactory;
        $this->categoryFactory = $categoryFactory;
        $this->storeManagerInterface = $StoreManagerInterface;
        $this->categoryRepository = $categoryRepository;
        $this->initCategories();
        $this->currentCategoryID = $this->getRootCategoryId();
        parent::__construct();
    }

    /**
     * Initialize categories
     *
     * @return $this
     */
    protected function initCategories()
    {
        if (empty($this->categories)) {
            $collection = $this->categoryColFactory->create();
            $collection->addAttributeToSelect('name')
                ->addAttributeToSelect('url_key')
                ->addAttributeToSelect('url_path');
            $collection->setStoreId(\Magento\Store\Model\Store::DEFAULT_STORE_ID);
            /* @var $collection \Magento\Catalog\Model\ResourceModel\Category\Collection */
            foreach ($collection as $category) {
                $structure = explode(self::DELIMITER_CATEGORY, $category->getPath());
                $pathSize = count($structure);

                $this->categoriesCache[$category->getId()] = $category;
                if ($pathSize > 1) {
                    $path = [];
                    for ($i = 1; $i < $pathSize; $i++) {
                        $name = $collection->getItemById((int)$structure[$i])->getName();
                        $path[] = $this->quoteDelimiter($name);
                    }
                    /** @var string $index */
                    $index = $this->standardizeString(
                        implode(self::DELIMITER_CATEGORY, $path)
                    );
                    $this->categories[$index] = $category->getId();
                }
            }
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        $this->setName('quickcreate:categorytree')
            ->setDescription('Add a category tree');

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $inputChoice = "";
        
        $helper = $this->getHelper('question');
        
        $actionQuestion = new ChoiceQuestion(
            'Choose an option from the below: ',
            // choices can also be PHP objects that implement __toString() method
            [
                'Create sub-categories', 
                'Select a sub-category', 
                'Go up a category level',
                'Exit'
            ]
        );

        while($inputChoice != "Exit"){

            $output->writeln('=======================');
            $output->writeln('<info>You are currently in: ' . $this->getCategoryNameById($this->currentCategoryID) . ' [' . $this->currentCategoryID . ']</info>');
            $output->writeln('=======================');
            $output->writeln('');
            $inputChoice = $helper->ask($input, $output, $actionQuestion);

            //$output->writeln('<info>input choice: ' . $inputChoice . '</info>');

            switch($inputChoice) {
                case "Create sub-categories":
                    $output->writeln('=======================');
                    $output->writeln('<info>Mass create sub-categories.</info>');
                    $output->writeln('=======================');
                    $output->writeln('');
                    $createCategoriesQuestion = new Question('Enter a comma separated list of category names: ');
                    $categoriesToBeCreated = $helper->ask($input, $output, $createCategoriesQuestion);
                    $this->createMultipleCategories($output, $categoriesToBeCreated, $this->currentCategoryID);
                    $output->writeln('<info>' . $categoriesToBeCreated . '</info>');
                    break;
                case "Select a sub-category":
                    $this->chooseSubCategory($input, $output, $this->currentCategoryID);
                    break;
                case "Go up a category level":
                    if($this->getParentCategoryId($this->currentCategoryID)) {
                        $this->currentCategoryID = $this->getParentCategoryId($this->currentCategoryID);
                    } else {
                        $output->writeln('<error>No parent category found.</error>');
                    }
                    break;
                case "Exit":
                    $output->writeln('<info>Exiting</info>');
                    break;
            }
        }

    }

    protected function createMultipleCategories(OutputInterface $output, $categoryString, $parentId) {

        $categoryArray = explode(',', $categoryString);

        $progressBarSection = $output->section();
        $feedbackSection = $output->section();

        $progressBar = new ProgressBar($progressBarSection, count($categoryArray));
        $progressBar->start();

        foreach($categoryArray as $newCategory){
            $newCategory = trim($newCategory);
            if(strlen($newCategory) > 0){
                $this->createCategory($feedbackSection, $newCategory, $parentId);
            }
            $progressBar->advance();
        }
        $progressBar->advance();
    }

    protected function createCategory(OutputInterface $output, $name, $parentId = 64, $isActive = true, $isAnchor = true, $isIncludeInMenu = true) {
        $category = $this->categoryFactory->create();
        if (!($parentCategory = $this->getCategoryById($parentId))) {
            $parentCategory = $this->categoryFactory->create()->load($parentId);
        }
        try {
            
            $category->setName($name);
            $category->setParentId($parentId); // 1: root category.
            $category->setIsActive($isActive);
            $category->setIncludeInMenu($isIncludeInMenu);
            $category->setIsAnchor($isAnchor);
            $category->setPath($parentCategory->getPath());
            //$category->setCustomAttributes();
            $category->save();
            $output->writeln('<info>' . $name . ' has been created! The category ID is ' . $category->getId() . '</info>');
        } catch(\Exception $e) {
            $output->writeln('<error>no luck ' . $e . '!</error>');
        }
    }

    protected function chooseSubCategory(InputInterface $input, OutputInterface $output, $id, $storeId = null) {
        $helper = $this->getHelper('question');

        $subCategoryList = $this->categoryRepository->get($id, $storeId)->getChildrenCategories();
        
        foreach($subCategoryList as $subCategory){
            $subCategoryListArray[$subCategory->getId()] = $subCategory->getName();
        }

        $actionQuestion = new ChoiceQuestion(
            'Choose a category from below: ', $subCategoryListArray
        );

        $inputChoice = $helper->ask($input, $output, $actionQuestion);

        //$output->writeln('<info>Choosen category is ' . array_search($inputChoice, $subCategoryListArray) . '</info>');

        $this->currentCategoryID = array_search($inputChoice, $subCategoryListArray);
    }

    protected function getRootCategoryId() {
        return $this->storeManagerInterface->getStore()->getRootCategoryId();
    }

    protected function getCategoryNameById($id, $storeId = null) {
        $categoryInstance = $this->categoryRepository->get($id, $storeId);

        return $categoryInstance->getName();
    }

    protected function getParentCategoryId($id, $storeId = null) {
        $categoryInstance = $this->categoryRepository->get($id, $storeId);

        if ($categoryInstance->getParentId()) {
            return $categoryInstance->getParentId();
        } else {
            return false;
        }
    }


    /**
     * Get category by Id
     *
     * @param int $categoryId
     *
     * @return \Magento\Catalog\Model\Category|null
     */
    public function getCategoryById($categoryId) {
        return $this->categoriesCache[$categoryId] ?? null;
    }

    /**
     * Standardize a string.
     * For now it performs only a lowercase action, this method is here to include more complex checks in the future
     * if needed.
     *
     * @param string $string
     * @return string
     */
    private function standardizeString($string) {
        return mb_strtolower($string);
    }

    /**
     * Quoting delimiter character in string.
     *
     * @param string $string
     * @return string
     */
    private function quoteDelimiter($string) {
        return str_replace(self::DELIMITER_CATEGORY, '\\' . self::DELIMITER_CATEGORY, $string);
    }

    /**
     * Remove quoting delimiter in string.
     *
     * @param string $string
     * @return string
     */
    private function unquoteDelimiter($string) {
        return str_replace('\\' . self::DELIMITER_CATEGORY, self::DELIMITER_CATEGORY, $string);
    }

}