# SimpleGallery

An image gallery script in one single [PHP](https://en.wikipedia.org/wiki/PHP) file.

It makes use of

* [Bootstrap](http://getbootstrap.com/) with [Darkly](http://bootswatch.com/) theme
* [jQuery](http://jquery.com/)

Have a look at the [wiki](https://github.com/mkloubert/SimpleGallery/wiki) if you need more detailed documentation.

## Requirements

* PHP 5.2 or later

## Installation

Copy or link the [index.php](https://github.com/mkloubert/SimpleGallery/blob/master/index.php) file to the directory where your images are included.

Thats all!

## Customization

### Config

By default you can copy or link the following files to the directory where the gallery script is included (the "original meaning" can be ignored, but it is useful because of the order the files are loaded):

 # | Name | Original meaning
------------ | ------------ | -------------
 1 | `sgConfig.json` | Global config
 2 | `sgUsers.json` | List of users
 3 | `sgCustom.json` | Other custom 

All existing values are overwritten by the data of the current file.

If a file of the list was not found, it is ignored.

### Includes

By default you can copy or link a file called `sgInclude.php` to the directory where the gallery script is stored / linked.

These files are included BEFORE app class is initialized and AFTER config files were loaded.

If the file was not found, it is ignored.

### Scripts

By default you can copy or link a file called `sgScript.js` to the directory where the gallery script is stored / linked.

The content of the file is included at the end of the output document AFTER custom style sheet was included.

If the file was not found, it is ignored.

### Styles

By default you can copy or link a file called `sgStyle.css` to the directory where the gallery script is stored / linked.

The content of the file is included at the end of the output document BEFORE custom script was included.

If the file was not found, it is ignored.
