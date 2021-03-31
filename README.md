# Quick Create CLI
Magento CLI `bin/magento` tools to quickly create objects inside of Magento directly from the command line.

This is not a replacement for import/export type features, but instead intended to speed up creating custom content for demoing Magento.

## Installation
Composer instructions to go here.....

## Usage
Once installed you can access the commands through the Magento CLI using `bin/magento`.

### quickcreate:category
Command: `bin/magento quickcreate:category {NAME} (OPTIONAL -p {PARENT_ID})`

Quickly create a new category, optionally within a specific parent ID.

### quickcreate:categorytree
Command: `bin/magento quickcreate:categorytree`

Using this option brings up an interactive menu, where you can:
* Create multiple sub-categories quickly using a comma separated list
* Go into a sub-category
* Go up one category level

## To do

* Test thoroughly
* Add more quick create content types
* Add more options to the category functionality

## Boring stuff

This code is provided "as is" and not intended for production use. It is created to improve my own personal workflow and is not endoresed or supported by myself, my employer or anyone else associated with the code. If you use this, you do so at your own risk, and with full knowledge of what you're doing.