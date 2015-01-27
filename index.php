<?php

/*
 
 Image gallery script in one PHP file.
 Copyright (C) 2015  Marcel Joachim Kloubert <marcel.kloubert@gmx.net>

 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License as
 published by the Free Software Foundation, either version 3 of the
 License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
 
*/


error_reporting(E_ALL);

define('SG_INDEX', 'index', false);


/********** CONFIG **********/

$GLOBALS['sg'] = null;

$GLOBALS['conf']          = array();
$GLOBALS['conf']['class'] = 'SimpleGallery';
$GLOBALS['conf']['file']  = './sgConfig.json';

// custom data
$GLOBALS['conf']['custom']            = array();
$GLOBALS['conf']['custom']['dir']     = './';
$GLOBALS['conf']['custom']['include'] = './sgInclude.php';
$GLOBALS['conf']['custom']['script']  = './sgScript.js';
$GLOBALS['conf']['custom']['style']   = './sgStyle.css';

// files
$GLOBALS['conf']['files']              = array();
$GLOBALS['conf']['files']['supported'] = array(
    'image/gif'  => array('gif'),
	'image/jpeg' => array('jpeg', 'jpg'),
	'image/png'  => array('png'),
);

// output
$GLOBALS['conf']['output']             = array();
$GLOBALS['conf']['output']['compress'] = true;

// thumbs
$GLOBALS['conf']['thumbs']               = array();
$GLOBALS['conf']['thumbs']['cache']      = null;
$GLOBALS['conf']['thumbs']['max_height'] = 200;
$GLOBALS['conf']['thumbs']['max_width']  = 200;
$GLOBALS['conf']['thumbs']['quality']    = 67;

/********** CONFIG **********/


/********** CONSTANTS **********/

define('SG_SELF'             , $_SERVER['SCRIPT_NAME'], false);
define('SG_VERSION'          , '1.0'                  , false);

/********** CONSTANTS **********/


/********** CLASS: SimpleGallery **********/

/**
 * The application class.
 * 
 * @author Marcel Joachim Kloubert <marcel.kloubert@gmx.net>
 */
class SimpleGallery {
	/**
	 * Stores the config data.
	 * 
	 * @var stdClass
	 */
	protected $_config;
	
	
	/**
	 * Initializes a new instance of that class.
	 */
	public function __construct() {
		$this->loadConfig();
	}
	
	
	/**
	 * Gets a property.
	 * 
	 * @param string $name The name of the property.
	 * 
	 * @throws Exception Property not found.
	 * 
	 * @return mixed The value of the property.
	 */
	public function __get($name) {
		switch ($name) {
			case 'Config':
				return $this->_config; 
		}
		
		throw new Exception(sprintf("'%s' property not found!", $name));
	}
	
	
	/**
	 * Converts an array to an object deep.
	 * 
	 * @param mixed $arr The array/value to convert.
	 * 
	 * @return mixed|stdClass If input value is an array, this is the
	 *                        converted value; otherwise the input value itself.
	 */
	public function arrayToObject($arr) {
		if (!is_array($arr)) {
			return $arr;
		}
		 
		$result = new stdClass();
		
		foreach ($arr as $name => $value) {
			$name = trim(strtolower($name));
			if (!empty($name)) {
				$result->$name = $this->arrayToObject($value);
			}
		}
		
		return $result;
	}
	
	/**
	 * Checks if a strings ends with a specific expression.
	 * 
	 * @param string $str The string where the expression should be
	 *                    searched in.
	 * @param string $searchFor The string to search for.
	 * 
	 * @return boolean Ends with expression or not.
	 */
	public final function endsWith($str, $searchFor) {
		return ($searchFor === "") ||
		       (false !== strpos($str, $searchFor, strlen($str) - strlen($searchFor)));
	}
	
	/**
	 * Returns the current directory.
	 * 
	 * @return string The current directory.
	 */
	public function getCurrentDirectory() {
		return realpath($this->Config->custom->dir) .
		       DIRECTORY_SEPARATOR;
	}
	
	/**
	 * Gets the default MIME type.
	 * 
	 * @return string The default MIME type.
	 */
	public function getDefaultMimeType() {
		return 'application/octet-stream';
	}
	
	/**
	 * Gets the EXIF data from a file.
	 * 
	 * @param string $file The path to the file.
	 * 
	 * @return array The EXIF data or FALSE if an error occured.
	 *               NULL indicates that the operation is NOT suppoted.
	 */
	public function getExif($file) {
		switch($this->getMimeType($file)) {
			case 'image/jpeg':
			case 'image/tiff':
				return @exif_read_data($file);
		}
		
		// not supported
		return null;
	}
	
	/**
	 * Gets the file name that was submitted with the current request.
	 * 
	 * @return boolean|string The file name or FALSE if no filename was
	 *                        submitted; NULL indicates an empty value
	 */
	public function getFileName() {
		$result = false;
		if (isset($_REQUEST['f'])) {
			$result = trim($_REQUEST['f']);
			if ('' == $result) {
				$result = null;
			}
		}
		
		return $result;
	}
	
	/**
	 * Returns a sorted list of files from the current folder.
	 * 
	 * @return array The list of files.
	 */
	public function getFiles() {
		return scandir($this->getCurrentDirectory());
	}
	
	/**
	 * Returns the mime type by filename / path.
	 * 
	 * @param string $file The file name / path.
	 * 
	 * @return string The mime type.
	 */
	public final function getMimeType($file) {
		return $this->getMimeTypeByExtension(pathinfo($file,
				                                      PATHINFO_EXTENSION));
	}
	
	/**
	 * Returns the MIME type by file extensions.
	 * 
	 * @param string $ext The file extensions.
	 * 
	 * @return string The MIME type.
	 */
	public function getMimeTypeByExtension($ext) {
		$ext = trim(strtolower($ext));
		
		foreach ($this->getSupportedFiles() as $mime => $extensions) {
			foreach ($extensions as $e) {
				if (trim(strtolower($e)) == $ext) {
					return trim(strtolower($mime));
				}
			}
		}

		return $this->getDefaultMimeType();
	}
	
	public function getMode() {
		$result = null;
		if (isset($_REQUEST['m'])) {
			$result = trim($_REQUEST['m']);
		}
		
		return $result;
	}
	
	/**
	 * Returns the list of supported file extensions.
	 * 
	 * @return array The list of supported file extensions.
	 */
	public function getSupportedFileExtensions() {
		$result = array();
		foreach ($this->getSupportedFiles() as $mime => $extensions) {
			foreach ($extensions as $ext) {
				$result[] = $ext;
			}
		}
		
		return array_unique($result);
	}
	
	/**
	 * Returns the list of supported MIME types and file extensions.
	 */
	public function getSupportedFiles() {
		return $this->Config->files->supported;
	}
	
	/**
	 * Gets the cache directory for the thumbs.
	 * 
	 * @return string The full path to the cache directory for the thumbs.
	 *                NULL indicates that the directory is NOT available.
	 */
	public function getThumbCacheDir() {
		$result = null;
		
		$dir = trim($this->Config->thumbs->cache);
		if ('' != $dir) {
			if (!file_exists($dir)) {
				@mkdir($dir);
			}
			
			$result = realpath($dir);
			if (false !== $result) {
				$result .= DIRECTORY_SEPARATOR;
			}
		}
		
		return $result;
	}
	
	/**
	 * Gets the thumb name for a file.
	 * 
	 * @param string $filename The original filename.
	 * 
	 * @return string The name of the file for its thumb version.
	 */
	public function getThumbFilename($filename) {
		return basename($filename) . '.jpg';
	}
	
	/**
	 * Gets a thumb image of a file.
	 * 
	 * @param string $filename The original filename.
	 * 
	 * @return resource The thumb of the file or NULL if not available.
	 *                  FALSE indicates that an error has been occured.
	 */
	public function getThumbFromCache($filename) {
		$dir = $this->getThumbCacheDir();
		if (empty($dir)) {
			return null;
		}
		
		$file = realpath($dir . $this->getThumbFilename($filename));
		if (false === $file) {
			return null;
		}
		
		return imagecreatefromstring(file_get_contents($file));
	}
	
	/**
	 * Handles the (current) mode that is provides by
	 * SimpleGallery::getMode() method.
	 * 
	 * @return boolean Mode was handled or not.
	 */
	public final function handleCurrentMode() {
		return $this->handleMode($this->getMode());
	}

	/**
	 * Handles a mode.
	 * 
	 * @param string $mode The ID of the mode.
	 * 
	 * @return boolean Mode was handled or not.
	 */
	public function handleMode($mode) {
		switch (trim(strtolower($mode))) {
			case '1':
				// get thumb of
				{
					$imgFile = realpath($this->getCurrentDirectory() . $this->getFileName());
					if (false !== $imgFile) {
						if ($this->isImageFile($imgFile)) {
							$isCached = true;
							
							$img = $this->getThumbFromCache($imgFile);
							if (!is_resource($img)) {
								$isCached = false;
								
							    // get tresized image from file
							    $img = $this->resizeImageFromFile($imgFile,
								  							      $this->Config->thumbs->max_width,
									                              $this->Config->thumbs->max_height);
							  
							    // correct orentation if needed
							    $exif = $this->getExif($imgFile);
							    if (is_array($exif)) {
							  	    if (!empty($exif['Orientation'])) {
							  		    $rotatedImage = null;
							  		    switch ($exif['Orientation']) {
							  			    case 3:
							  				    $rotatedImage = imagerotate($img, 180, 0);
							  				    break;
							  					
							  			    case 6:
							  				    $rotatedImage = imagerotate($img, -90, 0);
							  				    break;
							  					
							  			    case 8:
							  				    $rotatedImage = imagerotate($img, 90, 0);
							  			  	    break;
							  		    }
							  			
							  		    if (is_resource($rotatedImage)) {
							  		  	    imagedestroy($img);
							  
							  		  	    $img = $rotatedImage;
							  		    }
							        }
							    }    
							  
							    $this->saveThumbToCache($img, $imgFile);
							}
							
							// output and free resources
							if ($isCached) {
								$this->sendThumbHeaders('image/png');
								imagepng($img);
							}
							else {
								$this->outputThumb($img);
							}
							
							imagedestroy($img);
						}
					}
				}
				return true;  // mark as 'handled'
				
			case '2':
				// send as download
				{
					$imgFile = realpath($this->getCurrentDirectory() . $this->getFileName());
					if (false !== $imgFile) {
						if ($this->isImageFile($imgFile)) {
							header('Content-type: ' . $this->getMimeType($imgFile));
							header(sprintf('Content-disposition: attachment; filename="%s"',
							               basename($imgFile)));
							
							readfile($imgFile);
						}
					}
				}
				break;
		}
		
		return false;  // NOT handled
	}
	
	/**
	 * Checks if a filename represents a valid image file. 
	 * 
	 * @param string $filename The filename to check.
	 * 
	 * @return boolean Seems to be a valid image file or not.
	 */
	public function isImageFile($filename) {
		$filename = trim(strtolower($filename));
		
		switch ($filename) {
			case '.':
			case '..':
				return false;
		}
		
		foreach ($this->getSupportedFileExtensions() as $ext) {
			if ($this->endsWith($filename, $ext)) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Loads the configuation.
	 */
	protected function loadConfig() {
		$this->_config = $this->arrayToObject($GLOBALS['conf']);
	}
	
	/**
	 * Outputs an image as thumb.
	 * 
	 * @param resource $img The image to output.
	 * @param string $file The target file (if defined).
	 *                     The thumb is send to output buffer by default.
	 * 
	 * @return boolean Operation was successful or not.
	 *                 NULL indicates that image is no resource (image).
	 */
	public function outputThumb($img, $file = null) {
		if (!is_resource($img)) {
			return null;
		}
		
		if (is_null($file)) {
			$this->sendThumbHeaders();
		}

		return imagejpeg($img,
				         $file,
				         $this->Config->thumbs->quality);
	}
	
	/**
	 * Reload the configuration data.
	 */
	public final function reloadConfig() {
		$this->loadConfig();
	}
	
	/**
	 * Resizes an image.
	 * 
	 * @param resource $img The original image.
	 * @param int $maxWidth The maximum width.
	 * @param int $maxHeight The maximum height.
	 * 
	 * @return boolean|resource The resized image or FALSE if an error
	 *                          error occured.
	 */
	public function resizeImage($img, $maxWidth, $maxHeight = null) {
		if (!is_resource($img)) {
			return false;
		}
		
		if (is_null($maxHeight)) {
			$maxHeight = $maxWidth;
		}
		
		$orgWidth  = imagesx($img);
		$orgHeight = imagesy($img);
		
		$width  = $orgWidth;
		$height = $orgHeight;
			
		// taller
		if ($height > $maxHeight) {
			$width = ($maxHeight / $height) * $width;
			$height = $maxHeight;
		}
			
		// wider
		if ($width > $maxWidth) {
			$height = ($maxWidth / $width) * $height;
			$width = $maxWidth;
		}
		
		$resizedImage = imagecreatetruecolor($width, $height);
		imagecopyresampled($resizedImage, $img, 0, 0, 0, 0,
		                   $width, $height, $orgWidth, $orgHeight);
		
		return $resizedImage;
	}
	
	/**
	 * Returns a resized version of a image file.
	 * 
	 * @param string $filename The path to the image file.
	 * @param int $maxWidth The maximum width.
	 * @param int $maxHeight The maximum height.
	 * 
	 * @return mixed The resource of the resized image;
	 *               NULL indicates that the file is NO valid image file;
	 *               FALSE indicates that an error occured
	 */
	public function resizeImageFromFile($filename, $maxWidth, $maxHeight = null) {
		// try load original image
		$orgImg = null;
		switch ($this->getMimeType($filename)) {
			case 'image/gif':
				$orgImg = @imagecreatefromgif($filename);
				break;
		
			case 'image/jpeg':
				$orgImg = @imagecreatefromjpeg($filename);
				break;
		
			case 'image/png':
				$orgImg = @imagecreatefrompng($filename);
				break;
		}
		
		if (false === $orgImg) {
			return false;
		}
		
		if (is_resource($orgImg)) {
			// original image loaded
			// so start resizing
			
			$resizedImage = $this->resizeImage($orgImg,
					                           $maxWidth, $maxHeight);

			imagedestroy($orgImg);
			
			return $resizedImage;
		}
		
		return null;
	}
	
	/**
	 * Saves a thumb image to cache.
	 * 
	 * @param resource $img The image to save.
	 * @param string $filename The name of the original file.
	 * 
	 * @return boolean Operation was successful or not.
	 */
	public function saveThumbToCache($img, $filename) {
		$dir = $this->getThumbCacheDir();
		if (empty($dir)) {
			return false;
		}
	
		$filename = $dir . $this->getThumbFilename($filename);
	
		return $this->outputThumb($img, $filename);
	}
	
	/**
	 * Sends the HTTP response headers for thumb output.
	 * 
	 * @param string $mime The custom content type to use.
	 */
	public function sendThumbHeaders($mime = 'image/jpeg') {
		header('Content-type: ' . trim(strtolower($mime)));
		header('Max-age: 604800');
	}
	
	/**
	 * Logic to sort strings case insensitive (ascending).
	 * 
	 * @param string $x The left string.
	 * @param string $y The right string.
	 * 
	 * @return number The compare value.
	 */
	public final function sortStringsCaseInsensitive($x, $y) {
		return strcmp(trim(strtolower($x)),
				      trim(strtolower($y)));
	}
	
	/**
	 * Logic to sort strings case insensitive (descending).
	 *
	 * @param string $x The left string.
	 * @param string $y The right string.
	 *
	 * @return number The compare value.
	 */
	public final function sortStringsCaseInsensitiveDesc($x, $y) {
		return -1 * $this->sortStringsCaseInsensitive($x, $y);
	}
}

/********** CLASS: SimpleGallery **********/


// create instance of application class
// that should be an instance of 'SimpleGallery'
// or of a subclass of it
$GLOBALS['sg'] = $sg = new $GLOBALS['conf']['class']();


// load custom config (if available)
$customConfigFile = realpath($GLOBALS['conf']['file']);
if (false !== $customConfigFile) {
	$jsonConf = trim(file_get_contents($customConfigFile));
	
	$customConf = array();
	if ('' != $jsonConf) {
		$customConf = json_decode($jsonConf, true);
	}
	
	if (false === $customConf) {
		die('Could not load config file!');
	}

	$GLOBALS['conf'] = array_replace($GLOBALS['conf'],
			                         $customConf);

	$sg->reloadConfig();
}


if (!$sg->handleCurrentMode()) :

// do the default: output gallery page

if ($sg->Config->output->compress) {
	ob_start('ob_gzhandler');
}

?>
<!-- 

  simpleGallery v<?php echo SG_VERSION; ?> 
  Copyight (C)  2015 Marcel Joachim Kloubert <marcel.kloubert@gmx.net>
  
  https://github.com/mkloubert/simpleGallery

  Licence: AGPL v3.0 (http://www.gnu.org/licenses/)
  
-->
<html>
  <head>
    <title>simpleGallery</title>
    
    <script type="text/javascript">

	    /**
	     * Handles that string as formatted string.
	     *
	     * @method format
	     *
	     * @param {mixed} [...args] The values for the placeholders in that string.
	     *
	     * @return {String} The formatted string.
	     */
	    String.prototype.format = function() {
	        return this.formatArray(arguments);
	    };
	    
        /**
         * Handles that string as formatted string.
         *
         * @method formatArray
         *
         * @param {Array} [args] The values for the placeholders in that string.
         *
         * @return {String} The formatted string.
         */
        String.prototype.formatArray = function(args) {
            if (!args) {
                args = [];
            }
            
            return this.replace(/{(\d+)}/g, function(match, number) {
                return (typeof args[number] != 'undefined') ? args[number]
                                                            : match;
            });
        };

    </script>
    
    <!-- wdContextMenu -->
    <!-- http://www.web-delicious.com/jquery-plugins-demo/wdContextMenu/sample.htm -->
    <style type="text/css">
    

.b-m-mpanel {
    /* images/contextmenu/menu_bg.gif */
    background: url(data:image/gif;base64,R0lGODlhGgABAJEAANDPz/////Dw8AAAACH5BAAAAAAALAAAAAAaAAEAAAIFlI+pB1EAOw==) repeat-y scroll left center #f0f0f0;
    border: 1px solid #718bb7;
    left: 0;
    padding: 2px 0;
    position: absolute;
    top: 0;
    z-index: 99997;
    color:#000;
}
.b-m-split {
    /* images/contextmenu/m_splitLine.gif */
    background: url(data:image/gif;base64,R0lGODlhAQACAIAAAP///9DPzyH5BAAAAAAALAAAAAABAAIAAAICDAoAOw==) repeat-x scroll center center rgba(0, 0, 0, 0);
    font-size: 0;
    height: 6px;
    margin: 0 2px;
}
.b-m-item, .b-m-idisable {
    line-height: 100%;
    margin: 0 2px 0 3px;
    padding: 4px 2px;
}
.b-m-idisable {
    color: #808080;
}
.b-m-ibody, .b-m-arrow {
    overflow: hidden;
    text-overflow: ellipsis;
}
.b-m-arrow {
    /* images/contextmenu/m_arrow.gif */
    background: url(data:image/gif;base64,R0lGODlhBQAJAMQUAFSg6UGJ2TyE04rD/CluvlWUzo7D9ixwwDiC1j2I3UeLyTmE0zJ1v2eg1Tp6zmKq8ZXL/ypxv1Kg7Ct1yv///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAEAABQALAAAAAAFAAkAAAUcIEKNVCKQE7SMkzEE1AQ8DTNJRRQrBHuQDhIlBAA7) no-repeat scroll right center rgba(0, 0, 0, 0);
}
.b-m-idisable .b-m-arrow {
    background: none repeat scroll 0 0 rgba(0, 0, 0, 0);
}
.b-m-item img, .b-m-ifocus img, .b-m-idisable img {
    margin-right: 8px;
    width: 16px;
}
.b-m-ifocus {
    /* images/contextmenu/m_item.gif */
    background: url(data:image/gif;base64,R0lGODlhAQAWAIAAAOvz/dno+yH5BAAAAAAALAAAAAABABYAAAIFhI+hy1oAOw==) repeat-x scroll center bottom rgba(0, 0, 0, 0);
    border: 1px solid #aaccf6;
    line-height: 100%;
    margin: 0 2px 0 3px;
    padding: 3px 1px;
}
.b-m-idisable img {
    visibility: hidden;
}

    
    </style>
    
    <style type="text/css">
    	body {
    		background-color: #000;
    		color: #fff;
    		font-family: "Lucida Grande","Lucida Sans Unicode",Arial,Verdana,sans-serif;
            font-size: 10pt;
    		margin: 0;
    	}
		
		#sgHeaderWrapper {
		    position: fixed;
		    top: 0px;
		    width: 100%;
		}
		
		.sgHeader {
		    height:3em;
		    background: #00f;
		    border-bottom: 1px solid #FFF;
		    margin: 0px auto;
		    width: 100%;
		}
		
		#sgContent {
		    margin: 3.5em auto;
		    width: 100%;
		}
        
    	#sgContent td {
    	   vertical-align: top;
    	}
    	
    	#sgContent {
    		clear: both;
    		display: block;
    	}
    	
    	#sgContent .sgThumbItem {
    		float: left;
    		margin-left: 1em;
    		margin-bottom: 1em;
    	}
    	
    	#sgContent .sgThumbItem img {
    	    border: 0px none transparent;
    		height: 10em;
    	}
    </style>
    
    <!-- jQuery -->
    <script type="text/javascript">

/*! jQuery v1.11.2 | (c) 2005, 2014 jQuery Foundation, Inc. | jquery.org/license */
!function(a,b){"object"==typeof module&&"object"==typeof module.exports?module.exports=a.document?b(a,!0):function(a){if(!a.document)throw new Error("jQuery requires a window with a document");return b(a)}:b(a)}("undefined"!=typeof window?window:this,function(a,b){var c=[],d=c.slice,e=c.concat,f=c.push,g=c.indexOf,h={},i=h.toString,j=h.hasOwnProperty,k={},l="1.11.2",m=function(a,b){return new m.fn.init(a,b)},n=/^[\s\uFEFF\xA0]+|[\s\uFEFF\xA0]+$/g,o=/^-ms-/,p=/-([\da-z])/gi,q=function(a,b){return b.toUpperCase()};m.fn=m.prototype={jquery:l,constructor:m,selector:"",length:0,toArray:function(){return d.call(this)},get:function(a){return null!=a?0>a?this[a+this.length]:this[a]:d.call(this)},pushStack:function(a){var b=m.merge(this.constructor(),a);return b.prevObject=this,b.context=this.context,b},each:function(a,b){return m.each(this,a,b)},map:function(a){return this.pushStack(m.map(this,function(b,c){return a.call(b,c,b)}))},slice:function(){return this.pushStack(d.apply(this,arguments))},first:function(){return this.eq(0)},last:function(){return this.eq(-1)},eq:function(a){var b=this.length,c=+a+(0>a?b:0);return this.pushStack(c>=0&&b>c?[this[c]]:[])},end:function(){return this.prevObject||this.constructor(null)},push:f,sort:c.sort,splice:c.splice},m.extend=m.fn.extend=function(){var a,b,c,d,e,f,g=arguments[0]||{},h=1,i=arguments.length,j=!1;for("boolean"==typeof g&&(j=g,g=arguments[h]||{},h++),"object"==typeof g||m.isFunction(g)||(g={}),h===i&&(g=this,h--);i>h;h++)if(null!=(e=arguments[h]))for(d in e)a=g[d],c=e[d],g!==c&&(j&&c&&(m.isPlainObject(c)||(b=m.isArray(c)))?(b?(b=!1,f=a&&m.isArray(a)?a:[]):f=a&&m.isPlainObject(a)?a:{},g[d]=m.extend(j,f,c)):void 0!==c&&(g[d]=c));return g},m.extend({expando:"jQuery"+(l+Math.random()).replace(/\D/g,""),isReady:!0,error:function(a){throw new Error(a)},noop:function(){},isFunction:function(a){return"function"===m.type(a)},isArray:Array.isArray||function(a){return"array"===m.type(a)},isWindow:function(a){return null!=a&&a==a.window},isNumeric:function(a){return!m.isArray(a)&&a-parseFloat(a)+1>=0},isEmptyObject:function(a){var b;for(b in a)return!1;return!0},isPlainObject:function(a){var b;if(!a||"object"!==m.type(a)||a.nodeType||m.isWindow(a))return!1;try{if(a.constructor&&!j.call(a,"constructor")&&!j.call(a.constructor.prototype,"isPrototypeOf"))return!1}catch(c){return!1}if(k.ownLast)for(b in a)return j.call(a,b);for(b in a);return void 0===b||j.call(a,b)},type:function(a){return null==a?a+"":"object"==typeof a||"function"==typeof a?h[i.call(a)]||"object":typeof a},globalEval:function(b){b&&m.trim(b)&&(a.execScript||function(b){a.eval.call(a,b)})(b)},camelCase:function(a){return a.replace(o,"ms-").replace(p,q)},nodeName:function(a,b){return a.nodeName&&a.nodeName.toLowerCase()===b.toLowerCase()},each:function(a,b,c){var d,e=0,f=a.length,g=r(a);if(c){if(g){for(;f>e;e++)if(d=b.apply(a[e],c),d===!1)break}else for(e in a)if(d=b.apply(a[e],c),d===!1)break}else if(g){for(;f>e;e++)if(d=b.call(a[e],e,a[e]),d===!1)break}else for(e in a)if(d=b.call(a[e],e,a[e]),d===!1)break;return a},trim:function(a){return null==a?"":(a+"").replace(n,"")},makeArray:function(a,b){var c=b||[];return null!=a&&(r(Object(a))?m.merge(c,"string"==typeof a?[a]:a):f.call(c,a)),c},inArray:function(a,b,c){var d;if(b){if(g)return g.call(b,a,c);for(d=b.length,c=c?0>c?Math.max(0,d+c):c:0;d>c;c++)if(c in b&&b[c]===a)return c}return-1},merge:function(a,b){var c=+b.length,d=0,e=a.length;while(c>d)a[e++]=b[d++];if(c!==c)while(void 0!==b[d])a[e++]=b[d++];return a.length=e,a},grep:function(a,b,c){for(var d,e=[],f=0,g=a.length,h=!c;g>f;f++)d=!b(a[f],f),d!==h&&e.push(a[f]);return e},map:function(a,b,c){var d,f=0,g=a.length,h=r(a),i=[];if(h)for(;g>f;f++)d=b(a[f],f,c),null!=d&&i.push(d);else for(f in a)d=b(a[f],f,c),null!=d&&i.push(d);return e.apply([],i)},guid:1,proxy:function(a,b){var c,e,f;return"string"==typeof b&&(f=a[b],b=a,a=f),m.isFunction(a)?(c=d.call(arguments,2),e=function(){return a.apply(b||this,c.concat(d.call(arguments)))},e.guid=a.guid=a.guid||m.guid++,e):void 0},now:function(){return+new Date},support:k}),m.each("Boolean Number String Function Array Date RegExp Object Error".split(" "),function(a,b){h["[object "+b+"]"]=b.toLowerCase()});function r(a){var b=a.length,c=m.type(a);return"function"===c||m.isWindow(a)?!1:1===a.nodeType&&b?!0:"array"===c||0===b||"number"==typeof b&&b>0&&b-1 in a}var s=function(a){var b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u="sizzle"+1*new Date,v=a.document,w=0,x=0,y=hb(),z=hb(),A=hb(),B=function(a,b){return a===b&&(l=!0),0},C=1<<31,D={}.hasOwnProperty,E=[],F=E.pop,G=E.push,H=E.push,I=E.slice,J=function(a,b){for(var c=0,d=a.length;d>c;c++)if(a[c]===b)return c;return-1},K="checked|selected|async|autofocus|autoplay|controls|defer|disabled|hidden|ismap|loop|multiple|open|readonly|required|scoped",L="[\\x20\\t\\r\\n\\f]",M="(?:\\\\.|[\\w-]|[^\\x00-\\xa0])+",N=M.replace("w","w#"),O="\\["+L+"*("+M+")(?:"+L+"*([*^$|!~]?=)"+L+"*(?:'((?:\\\\.|[^\\\\'])*)'|\"((?:\\\\.|[^\\\\\"])*)\"|("+N+"))|)"+L+"*\\]",P=":("+M+")(?:\\((('((?:\\\\.|[^\\\\'])*)'|\"((?:\\\\.|[^\\\\\"])*)\")|((?:\\\\.|[^\\\\()[\\]]|"+O+")*)|.*)\\)|)",Q=new RegExp(L+"+","g"),R=new RegExp("^"+L+"+|((?:^|[^\\\\])(?:\\\\.)*)"+L+"+$","g"),S=new RegExp("^"+L+"*,"+L+"*"),T=new RegExp("^"+L+"*([>+~]|"+L+")"+L+"*"),U=new RegExp("="+L+"*([^\\]'\"]*?)"+L+"*\\]","g"),V=new RegExp(P),W=new RegExp("^"+N+"$"),X={ID:new RegExp("^#("+M+")"),CLASS:new RegExp("^\\.("+M+")"),TAG:new RegExp("^("+M.replace("w","w*")+")"),ATTR:new RegExp("^"+O),PSEUDO:new RegExp("^"+P),CHILD:new RegExp("^:(only|first|last|nth|nth-last)-(child|of-type)(?:\\("+L+"*(even|odd|(([+-]|)(\\d*)n|)"+L+"*(?:([+-]|)"+L+"*(\\d+)|))"+L+"*\\)|)","i"),bool:new RegExp("^(?:"+K+")$","i"),needsContext:new RegExp("^"+L+"*[>+~]|:(even|odd|eq|gt|lt|nth|first|last)(?:\\("+L+"*((?:-\\d)?\\d*)"+L+"*\\)|)(?=[^-]|$)","i")},Y=/^(?:input|select|textarea|button)$/i,Z=/^h\d$/i,$=/^[^{]+\{\s*\[native \w/,_=/^(?:#([\w-]+)|(\w+)|\.([\w-]+))$/,ab=/[+~]/,bb=/'|\\/g,cb=new RegExp("\\\\([\\da-f]{1,6}"+L+"?|("+L+")|.)","ig"),db=function(a,b,c){var d="0x"+b-65536;return d!==d||c?b:0>d?String.fromCharCode(d+65536):String.fromCharCode(d>>10|55296,1023&d|56320)},eb=function(){m()};try{H.apply(E=I.call(v.childNodes),v.childNodes),E[v.childNodes.length].nodeType}catch(fb){H={apply:E.length?function(a,b){G.apply(a,I.call(b))}:function(a,b){var c=a.length,d=0;while(a[c++]=b[d++]);a.length=c-1}}}function gb(a,b,d,e){var f,h,j,k,l,o,r,s,w,x;if((b?b.ownerDocument||b:v)!==n&&m(b),b=b||n,d=d||[],k=b.nodeType,"string"!=typeof a||!a||1!==k&&9!==k&&11!==k)return d;if(!e&&p){if(11!==k&&(f=_.exec(a)))if(j=f[1]){if(9===k){if(h=b.getElementById(j),!h||!h.parentNode)return d;if(h.id===j)return d.push(h),d}else if(b.ownerDocument&&(h=b.ownerDocument.getElementById(j))&&t(b,h)&&h.id===j)return d.push(h),d}else{if(f[2])return H.apply(d,b.getElementsByTagName(a)),d;if((j=f[3])&&c.getElementsByClassName)return H.apply(d,b.getElementsByClassName(j)),d}if(c.qsa&&(!q||!q.test(a))){if(s=r=u,w=b,x=1!==k&&a,1===k&&"object"!==b.nodeName.toLowerCase()){o=g(a),(r=b.getAttribute("id"))?s=r.replace(bb,"\\$&"):b.setAttribute("id",s),s="[id='"+s+"'] ",l=o.length;while(l--)o[l]=s+rb(o[l]);w=ab.test(a)&&pb(b.parentNode)||b,x=o.join(",")}if(x)try{return H.apply(d,w.querySelectorAll(x)),d}catch(y){}finally{r||b.removeAttribute("id")}}}return i(a.replace(R,"$1"),b,d,e)}function hb(){var a=[];function b(c,e){return a.push(c+" ")>d.cacheLength&&delete b[a.shift()],b[c+" "]=e}return b}function ib(a){return a[u]=!0,a}function jb(a){var b=n.createElement("div");try{return!!a(b)}catch(c){return!1}finally{b.parentNode&&b.parentNode.removeChild(b),b=null}}function kb(a,b){var c=a.split("|"),e=a.length;while(e--)d.attrHandle[c[e]]=b}function lb(a,b){var c=b&&a,d=c&&1===a.nodeType&&1===b.nodeType&&(~b.sourceIndex||C)-(~a.sourceIndex||C);if(d)return d;if(c)while(c=c.nextSibling)if(c===b)return-1;return a?1:-1}function mb(a){return function(b){var c=b.nodeName.toLowerCase();return"input"===c&&b.type===a}}function nb(a){return function(b){var c=b.nodeName.toLowerCase();return("input"===c||"button"===c)&&b.type===a}}function ob(a){return ib(function(b){return b=+b,ib(function(c,d){var e,f=a([],c.length,b),g=f.length;while(g--)c[e=f[g]]&&(c[e]=!(d[e]=c[e]))})})}function pb(a){return a&&"undefined"!=typeof a.getElementsByTagName&&a}c=gb.support={},f=gb.isXML=function(a){var b=a&&(a.ownerDocument||a).documentElement;return b?"HTML"!==b.nodeName:!1},m=gb.setDocument=function(a){var b,e,g=a?a.ownerDocument||a:v;return g!==n&&9===g.nodeType&&g.documentElement?(n=g,o=g.documentElement,e=g.defaultView,e&&e!==e.top&&(e.addEventListener?e.addEventListener("unload",eb,!1):e.attachEvent&&e.attachEvent("onunload",eb)),p=!f(g),c.attributes=jb(function(a){return a.className="i",!a.getAttribute("className")}),c.getElementsByTagName=jb(function(a){return a.appendChild(g.createComment("")),!a.getElementsByTagName("*").length}),c.getElementsByClassName=$.test(g.getElementsByClassName),c.getById=jb(function(a){return o.appendChild(a).id=u,!g.getElementsByName||!g.getElementsByName(u).length}),c.getById?(d.find.ID=function(a,b){if("undefined"!=typeof b.getElementById&&p){var c=b.getElementById(a);return c&&c.parentNode?[c]:[]}},d.filter.ID=function(a){var b=a.replace(cb,db);return function(a){return a.getAttribute("id")===b}}):(delete d.find.ID,d.filter.ID=function(a){var b=a.replace(cb,db);return function(a){var c="undefined"!=typeof a.getAttributeNode&&a.getAttributeNode("id");return c&&c.value===b}}),d.find.TAG=c.getElementsByTagName?function(a,b){return"undefined"!=typeof b.getElementsByTagName?b.getElementsByTagName(a):c.qsa?b.querySelectorAll(a):void 0}:function(a,b){var c,d=[],e=0,f=b.getElementsByTagName(a);if("*"===a){while(c=f[e++])1===c.nodeType&&d.push(c);return d}return f},d.find.CLASS=c.getElementsByClassName&&function(a,b){return p?b.getElementsByClassName(a):void 0},r=[],q=[],(c.qsa=$.test(g.querySelectorAll))&&(jb(function(a){o.appendChild(a).innerHTML="<a id='"+u+"'></a><select id='"+u+"-\f]' msallowcapture=''><option selected=''></option></select>",a.querySelectorAll("[msallowcapture^='']").length&&q.push("[*^$]="+L+"*(?:''|\"\")"),a.querySelectorAll("[selected]").length||q.push("\\["+L+"*(?:value|"+K+")"),a.querySelectorAll("[id~="+u+"-]").length||q.push("~="),a.querySelectorAll(":checked").length||q.push(":checked"),a.querySelectorAll("a#"+u+"+*").length||q.push(".#.+[+~]")}),jb(function(a){var b=g.createElement("input");b.setAttribute("type","hidden"),a.appendChild(b).setAttribute("name","D"),a.querySelectorAll("[name=d]").length&&q.push("name"+L+"*[*^$|!~]?="),a.querySelectorAll(":enabled").length||q.push(":enabled",":disabled"),a.querySelectorAll("*,:x"),q.push(",.*:")})),(c.matchesSelector=$.test(s=o.matches||o.webkitMatchesSelector||o.mozMatchesSelector||o.oMatchesSelector||o.msMatchesSelector))&&jb(function(a){c.disconnectedMatch=s.call(a,"div"),s.call(a,"[s!='']:x"),r.push("!=",P)}),q=q.length&&new RegExp(q.join("|")),r=r.length&&new RegExp(r.join("|")),b=$.test(o.compareDocumentPosition),t=b||$.test(o.contains)?function(a,b){var c=9===a.nodeType?a.documentElement:a,d=b&&b.parentNode;return a===d||!(!d||1!==d.nodeType||!(c.contains?c.contains(d):a.compareDocumentPosition&&16&a.compareDocumentPosition(d)))}:function(a,b){if(b)while(b=b.parentNode)if(b===a)return!0;return!1},B=b?function(a,b){if(a===b)return l=!0,0;var d=!a.compareDocumentPosition-!b.compareDocumentPosition;return d?d:(d=(a.ownerDocument||a)===(b.ownerDocument||b)?a.compareDocumentPosition(b):1,1&d||!c.sortDetached&&b.compareDocumentPosition(a)===d?a===g||a.ownerDocument===v&&t(v,a)?-1:b===g||b.ownerDocument===v&&t(v,b)?1:k?J(k,a)-J(k,b):0:4&d?-1:1)}:function(a,b){if(a===b)return l=!0,0;var c,d=0,e=a.parentNode,f=b.parentNode,h=[a],i=[b];if(!e||!f)return a===g?-1:b===g?1:e?-1:f?1:k?J(k,a)-J(k,b):0;if(e===f)return lb(a,b);c=a;while(c=c.parentNode)h.unshift(c);c=b;while(c=c.parentNode)i.unshift(c);while(h[d]===i[d])d++;return d?lb(h[d],i[d]):h[d]===v?-1:i[d]===v?1:0},g):n},gb.matches=function(a,b){return gb(a,null,null,b)},gb.matchesSelector=function(a,b){if((a.ownerDocument||a)!==n&&m(a),b=b.replace(U,"='$1']"),!(!c.matchesSelector||!p||r&&r.test(b)||q&&q.test(b)))try{var d=s.call(a,b);if(d||c.disconnectedMatch||a.document&&11!==a.document.nodeType)return d}catch(e){}return gb(b,n,null,[a]).length>0},gb.contains=function(a,b){return(a.ownerDocument||a)!==n&&m(a),t(a,b)},gb.attr=function(a,b){(a.ownerDocument||a)!==n&&m(a);var e=d.attrHandle[b.toLowerCase()],f=e&&D.call(d.attrHandle,b.toLowerCase())?e(a,b,!p):void 0;return void 0!==f?f:c.attributes||!p?a.getAttribute(b):(f=a.getAttributeNode(b))&&f.specified?f.value:null},gb.error=function(a){throw new Error("Syntax error, unrecognized expression: "+a)},gb.uniqueSort=function(a){var b,d=[],e=0,f=0;if(l=!c.detectDuplicates,k=!c.sortStable&&a.slice(0),a.sort(B),l){while(b=a[f++])b===a[f]&&(e=d.push(f));while(e--)a.splice(d[e],1)}return k=null,a},e=gb.getText=function(a){var b,c="",d=0,f=a.nodeType;if(f){if(1===f||9===f||11===f){if("string"==typeof a.textContent)return a.textContent;for(a=a.firstChild;a;a=a.nextSibling)c+=e(a)}else if(3===f||4===f)return a.nodeValue}else while(b=a[d++])c+=e(b);return c},d=gb.selectors={cacheLength:50,createPseudo:ib,match:X,attrHandle:{},find:{},relative:{">":{dir:"parentNode",first:!0}," ":{dir:"parentNode"},"+":{dir:"previousSibling",first:!0},"~":{dir:"previousSibling"}},preFilter:{ATTR:function(a){return a[1]=a[1].replace(cb,db),a[3]=(a[3]||a[4]||a[5]||"").replace(cb,db),"~="===a[2]&&(a[3]=" "+a[3]+" "),a.slice(0,4)},CHILD:function(a){return a[1]=a[1].toLowerCase(),"nth"===a[1].slice(0,3)?(a[3]||gb.error(a[0]),a[4]=+(a[4]?a[5]+(a[6]||1):2*("even"===a[3]||"odd"===a[3])),a[5]=+(a[7]+a[8]||"odd"===a[3])):a[3]&&gb.error(a[0]),a},PSEUDO:function(a){var b,c=!a[6]&&a[2];return X.CHILD.test(a[0])?null:(a[3]?a[2]=a[4]||a[5]||"":c&&V.test(c)&&(b=g(c,!0))&&(b=c.indexOf(")",c.length-b)-c.length)&&(a[0]=a[0].slice(0,b),a[2]=c.slice(0,b)),a.slice(0,3))}},filter:{TAG:function(a){var b=a.replace(cb,db).toLowerCase();return"*"===a?function(){return!0}:function(a){return a.nodeName&&a.nodeName.toLowerCase()===b}},CLASS:function(a){var b=y[a+" "];return b||(b=new RegExp("(^|"+L+")"+a+"("+L+"|$)"))&&y(a,function(a){return b.test("string"==typeof a.className&&a.className||"undefined"!=typeof a.getAttribute&&a.getAttribute("class")||"")})},ATTR:function(a,b,c){return function(d){var e=gb.attr(d,a);return null==e?"!="===b:b?(e+="","="===b?e===c:"!="===b?e!==c:"^="===b?c&&0===e.indexOf(c):"*="===b?c&&e.indexOf(c)>-1:"$="===b?c&&e.slice(-c.length)===c:"~="===b?(" "+e.replace(Q," ")+" ").indexOf(c)>-1:"|="===b?e===c||e.slice(0,c.length+1)===c+"-":!1):!0}},CHILD:function(a,b,c,d,e){var f="nth"!==a.slice(0,3),g="last"!==a.slice(-4),h="of-type"===b;return 1===d&&0===e?function(a){return!!a.parentNode}:function(b,c,i){var j,k,l,m,n,o,p=f!==g?"nextSibling":"previousSibling",q=b.parentNode,r=h&&b.nodeName.toLowerCase(),s=!i&&!h;if(q){if(f){while(p){l=b;while(l=l[p])if(h?l.nodeName.toLowerCase()===r:1===l.nodeType)return!1;o=p="only"===a&&!o&&"nextSibling"}return!0}if(o=[g?q.firstChild:q.lastChild],g&&s){k=q[u]||(q[u]={}),j=k[a]||[],n=j[0]===w&&j[1],m=j[0]===w&&j[2],l=n&&q.childNodes[n];while(l=++n&&l&&l[p]||(m=n=0)||o.pop())if(1===l.nodeType&&++m&&l===b){k[a]=[w,n,m];break}}else if(s&&(j=(b[u]||(b[u]={}))[a])&&j[0]===w)m=j[1];else while(l=++n&&l&&l[p]||(m=n=0)||o.pop())if((h?l.nodeName.toLowerCase()===r:1===l.nodeType)&&++m&&(s&&((l[u]||(l[u]={}))[a]=[w,m]),l===b))break;return m-=e,m===d||m%d===0&&m/d>=0}}},PSEUDO:function(a,b){var c,e=d.pseudos[a]||d.setFilters[a.toLowerCase()]||gb.error("unsupported pseudo: "+a);return e[u]?e(b):e.length>1?(c=[a,a,"",b],d.setFilters.hasOwnProperty(a.toLowerCase())?ib(function(a,c){var d,f=e(a,b),g=f.length;while(g--)d=J(a,f[g]),a[d]=!(c[d]=f[g])}):function(a){return e(a,0,c)}):e}},pseudos:{not:ib(function(a){var b=[],c=[],d=h(a.replace(R,"$1"));return d[u]?ib(function(a,b,c,e){var f,g=d(a,null,e,[]),h=a.length;while(h--)(f=g[h])&&(a[h]=!(b[h]=f))}):function(a,e,f){return b[0]=a,d(b,null,f,c),b[0]=null,!c.pop()}}),has:ib(function(a){return function(b){return gb(a,b).length>0}}),contains:ib(function(a){return a=a.replace(cb,db),function(b){return(b.textContent||b.innerText||e(b)).indexOf(a)>-1}}),lang:ib(function(a){return W.test(a||"")||gb.error("unsupported lang: "+a),a=a.replace(cb,db).toLowerCase(),function(b){var c;do if(c=p?b.lang:b.getAttribute("xml:lang")||b.getAttribute("lang"))return c=c.toLowerCase(),c===a||0===c.indexOf(a+"-");while((b=b.parentNode)&&1===b.nodeType);return!1}}),target:function(b){var c=a.location&&a.location.hash;return c&&c.slice(1)===b.id},root:function(a){return a===o},focus:function(a){return a===n.activeElement&&(!n.hasFocus||n.hasFocus())&&!!(a.type||a.href||~a.tabIndex)},enabled:function(a){return a.disabled===!1},disabled:function(a){return a.disabled===!0},checked:function(a){var b=a.nodeName.toLowerCase();return"input"===b&&!!a.checked||"option"===b&&!!a.selected},selected:function(a){return a.parentNode&&a.parentNode.selectedIndex,a.selected===!0},empty:function(a){for(a=a.firstChild;a;a=a.nextSibling)if(a.nodeType<6)return!1;return!0},parent:function(a){return!d.pseudos.empty(a)},header:function(a){return Z.test(a.nodeName)},input:function(a){return Y.test(a.nodeName)},button:function(a){var b=a.nodeName.toLowerCase();return"input"===b&&"button"===a.type||"button"===b},text:function(a){var b;return"input"===a.nodeName.toLowerCase()&&"text"===a.type&&(null==(b=a.getAttribute("type"))||"text"===b.toLowerCase())},first:ob(function(){return[0]}),last:ob(function(a,b){return[b-1]}),eq:ob(function(a,b,c){return[0>c?c+b:c]}),even:ob(function(a,b){for(var c=0;b>c;c+=2)a.push(c);return a}),odd:ob(function(a,b){for(var c=1;b>c;c+=2)a.push(c);return a}),lt:ob(function(a,b,c){for(var d=0>c?c+b:c;--d>=0;)a.push(d);return a}),gt:ob(function(a,b,c){for(var d=0>c?c+b:c;++d<b;)a.push(d);return a})}},d.pseudos.nth=d.pseudos.eq;for(b in{radio:!0,checkbox:!0,file:!0,password:!0,image:!0})d.pseudos[b]=mb(b);for(b in{submit:!0,reset:!0})d.pseudos[b]=nb(b);function qb(){}qb.prototype=d.filters=d.pseudos,d.setFilters=new qb,g=gb.tokenize=function(a,b){var c,e,f,g,h,i,j,k=z[a+" "];if(k)return b?0:k.slice(0);h=a,i=[],j=d.preFilter;while(h){(!c||(e=S.exec(h)))&&(e&&(h=h.slice(e[0].length)||h),i.push(f=[])),c=!1,(e=T.exec(h))&&(c=e.shift(),f.push({value:c,type:e[0].replace(R," ")}),h=h.slice(c.length));for(g in d.filter)!(e=X[g].exec(h))||j[g]&&!(e=j[g](e))||(c=e.shift(),f.push({value:c,type:g,matches:e}),h=h.slice(c.length));if(!c)break}return b?h.length:h?gb.error(a):z(a,i).slice(0)};function rb(a){for(var b=0,c=a.length,d="";c>b;b++)d+=a[b].value;return d}function sb(a,b,c){var d=b.dir,e=c&&"parentNode"===d,f=x++;return b.first?function(b,c,f){while(b=b[d])if(1===b.nodeType||e)return a(b,c,f)}:function(b,c,g){var h,i,j=[w,f];if(g){while(b=b[d])if((1===b.nodeType||e)&&a(b,c,g))return!0}else while(b=b[d])if(1===b.nodeType||e){if(i=b[u]||(b[u]={}),(h=i[d])&&h[0]===w&&h[1]===f)return j[2]=h[2];if(i[d]=j,j[2]=a(b,c,g))return!0}}}function tb(a){return a.length>1?function(b,c,d){var e=a.length;while(e--)if(!a[e](b,c,d))return!1;return!0}:a[0]}function ub(a,b,c){for(var d=0,e=b.length;e>d;d++)gb(a,b[d],c);return c}function vb(a,b,c,d,e){for(var f,g=[],h=0,i=a.length,j=null!=b;i>h;h++)(f=a[h])&&(!c||c(f,d,e))&&(g.push(f),j&&b.push(h));return g}function wb(a,b,c,d,e,f){return d&&!d[u]&&(d=wb(d)),e&&!e[u]&&(e=wb(e,f)),ib(function(f,g,h,i){var j,k,l,m=[],n=[],o=g.length,p=f||ub(b||"*",h.nodeType?[h]:h,[]),q=!a||!f&&b?p:vb(p,m,a,h,i),r=c?e||(f?a:o||d)?[]:g:q;if(c&&c(q,r,h,i),d){j=vb(r,n),d(j,[],h,i),k=j.length;while(k--)(l=j[k])&&(r[n[k]]=!(q[n[k]]=l))}if(f){if(e||a){if(e){j=[],k=r.length;while(k--)(l=r[k])&&j.push(q[k]=l);e(null,r=[],j,i)}k=r.length;while(k--)(l=r[k])&&(j=e?J(f,l):m[k])>-1&&(f[j]=!(g[j]=l))}}else r=vb(r===g?r.splice(o,r.length):r),e?e(null,g,r,i):H.apply(g,r)})}function xb(a){for(var b,c,e,f=a.length,g=d.relative[a[0].type],h=g||d.relative[" "],i=g?1:0,k=sb(function(a){return a===b},h,!0),l=sb(function(a){return J(b,a)>-1},h,!0),m=[function(a,c,d){var e=!g&&(d||c!==j)||((b=c).nodeType?k(a,c,d):l(a,c,d));return b=null,e}];f>i;i++)if(c=d.relative[a[i].type])m=[sb(tb(m),c)];else{if(c=d.filter[a[i].type].apply(null,a[i].matches),c[u]){for(e=++i;f>e;e++)if(d.relative[a[e].type])break;return wb(i>1&&tb(m),i>1&&rb(a.slice(0,i-1).concat({value:" "===a[i-2].type?"*":""})).replace(R,"$1"),c,e>i&&xb(a.slice(i,e)),f>e&&xb(a=a.slice(e)),f>e&&rb(a))}m.push(c)}return tb(m)}function yb(a,b){var c=b.length>0,e=a.length>0,f=function(f,g,h,i,k){var l,m,o,p=0,q="0",r=f&&[],s=[],t=j,u=f||e&&d.find.TAG("*",k),v=w+=null==t?1:Math.random()||.1,x=u.length;for(k&&(j=g!==n&&g);q!==x&&null!=(l=u[q]);q++){if(e&&l){m=0;while(o=a[m++])if(o(l,g,h)){i.push(l);break}k&&(w=v)}c&&((l=!o&&l)&&p--,f&&r.push(l))}if(p+=q,c&&q!==p){m=0;while(o=b[m++])o(r,s,g,h);if(f){if(p>0)while(q--)r[q]||s[q]||(s[q]=F.call(i));s=vb(s)}H.apply(i,s),k&&!f&&s.length>0&&p+b.length>1&&gb.uniqueSort(i)}return k&&(w=v,j=t),r};return c?ib(f):f}return h=gb.compile=function(a,b){var c,d=[],e=[],f=A[a+" "];if(!f){b||(b=g(a)),c=b.length;while(c--)f=xb(b[c]),f[u]?d.push(f):e.push(f);f=A(a,yb(e,d)),f.selector=a}return f},i=gb.select=function(a,b,e,f){var i,j,k,l,m,n="function"==typeof a&&a,o=!f&&g(a=n.selector||a);if(e=e||[],1===o.length){if(j=o[0]=o[0].slice(0),j.length>2&&"ID"===(k=j[0]).type&&c.getById&&9===b.nodeType&&p&&d.relative[j[1].type]){if(b=(d.find.ID(k.matches[0].replace(cb,db),b)||[])[0],!b)return e;n&&(b=b.parentNode),a=a.slice(j.shift().value.length)}i=X.needsContext.test(a)?0:j.length;while(i--){if(k=j[i],d.relative[l=k.type])break;if((m=d.find[l])&&(f=m(k.matches[0].replace(cb,db),ab.test(j[0].type)&&pb(b.parentNode)||b))){if(j.splice(i,1),a=f.length&&rb(j),!a)return H.apply(e,f),e;break}}}return(n||h(a,o))(f,b,!p,e,ab.test(a)&&pb(b.parentNode)||b),e},c.sortStable=u.split("").sort(B).join("")===u,c.detectDuplicates=!!l,m(),c.sortDetached=jb(function(a){return 1&a.compareDocumentPosition(n.createElement("div"))}),jb(function(a){return a.innerHTML="<a href='#'></a>","#"===a.firstChild.getAttribute("href")})||kb("type|href|height|width",function(a,b,c){return c?void 0:a.getAttribute(b,"type"===b.toLowerCase()?1:2)}),c.attributes&&jb(function(a){return a.innerHTML="<input/>",a.firstChild.setAttribute("value",""),""===a.firstChild.getAttribute("value")})||kb("value",function(a,b,c){return c||"input"!==a.nodeName.toLowerCase()?void 0:a.defaultValue}),jb(function(a){return null==a.getAttribute("disabled")})||kb(K,function(a,b,c){var d;return c?void 0:a[b]===!0?b.toLowerCase():(d=a.getAttributeNode(b))&&d.specified?d.value:null}),gb}(a);m.find=s,m.expr=s.selectors,m.expr[":"]=m.expr.pseudos,m.unique=s.uniqueSort,m.text=s.getText,m.isXMLDoc=s.isXML,m.contains=s.contains;var t=m.expr.match.needsContext,u=/^<(\w+)\s*\/?>(?:<\/\1>|)$/,v=/^.[^:#\[\.,]*$/;function w(a,b,c){if(m.isFunction(b))return m.grep(a,function(a,d){return!!b.call(a,d,a)!==c});if(b.nodeType)return m.grep(a,function(a){return a===b!==c});if("string"==typeof b){if(v.test(b))return m.filter(b,a,c);b=m.filter(b,a)}return m.grep(a,function(a){return m.inArray(a,b)>=0!==c})}m.filter=function(a,b,c){var d=b[0];return c&&(a=":not("+a+")"),1===b.length&&1===d.nodeType?m.find.matchesSelector(d,a)?[d]:[]:m.find.matches(a,m.grep(b,function(a){return 1===a.nodeType}))},m.fn.extend({find:function(a){var b,c=[],d=this,e=d.length;if("string"!=typeof a)return this.pushStack(m(a).filter(function(){for(b=0;e>b;b++)if(m.contains(d[b],this))return!0}));for(b=0;e>b;b++)m.find(a,d[b],c);return c=this.pushStack(e>1?m.unique(c):c),c.selector=this.selector?this.selector+" "+a:a,c},filter:function(a){return this.pushStack(w(this,a||[],!1))},not:function(a){return this.pushStack(w(this,a||[],!0))},is:function(a){return!!w(this,"string"==typeof a&&t.test(a)?m(a):a||[],!1).length}});var x,y=a.document,z=/^(?:\s*(<[\w\W]+>)[^>]*|#([\w-]*))$/,A=m.fn.init=function(a,b){var c,d;if(!a)return this;if("string"==typeof a){if(c="<"===a.charAt(0)&&">"===a.charAt(a.length-1)&&a.length>=3?[null,a,null]:z.exec(a),!c||!c[1]&&b)return!b||b.jquery?(b||x).find(a):this.constructor(b).find(a);if(c[1]){if(b=b instanceof m?b[0]:b,m.merge(this,m.parseHTML(c[1],b&&b.nodeType?b.ownerDocument||b:y,!0)),u.test(c[1])&&m.isPlainObject(b))for(c in b)m.isFunction(this[c])?this[c](b[c]):this.attr(c,b[c]);return this}if(d=y.getElementById(c[2]),d&&d.parentNode){if(d.id!==c[2])return x.find(a);this.length=1,this[0]=d}return this.context=y,this.selector=a,this}return a.nodeType?(this.context=this[0]=a,this.length=1,this):m.isFunction(a)?"undefined"!=typeof x.ready?x.ready(a):a(m):(void 0!==a.selector&&(this.selector=a.selector,this.context=a.context),m.makeArray(a,this))};A.prototype=m.fn,x=m(y);var B=/^(?:parents|prev(?:Until|All))/,C={children:!0,contents:!0,next:!0,prev:!0};m.extend({dir:function(a,b,c){var d=[],e=a[b];while(e&&9!==e.nodeType&&(void 0===c||1!==e.nodeType||!m(e).is(c)))1===e.nodeType&&d.push(e),e=e[b];return d},sibling:function(a,b){for(var c=[];a;a=a.nextSibling)1===a.nodeType&&a!==b&&c.push(a);return c}}),m.fn.extend({has:function(a){var b,c=m(a,this),d=c.length;return this.filter(function(){for(b=0;d>b;b++)if(m.contains(this,c[b]))return!0})},closest:function(a,b){for(var c,d=0,e=this.length,f=[],g=t.test(a)||"string"!=typeof a?m(a,b||this.context):0;e>d;d++)for(c=this[d];c&&c!==b;c=c.parentNode)if(c.nodeType<11&&(g?g.index(c)>-1:1===c.nodeType&&m.find.matchesSelector(c,a))){f.push(c);break}return this.pushStack(f.length>1?m.unique(f):f)},index:function(a){return a?"string"==typeof a?m.inArray(this[0],m(a)):m.inArray(a.jquery?a[0]:a,this):this[0]&&this[0].parentNode?this.first().prevAll().length:-1},add:function(a,b){return this.pushStack(m.unique(m.merge(this.get(),m(a,b))))},addBack:function(a){return this.add(null==a?this.prevObject:this.prevObject.filter(a))}});function D(a,b){do a=a[b];while(a&&1!==a.nodeType);return a}m.each({parent:function(a){var b=a.parentNode;return b&&11!==b.nodeType?b:null},parents:function(a){return m.dir(a,"parentNode")},parentsUntil:function(a,b,c){return m.dir(a,"parentNode",c)},next:function(a){return D(a,"nextSibling")},prev:function(a){return D(a,"previousSibling")},nextAll:function(a){return m.dir(a,"nextSibling")},prevAll:function(a){return m.dir(a,"previousSibling")},nextUntil:function(a,b,c){return m.dir(a,"nextSibling",c)},prevUntil:function(a,b,c){return m.dir(a,"previousSibling",c)},siblings:function(a){return m.sibling((a.parentNode||{}).firstChild,a)},children:function(a){return m.sibling(a.firstChild)},contents:function(a){return m.nodeName(a,"iframe")?a.contentDocument||a.contentWindow.document:m.merge([],a.childNodes)}},function(a,b){m.fn[a]=function(c,d){var e=m.map(this,b,c);return"Until"!==a.slice(-5)&&(d=c),d&&"string"==typeof d&&(e=m.filter(d,e)),this.length>1&&(C[a]||(e=m.unique(e)),B.test(a)&&(e=e.reverse())),this.pushStack(e)}});var E=/\S+/g,F={};function G(a){var b=F[a]={};return m.each(a.match(E)||[],function(a,c){b[c]=!0}),b}m.Callbacks=function(a){a="string"==typeof a?F[a]||G(a):m.extend({},a);var b,c,d,e,f,g,h=[],i=!a.once&&[],j=function(l){for(c=a.memory&&l,d=!0,f=g||0,g=0,e=h.length,b=!0;h&&e>f;f++)if(h[f].apply(l[0],l[1])===!1&&a.stopOnFalse){c=!1;break}b=!1,h&&(i?i.length&&j(i.shift()):c?h=[]:k.disable())},k={add:function(){if(h){var d=h.length;!function f(b){m.each(b,function(b,c){var d=m.type(c);"function"===d?a.unique&&k.has(c)||h.push(c):c&&c.length&&"string"!==d&&f(c)})}(arguments),b?e=h.length:c&&(g=d,j(c))}return this},remove:function(){return h&&m.each(arguments,function(a,c){var d;while((d=m.inArray(c,h,d))>-1)h.splice(d,1),b&&(e>=d&&e--,f>=d&&f--)}),this},has:function(a){return a?m.inArray(a,h)>-1:!(!h||!h.length)},empty:function(){return h=[],e=0,this},disable:function(){return h=i=c=void 0,this},disabled:function(){return!h},lock:function(){return i=void 0,c||k.disable(),this},locked:function(){return!i},fireWith:function(a,c){return!h||d&&!i||(c=c||[],c=[a,c.slice?c.slice():c],b?i.push(c):j(c)),this},fire:function(){return k.fireWith(this,arguments),this},fired:function(){return!!d}};return k},m.extend({Deferred:function(a){var b=[["resolve","done",m.Callbacks("once memory"),"resolved"],["reject","fail",m.Callbacks("once memory"),"rejected"],["notify","progress",m.Callbacks("memory")]],c="pending",d={state:function(){return c},always:function(){return e.done(arguments).fail(arguments),this},then:function(){var a=arguments;return m.Deferred(function(c){m.each(b,function(b,f){var g=m.isFunction(a[b])&&a[b];e[f[1]](function(){var a=g&&g.apply(this,arguments);a&&m.isFunction(a.promise)?a.promise().done(c.resolve).fail(c.reject).progress(c.notify):c[f[0]+"With"](this===d?c.promise():this,g?[a]:arguments)})}),a=null}).promise()},promise:function(a){return null!=a?m.extend(a,d):d}},e={};return d.pipe=d.then,m.each(b,function(a,f){var g=f[2],h=f[3];d[f[1]]=g.add,h&&g.add(function(){c=h},b[1^a][2].disable,b[2][2].lock),e[f[0]]=function(){return e[f[0]+"With"](this===e?d:this,arguments),this},e[f[0]+"With"]=g.fireWith}),d.promise(e),a&&a.call(e,e),e},when:function(a){var b=0,c=d.call(arguments),e=c.length,f=1!==e||a&&m.isFunction(a.promise)?e:0,g=1===f?a:m.Deferred(),h=function(a,b,c){return function(e){b[a]=this,c[a]=arguments.length>1?d.call(arguments):e,c===i?g.notifyWith(b,c):--f||g.resolveWith(b,c)}},i,j,k;if(e>1)for(i=new Array(e),j=new Array(e),k=new Array(e);e>b;b++)c[b]&&m.isFunction(c[b].promise)?c[b].promise().done(h(b,k,c)).fail(g.reject).progress(h(b,j,i)):--f;return f||g.resolveWith(k,c),g.promise()}});var H;m.fn.ready=function(a){return m.ready.promise().done(a),this},m.extend({isReady:!1,readyWait:1,holdReady:function(a){a?m.readyWait++:m.ready(!0)},ready:function(a){if(a===!0?!--m.readyWait:!m.isReady){if(!y.body)return setTimeout(m.ready);m.isReady=!0,a!==!0&&--m.readyWait>0||(H.resolveWith(y,[m]),m.fn.triggerHandler&&(m(y).triggerHandler("ready"),m(y).off("ready")))}}});function I(){y.addEventListener?(y.removeEventListener("DOMContentLoaded",J,!1),a.removeEventListener("load",J,!1)):(y.detachEvent("onreadystatechange",J),a.detachEvent("onload",J))}function J(){(y.addEventListener||"load"===event.type||"complete"===y.readyState)&&(I(),m.ready())}m.ready.promise=function(b){if(!H)if(H=m.Deferred(),"complete"===y.readyState)setTimeout(m.ready);else if(y.addEventListener)y.addEventListener("DOMContentLoaded",J,!1),a.addEventListener("load",J,!1);else{y.attachEvent("onreadystatechange",J),a.attachEvent("onload",J);var c=!1;try{c=null==a.frameElement&&y.documentElement}catch(d){}c&&c.doScroll&&!function e(){if(!m.isReady){try{c.doScroll("left")}catch(a){return setTimeout(e,50)}I(),m.ready()}}()}return H.promise(b)};var K="undefined",L;for(L in m(k))break;k.ownLast="0"!==L,k.inlineBlockNeedsLayout=!1,m(function(){var a,b,c,d;c=y.getElementsByTagName("body")[0],c&&c.style&&(b=y.createElement("div"),d=y.createElement("div"),d.style.cssText="position:absolute;border:0;width:0;height:0;top:0;left:-9999px",c.appendChild(d).appendChild(b),typeof b.style.zoom!==K&&(b.style.cssText="display:inline;margin:0;border:0;padding:1px;width:1px;zoom:1",k.inlineBlockNeedsLayout=a=3===b.offsetWidth,a&&(c.style.zoom=1)),c.removeChild(d))}),function(){var a=y.createElement("div");if(null==k.deleteExpando){k.deleteExpando=!0;try{delete a.test}catch(b){k.deleteExpando=!1}}a=null}(),m.acceptData=function(a){var b=m.noData[(a.nodeName+" ").toLowerCase()],c=+a.nodeType||1;return 1!==c&&9!==c?!1:!b||b!==!0&&a.getAttribute("classid")===b};var M=/^(?:\{[\w\W]*\}|\[[\w\W]*\])$/,N=/([A-Z])/g;function O(a,b,c){if(void 0===c&&1===a.nodeType){var d="data-"+b.replace(N,"-$1").toLowerCase();if(c=a.getAttribute(d),"string"==typeof c){try{c="true"===c?!0:"false"===c?!1:"null"===c?null:+c+""===c?+c:M.test(c)?m.parseJSON(c):c}catch(e){}m.data(a,b,c)}else c=void 0}return c}function P(a){var b;for(b in a)if(("data"!==b||!m.isEmptyObject(a[b]))&&"toJSON"!==b)return!1;
return!0}function Q(a,b,d,e){if(m.acceptData(a)){var f,g,h=m.expando,i=a.nodeType,j=i?m.cache:a,k=i?a[h]:a[h]&&h;if(k&&j[k]&&(e||j[k].data)||void 0!==d||"string"!=typeof b)return k||(k=i?a[h]=c.pop()||m.guid++:h),j[k]||(j[k]=i?{}:{toJSON:m.noop}),("object"==typeof b||"function"==typeof b)&&(e?j[k]=m.extend(j[k],b):j[k].data=m.extend(j[k].data,b)),g=j[k],e||(g.data||(g.data={}),g=g.data),void 0!==d&&(g[m.camelCase(b)]=d),"string"==typeof b?(f=g[b],null==f&&(f=g[m.camelCase(b)])):f=g,f}}function R(a,b,c){if(m.acceptData(a)){var d,e,f=a.nodeType,g=f?m.cache:a,h=f?a[m.expando]:m.expando;if(g[h]){if(b&&(d=c?g[h]:g[h].data)){m.isArray(b)?b=b.concat(m.map(b,m.camelCase)):b in d?b=[b]:(b=m.camelCase(b),b=b in d?[b]:b.split(" ")),e=b.length;while(e--)delete d[b[e]];if(c?!P(d):!m.isEmptyObject(d))return}(c||(delete g[h].data,P(g[h])))&&(f?m.cleanData([a],!0):k.deleteExpando||g!=g.window?delete g[h]:g[h]=null)}}}m.extend({cache:{},noData:{"applet ":!0,"embed ":!0,"object ":"clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"},hasData:function(a){return a=a.nodeType?m.cache[a[m.expando]]:a[m.expando],!!a&&!P(a)},data:function(a,b,c){return Q(a,b,c)},removeData:function(a,b){return R(a,b)},_data:function(a,b,c){return Q(a,b,c,!0)},_removeData:function(a,b){return R(a,b,!0)}}),m.fn.extend({data:function(a,b){var c,d,e,f=this[0],g=f&&f.attributes;if(void 0===a){if(this.length&&(e=m.data(f),1===f.nodeType&&!m._data(f,"parsedAttrs"))){c=g.length;while(c--)g[c]&&(d=g[c].name,0===d.indexOf("data-")&&(d=m.camelCase(d.slice(5)),O(f,d,e[d])));m._data(f,"parsedAttrs",!0)}return e}return"object"==typeof a?this.each(function(){m.data(this,a)}):arguments.length>1?this.each(function(){m.data(this,a,b)}):f?O(f,a,m.data(f,a)):void 0},removeData:function(a){return this.each(function(){m.removeData(this,a)})}}),m.extend({queue:function(a,b,c){var d;return a?(b=(b||"fx")+"queue",d=m._data(a,b),c&&(!d||m.isArray(c)?d=m._data(a,b,m.makeArray(c)):d.push(c)),d||[]):void 0},dequeue:function(a,b){b=b||"fx";var c=m.queue(a,b),d=c.length,e=c.shift(),f=m._queueHooks(a,b),g=function(){m.dequeue(a,b)};"inprogress"===e&&(e=c.shift(),d--),e&&("fx"===b&&c.unshift("inprogress"),delete f.stop,e.call(a,g,f)),!d&&f&&f.empty.fire()},_queueHooks:function(a,b){var c=b+"queueHooks";return m._data(a,c)||m._data(a,c,{empty:m.Callbacks("once memory").add(function(){m._removeData(a,b+"queue"),m._removeData(a,c)})})}}),m.fn.extend({queue:function(a,b){var c=2;return"string"!=typeof a&&(b=a,a="fx",c--),arguments.length<c?m.queue(this[0],a):void 0===b?this:this.each(function(){var c=m.queue(this,a,b);m._queueHooks(this,a),"fx"===a&&"inprogress"!==c[0]&&m.dequeue(this,a)})},dequeue:function(a){return this.each(function(){m.dequeue(this,a)})},clearQueue:function(a){return this.queue(a||"fx",[])},promise:function(a,b){var c,d=1,e=m.Deferred(),f=this,g=this.length,h=function(){--d||e.resolveWith(f,[f])};"string"!=typeof a&&(b=a,a=void 0),a=a||"fx";while(g--)c=m._data(f[g],a+"queueHooks"),c&&c.empty&&(d++,c.empty.add(h));return h(),e.promise(b)}});var S=/[+-]?(?:\d*\.|)\d+(?:[eE][+-]?\d+|)/.source,T=["Top","Right","Bottom","Left"],U=function(a,b){return a=b||a,"none"===m.css(a,"display")||!m.contains(a.ownerDocument,a)},V=m.access=function(a,b,c,d,e,f,g){var h=0,i=a.length,j=null==c;if("object"===m.type(c)){e=!0;for(h in c)m.access(a,b,h,c[h],!0,f,g)}else if(void 0!==d&&(e=!0,m.isFunction(d)||(g=!0),j&&(g?(b.call(a,d),b=null):(j=b,b=function(a,b,c){return j.call(m(a),c)})),b))for(;i>h;h++)b(a[h],c,g?d:d.call(a[h],h,b(a[h],c)));return e?a:j?b.call(a):i?b(a[0],c):f},W=/^(?:checkbox|radio)$/i;!function(){var a=y.createElement("input"),b=y.createElement("div"),c=y.createDocumentFragment();if(b.innerHTML="  <link/><table></table><a href='/a'>a</a><input type='checkbox'/>",k.leadingWhitespace=3===b.firstChild.nodeType,k.tbody=!b.getElementsByTagName("tbody").length,k.htmlSerialize=!!b.getElementsByTagName("link").length,k.html5Clone="<:nav></:nav>"!==y.createElement("nav").cloneNode(!0).outerHTML,a.type="checkbox",a.checked=!0,c.appendChild(a),k.appendChecked=a.checked,b.innerHTML="<textarea>x</textarea>",k.noCloneChecked=!!b.cloneNode(!0).lastChild.defaultValue,c.appendChild(b),b.innerHTML="<input type='radio' checked='checked' name='t'/>",k.checkClone=b.cloneNode(!0).cloneNode(!0).lastChild.checked,k.noCloneEvent=!0,b.attachEvent&&(b.attachEvent("onclick",function(){k.noCloneEvent=!1}),b.cloneNode(!0).click()),null==k.deleteExpando){k.deleteExpando=!0;try{delete b.test}catch(d){k.deleteExpando=!1}}}(),function(){var b,c,d=y.createElement("div");for(b in{submit:!0,change:!0,focusin:!0})c="on"+b,(k[b+"Bubbles"]=c in a)||(d.setAttribute(c,"t"),k[b+"Bubbles"]=d.attributes[c].expando===!1);d=null}();var X=/^(?:input|select|textarea)$/i,Y=/^key/,Z=/^(?:mouse|pointer|contextmenu)|click/,$=/^(?:focusinfocus|focusoutblur)$/,_=/^([^.]*)(?:\.(.+)|)$/;function ab(){return!0}function bb(){return!1}function cb(){try{return y.activeElement}catch(a){}}m.event={global:{},add:function(a,b,c,d,e){var f,g,h,i,j,k,l,n,o,p,q,r=m._data(a);if(r){c.handler&&(i=c,c=i.handler,e=i.selector),c.guid||(c.guid=m.guid++),(g=r.events)||(g=r.events={}),(k=r.handle)||(k=r.handle=function(a){return typeof m===K||a&&m.event.triggered===a.type?void 0:m.event.dispatch.apply(k.elem,arguments)},k.elem=a),b=(b||"").match(E)||[""],h=b.length;while(h--)f=_.exec(b[h])||[],o=q=f[1],p=(f[2]||"").split(".").sort(),o&&(j=m.event.special[o]||{},o=(e?j.delegateType:j.bindType)||o,j=m.event.special[o]||{},l=m.extend({type:o,origType:q,data:d,handler:c,guid:c.guid,selector:e,needsContext:e&&m.expr.match.needsContext.test(e),namespace:p.join(".")},i),(n=g[o])||(n=g[o]=[],n.delegateCount=0,j.setup&&j.setup.call(a,d,p,k)!==!1||(a.addEventListener?a.addEventListener(o,k,!1):a.attachEvent&&a.attachEvent("on"+o,k))),j.add&&(j.add.call(a,l),l.handler.guid||(l.handler.guid=c.guid)),e?n.splice(n.delegateCount++,0,l):n.push(l),m.event.global[o]=!0);a=null}},remove:function(a,b,c,d,e){var f,g,h,i,j,k,l,n,o,p,q,r=m.hasData(a)&&m._data(a);if(r&&(k=r.events)){b=(b||"").match(E)||[""],j=b.length;while(j--)if(h=_.exec(b[j])||[],o=q=h[1],p=(h[2]||"").split(".").sort(),o){l=m.event.special[o]||{},o=(d?l.delegateType:l.bindType)||o,n=k[o]||[],h=h[2]&&new RegExp("(^|\\.)"+p.join("\\.(?:.*\\.|)")+"(\\.|$)"),i=f=n.length;while(f--)g=n[f],!e&&q!==g.origType||c&&c.guid!==g.guid||h&&!h.test(g.namespace)||d&&d!==g.selector&&("**"!==d||!g.selector)||(n.splice(f,1),g.selector&&n.delegateCount--,l.remove&&l.remove.call(a,g));i&&!n.length&&(l.teardown&&l.teardown.call(a,p,r.handle)!==!1||m.removeEvent(a,o,r.handle),delete k[o])}else for(o in k)m.event.remove(a,o+b[j],c,d,!0);m.isEmptyObject(k)&&(delete r.handle,m._removeData(a,"events"))}},trigger:function(b,c,d,e){var f,g,h,i,k,l,n,o=[d||y],p=j.call(b,"type")?b.type:b,q=j.call(b,"namespace")?b.namespace.split("."):[];if(h=l=d=d||y,3!==d.nodeType&&8!==d.nodeType&&!$.test(p+m.event.triggered)&&(p.indexOf(".")>=0&&(q=p.split("."),p=q.shift(),q.sort()),g=p.indexOf(":")<0&&"on"+p,b=b[m.expando]?b:new m.Event(p,"object"==typeof b&&b),b.isTrigger=e?2:3,b.namespace=q.join("."),b.namespace_re=b.namespace?new RegExp("(^|\\.)"+q.join("\\.(?:.*\\.|)")+"(\\.|$)"):null,b.result=void 0,b.target||(b.target=d),c=null==c?[b]:m.makeArray(c,[b]),k=m.event.special[p]||{},e||!k.trigger||k.trigger.apply(d,c)!==!1)){if(!e&&!k.noBubble&&!m.isWindow(d)){for(i=k.delegateType||p,$.test(i+p)||(h=h.parentNode);h;h=h.parentNode)o.push(h),l=h;l===(d.ownerDocument||y)&&o.push(l.defaultView||l.parentWindow||a)}n=0;while((h=o[n++])&&!b.isPropagationStopped())b.type=n>1?i:k.bindType||p,f=(m._data(h,"events")||{})[b.type]&&m._data(h,"handle"),f&&f.apply(h,c),f=g&&h[g],f&&f.apply&&m.acceptData(h)&&(b.result=f.apply(h,c),b.result===!1&&b.preventDefault());if(b.type=p,!e&&!b.isDefaultPrevented()&&(!k._default||k._default.apply(o.pop(),c)===!1)&&m.acceptData(d)&&g&&d[p]&&!m.isWindow(d)){l=d[g],l&&(d[g]=null),m.event.triggered=p;try{d[p]()}catch(r){}m.event.triggered=void 0,l&&(d[g]=l)}return b.result}},dispatch:function(a){a=m.event.fix(a);var b,c,e,f,g,h=[],i=d.call(arguments),j=(m._data(this,"events")||{})[a.type]||[],k=m.event.special[a.type]||{};if(i[0]=a,a.delegateTarget=this,!k.preDispatch||k.preDispatch.call(this,a)!==!1){h=m.event.handlers.call(this,a,j),b=0;while((f=h[b++])&&!a.isPropagationStopped()){a.currentTarget=f.elem,g=0;while((e=f.handlers[g++])&&!a.isImmediatePropagationStopped())(!a.namespace_re||a.namespace_re.test(e.namespace))&&(a.handleObj=e,a.data=e.data,c=((m.event.special[e.origType]||{}).handle||e.handler).apply(f.elem,i),void 0!==c&&(a.result=c)===!1&&(a.preventDefault(),a.stopPropagation()))}return k.postDispatch&&k.postDispatch.call(this,a),a.result}},handlers:function(a,b){var c,d,e,f,g=[],h=b.delegateCount,i=a.target;if(h&&i.nodeType&&(!a.button||"click"!==a.type))for(;i!=this;i=i.parentNode||this)if(1===i.nodeType&&(i.disabled!==!0||"click"!==a.type)){for(e=[],f=0;h>f;f++)d=b[f],c=d.selector+" ",void 0===e[c]&&(e[c]=d.needsContext?m(c,this).index(i)>=0:m.find(c,this,null,[i]).length),e[c]&&e.push(d);e.length&&g.push({elem:i,handlers:e})}return h<b.length&&g.push({elem:this,handlers:b.slice(h)}),g},fix:function(a){if(a[m.expando])return a;var b,c,d,e=a.type,f=a,g=this.fixHooks[e];g||(this.fixHooks[e]=g=Z.test(e)?this.mouseHooks:Y.test(e)?this.keyHooks:{}),d=g.props?this.props.concat(g.props):this.props,a=new m.Event(f),b=d.length;while(b--)c=d[b],a[c]=f[c];return a.target||(a.target=f.srcElement||y),3===a.target.nodeType&&(a.target=a.target.parentNode),a.metaKey=!!a.metaKey,g.filter?g.filter(a,f):a},props:"altKey bubbles cancelable ctrlKey currentTarget eventPhase metaKey relatedTarget shiftKey target timeStamp view which".split(" "),fixHooks:{},keyHooks:{props:"char charCode key keyCode".split(" "),filter:function(a,b){return null==a.which&&(a.which=null!=b.charCode?b.charCode:b.keyCode),a}},mouseHooks:{props:"button buttons clientX clientY fromElement offsetX offsetY pageX pageY screenX screenY toElement".split(" "),filter:function(a,b){var c,d,e,f=b.button,g=b.fromElement;return null==a.pageX&&null!=b.clientX&&(d=a.target.ownerDocument||y,e=d.documentElement,c=d.body,a.pageX=b.clientX+(e&&e.scrollLeft||c&&c.scrollLeft||0)-(e&&e.clientLeft||c&&c.clientLeft||0),a.pageY=b.clientY+(e&&e.scrollTop||c&&c.scrollTop||0)-(e&&e.clientTop||c&&c.clientTop||0)),!a.relatedTarget&&g&&(a.relatedTarget=g===a.target?b.toElement:g),a.which||void 0===f||(a.which=1&f?1:2&f?3:4&f?2:0),a}},special:{load:{noBubble:!0},focus:{trigger:function(){if(this!==cb()&&this.focus)try{return this.focus(),!1}catch(a){}},delegateType:"focusin"},blur:{trigger:function(){return this===cb()&&this.blur?(this.blur(),!1):void 0},delegateType:"focusout"},click:{trigger:function(){return m.nodeName(this,"input")&&"checkbox"===this.type&&this.click?(this.click(),!1):void 0},_default:function(a){return m.nodeName(a.target,"a")}},beforeunload:{postDispatch:function(a){void 0!==a.result&&a.originalEvent&&(a.originalEvent.returnValue=a.result)}}},simulate:function(a,b,c,d){var e=m.extend(new m.Event,c,{type:a,isSimulated:!0,originalEvent:{}});d?m.event.trigger(e,null,b):m.event.dispatch.call(b,e),e.isDefaultPrevented()&&c.preventDefault()}},m.removeEvent=y.removeEventListener?function(a,b,c){a.removeEventListener&&a.removeEventListener(b,c,!1)}:function(a,b,c){var d="on"+b;a.detachEvent&&(typeof a[d]===K&&(a[d]=null),a.detachEvent(d,c))},m.Event=function(a,b){return this instanceof m.Event?(a&&a.type?(this.originalEvent=a,this.type=a.type,this.isDefaultPrevented=a.defaultPrevented||void 0===a.defaultPrevented&&a.returnValue===!1?ab:bb):this.type=a,b&&m.extend(this,b),this.timeStamp=a&&a.timeStamp||m.now(),void(this[m.expando]=!0)):new m.Event(a,b)},m.Event.prototype={isDefaultPrevented:bb,isPropagationStopped:bb,isImmediatePropagationStopped:bb,preventDefault:function(){var a=this.originalEvent;this.isDefaultPrevented=ab,a&&(a.preventDefault?a.preventDefault():a.returnValue=!1)},stopPropagation:function(){var a=this.originalEvent;this.isPropagationStopped=ab,a&&(a.stopPropagation&&a.stopPropagation(),a.cancelBubble=!0)},stopImmediatePropagation:function(){var a=this.originalEvent;this.isImmediatePropagationStopped=ab,a&&a.stopImmediatePropagation&&a.stopImmediatePropagation(),this.stopPropagation()}},m.each({mouseenter:"mouseover",mouseleave:"mouseout",pointerenter:"pointerover",pointerleave:"pointerout"},function(a,b){m.event.special[a]={delegateType:b,bindType:b,handle:function(a){var c,d=this,e=a.relatedTarget,f=a.handleObj;return(!e||e!==d&&!m.contains(d,e))&&(a.type=f.origType,c=f.handler.apply(this,arguments),a.type=b),c}}}),k.submitBubbles||(m.event.special.submit={setup:function(){return m.nodeName(this,"form")?!1:void m.event.add(this,"click._submit keypress._submit",function(a){var b=a.target,c=m.nodeName(b,"input")||m.nodeName(b,"button")?b.form:void 0;c&&!m._data(c,"submitBubbles")&&(m.event.add(c,"submit._submit",function(a){a._submit_bubble=!0}),m._data(c,"submitBubbles",!0))})},postDispatch:function(a){a._submit_bubble&&(delete a._submit_bubble,this.parentNode&&!a.isTrigger&&m.event.simulate("submit",this.parentNode,a,!0))},teardown:function(){return m.nodeName(this,"form")?!1:void m.event.remove(this,"._submit")}}),k.changeBubbles||(m.event.special.change={setup:function(){return X.test(this.nodeName)?(("checkbox"===this.type||"radio"===this.type)&&(m.event.add(this,"propertychange._change",function(a){"checked"===a.originalEvent.propertyName&&(this._just_changed=!0)}),m.event.add(this,"click._change",function(a){this._just_changed&&!a.isTrigger&&(this._just_changed=!1),m.event.simulate("change",this,a,!0)})),!1):void m.event.add(this,"beforeactivate._change",function(a){var b=a.target;X.test(b.nodeName)&&!m._data(b,"changeBubbles")&&(m.event.add(b,"change._change",function(a){!this.parentNode||a.isSimulated||a.isTrigger||m.event.simulate("change",this.parentNode,a,!0)}),m._data(b,"changeBubbles",!0))})},handle:function(a){var b=a.target;return this!==b||a.isSimulated||a.isTrigger||"radio"!==b.type&&"checkbox"!==b.type?a.handleObj.handler.apply(this,arguments):void 0},teardown:function(){return m.event.remove(this,"._change"),!X.test(this.nodeName)}}),k.focusinBubbles||m.each({focus:"focusin",blur:"focusout"},function(a,b){var c=function(a){m.event.simulate(b,a.target,m.event.fix(a),!0)};m.event.special[b]={setup:function(){var d=this.ownerDocument||this,e=m._data(d,b);e||d.addEventListener(a,c,!0),m._data(d,b,(e||0)+1)},teardown:function(){var d=this.ownerDocument||this,e=m._data(d,b)-1;e?m._data(d,b,e):(d.removeEventListener(a,c,!0),m._removeData(d,b))}}}),m.fn.extend({on:function(a,b,c,d,e){var f,g;if("object"==typeof a){"string"!=typeof b&&(c=c||b,b=void 0);for(f in a)this.on(f,b,c,a[f],e);return this}if(null==c&&null==d?(d=b,c=b=void 0):null==d&&("string"==typeof b?(d=c,c=void 0):(d=c,c=b,b=void 0)),d===!1)d=bb;else if(!d)return this;return 1===e&&(g=d,d=function(a){return m().off(a),g.apply(this,arguments)},d.guid=g.guid||(g.guid=m.guid++)),this.each(function(){m.event.add(this,a,d,c,b)})},one:function(a,b,c,d){return this.on(a,b,c,d,1)},off:function(a,b,c){var d,e;if(a&&a.preventDefault&&a.handleObj)return d=a.handleObj,m(a.delegateTarget).off(d.namespace?d.origType+"."+d.namespace:d.origType,d.selector,d.handler),this;if("object"==typeof a){for(e in a)this.off(e,b,a[e]);return this}return(b===!1||"function"==typeof b)&&(c=b,b=void 0),c===!1&&(c=bb),this.each(function(){m.event.remove(this,a,c,b)})},trigger:function(a,b){return this.each(function(){m.event.trigger(a,b,this)})},triggerHandler:function(a,b){var c=this[0];return c?m.event.trigger(a,b,c,!0):void 0}});function db(a){var b=eb.split("|"),c=a.createDocumentFragment();if(c.createElement)while(b.length)c.createElement(b.pop());return c}var eb="abbr|article|aside|audio|bdi|canvas|data|datalist|details|figcaption|figure|footer|header|hgroup|mark|meter|nav|output|progress|section|summary|time|video",fb=/ jQuery\d+="(?:null|\d+)"/g,gb=new RegExp("<(?:"+eb+")[\\s/>]","i"),hb=/^\s+/,ib=/<(?!area|br|col|embed|hr|img|input|link|meta|param)(([\w:]+)[^>]*)\/>/gi,jb=/<([\w:]+)/,kb=/<tbody/i,lb=/<|&#?\w+;/,mb=/<(?:script|style|link)/i,nb=/checked\s*(?:[^=]|=\s*.checked.)/i,ob=/^$|\/(?:java|ecma)script/i,pb=/^true\/(.*)/,qb=/^\s*<!(?:\[CDATA\[|--)|(?:\]\]|--)>\s*$/g,rb={option:[1,"<select multiple='multiple'>","</select>"],legend:[1,"<fieldset>","</fieldset>"],area:[1,"<map>","</map>"],param:[1,"<object>","</object>"],thead:[1,"<table>","</table>"],tr:[2,"<table><tbody>","</tbody></table>"],col:[2,"<table><tbody></tbody><colgroup>","</colgroup></table>"],td:[3,"<table><tbody><tr>","</tr></tbody></table>"],_default:k.htmlSerialize?[0,"",""]:[1,"X<div>","</div>"]},sb=db(y),tb=sb.appendChild(y.createElement("div"));rb.optgroup=rb.option,rb.tbody=rb.tfoot=rb.colgroup=rb.caption=rb.thead,rb.th=rb.td;function ub(a,b){var c,d,e=0,f=typeof a.getElementsByTagName!==K?a.getElementsByTagName(b||"*"):typeof a.querySelectorAll!==K?a.querySelectorAll(b||"*"):void 0;if(!f)for(f=[],c=a.childNodes||a;null!=(d=c[e]);e++)!b||m.nodeName(d,b)?f.push(d):m.merge(f,ub(d,b));return void 0===b||b&&m.nodeName(a,b)?m.merge([a],f):f}function vb(a){W.test(a.type)&&(a.defaultChecked=a.checked)}function wb(a,b){return m.nodeName(a,"table")&&m.nodeName(11!==b.nodeType?b:b.firstChild,"tr")?a.getElementsByTagName("tbody")[0]||a.appendChild(a.ownerDocument.createElement("tbody")):a}function xb(a){return a.type=(null!==m.find.attr(a,"type"))+"/"+a.type,a}function yb(a){var b=pb.exec(a.type);return b?a.type=b[1]:a.removeAttribute("type"),a}function zb(a,b){for(var c,d=0;null!=(c=a[d]);d++)m._data(c,"globalEval",!b||m._data(b[d],"globalEval"))}function Ab(a,b){if(1===b.nodeType&&m.hasData(a)){var c,d,e,f=m._data(a),g=m._data(b,f),h=f.events;if(h){delete g.handle,g.events={};for(c in h)for(d=0,e=h[c].length;e>d;d++)m.event.add(b,c,h[c][d])}g.data&&(g.data=m.extend({},g.data))}}function Bb(a,b){var c,d,e;if(1===b.nodeType){if(c=b.nodeName.toLowerCase(),!k.noCloneEvent&&b[m.expando]){e=m._data(b);for(d in e.events)m.removeEvent(b,d,e.handle);b.removeAttribute(m.expando)}"script"===c&&b.text!==a.text?(xb(b).text=a.text,yb(b)):"object"===c?(b.parentNode&&(b.outerHTML=a.outerHTML),k.html5Clone&&a.innerHTML&&!m.trim(b.innerHTML)&&(b.innerHTML=a.innerHTML)):"input"===c&&W.test(a.type)?(b.defaultChecked=b.checked=a.checked,b.value!==a.value&&(b.value=a.value)):"option"===c?b.defaultSelected=b.selected=a.defaultSelected:("input"===c||"textarea"===c)&&(b.defaultValue=a.defaultValue)}}m.extend({clone:function(a,b,c){var d,e,f,g,h,i=m.contains(a.ownerDocument,a);if(k.html5Clone||m.isXMLDoc(a)||!gb.test("<"+a.nodeName+">")?f=a.cloneNode(!0):(tb.innerHTML=a.outerHTML,tb.removeChild(f=tb.firstChild)),!(k.noCloneEvent&&k.noCloneChecked||1!==a.nodeType&&11!==a.nodeType||m.isXMLDoc(a)))for(d=ub(f),h=ub(a),g=0;null!=(e=h[g]);++g)d[g]&&Bb(e,d[g]);if(b)if(c)for(h=h||ub(a),d=d||ub(f),g=0;null!=(e=h[g]);g++)Ab(e,d[g]);else Ab(a,f);return d=ub(f,"script"),d.length>0&&zb(d,!i&&ub(a,"script")),d=h=e=null,f},buildFragment:function(a,b,c,d){for(var e,f,g,h,i,j,l,n=a.length,o=db(b),p=[],q=0;n>q;q++)if(f=a[q],f||0===f)if("object"===m.type(f))m.merge(p,f.nodeType?[f]:f);else if(lb.test(f)){h=h||o.appendChild(b.createElement("div")),i=(jb.exec(f)||["",""])[1].toLowerCase(),l=rb[i]||rb._default,h.innerHTML=l[1]+f.replace(ib,"<$1></$2>")+l[2],e=l[0];while(e--)h=h.lastChild;if(!k.leadingWhitespace&&hb.test(f)&&p.push(b.createTextNode(hb.exec(f)[0])),!k.tbody){f="table"!==i||kb.test(f)?"<table>"!==l[1]||kb.test(f)?0:h:h.firstChild,e=f&&f.childNodes.length;while(e--)m.nodeName(j=f.childNodes[e],"tbody")&&!j.childNodes.length&&f.removeChild(j)}m.merge(p,h.childNodes),h.textContent="";while(h.firstChild)h.removeChild(h.firstChild);h=o.lastChild}else p.push(b.createTextNode(f));h&&o.removeChild(h),k.appendChecked||m.grep(ub(p,"input"),vb),q=0;while(f=p[q++])if((!d||-1===m.inArray(f,d))&&(g=m.contains(f.ownerDocument,f),h=ub(o.appendChild(f),"script"),g&&zb(h),c)){e=0;while(f=h[e++])ob.test(f.type||"")&&c.push(f)}return h=null,o},cleanData:function(a,b){for(var d,e,f,g,h=0,i=m.expando,j=m.cache,l=k.deleteExpando,n=m.event.special;null!=(d=a[h]);h++)if((b||m.acceptData(d))&&(f=d[i],g=f&&j[f])){if(g.events)for(e in g.events)n[e]?m.event.remove(d,e):m.removeEvent(d,e,g.handle);j[f]&&(delete j[f],l?delete d[i]:typeof d.removeAttribute!==K?d.removeAttribute(i):d[i]=null,c.push(f))}}}),m.fn.extend({text:function(a){return V(this,function(a){return void 0===a?m.text(this):this.empty().append((this[0]&&this[0].ownerDocument||y).createTextNode(a))},null,a,arguments.length)},append:function(){return this.domManip(arguments,function(a){if(1===this.nodeType||11===this.nodeType||9===this.nodeType){var b=wb(this,a);b.appendChild(a)}})},prepend:function(){return this.domManip(arguments,function(a){if(1===this.nodeType||11===this.nodeType||9===this.nodeType){var b=wb(this,a);b.insertBefore(a,b.firstChild)}})},before:function(){return this.domManip(arguments,function(a){this.parentNode&&this.parentNode.insertBefore(a,this)})},after:function(){return this.domManip(arguments,function(a){this.parentNode&&this.parentNode.insertBefore(a,this.nextSibling)})},remove:function(a,b){for(var c,d=a?m.filter(a,this):this,e=0;null!=(c=d[e]);e++)b||1!==c.nodeType||m.cleanData(ub(c)),c.parentNode&&(b&&m.contains(c.ownerDocument,c)&&zb(ub(c,"script")),c.parentNode.removeChild(c));return this},empty:function(){for(var a,b=0;null!=(a=this[b]);b++){1===a.nodeType&&m.cleanData(ub(a,!1));while(a.firstChild)a.removeChild(a.firstChild);a.options&&m.nodeName(a,"select")&&(a.options.length=0)}return this},clone:function(a,b){return a=null==a?!1:a,b=null==b?a:b,this.map(function(){return m.clone(this,a,b)})},html:function(a){return V(this,function(a){var b=this[0]||{},c=0,d=this.length;if(void 0===a)return 1===b.nodeType?b.innerHTML.replace(fb,""):void 0;if(!("string"!=typeof a||mb.test(a)||!k.htmlSerialize&&gb.test(a)||!k.leadingWhitespace&&hb.test(a)||rb[(jb.exec(a)||["",""])[1].toLowerCase()])){a=a.replace(ib,"<$1></$2>");try{for(;d>c;c++)b=this[c]||{},1===b.nodeType&&(m.cleanData(ub(b,!1)),b.innerHTML=a);b=0}catch(e){}}b&&this.empty().append(a)},null,a,arguments.length)},replaceWith:function(){var a=arguments[0];return this.domManip(arguments,function(b){a=this.parentNode,m.cleanData(ub(this)),a&&a.replaceChild(b,this)}),a&&(a.length||a.nodeType)?this:this.remove()},detach:function(a){return this.remove(a,!0)},domManip:function(a,b){a=e.apply([],a);var c,d,f,g,h,i,j=0,l=this.length,n=this,o=l-1,p=a[0],q=m.isFunction(p);if(q||l>1&&"string"==typeof p&&!k.checkClone&&nb.test(p))return this.each(function(c){var d=n.eq(c);q&&(a[0]=p.call(this,c,d.html())),d.domManip(a,b)});if(l&&(i=m.buildFragment(a,this[0].ownerDocument,!1,this),c=i.firstChild,1===i.childNodes.length&&(i=c),c)){for(g=m.map(ub(i,"script"),xb),f=g.length;l>j;j++)d=i,j!==o&&(d=m.clone(d,!0,!0),f&&m.merge(g,ub(d,"script"))),b.call(this[j],d,j);if(f)for(h=g[g.length-1].ownerDocument,m.map(g,yb),j=0;f>j;j++)d=g[j],ob.test(d.type||"")&&!m._data(d,"globalEval")&&m.contains(h,d)&&(d.src?m._evalUrl&&m._evalUrl(d.src):m.globalEval((d.text||d.textContent||d.innerHTML||"").replace(qb,"")));i=c=null}return this}}),m.each({appendTo:"append",prependTo:"prepend",insertBefore:"before",insertAfter:"after",replaceAll:"replaceWith"},function(a,b){m.fn[a]=function(a){for(var c,d=0,e=[],g=m(a),h=g.length-1;h>=d;d++)c=d===h?this:this.clone(!0),m(g[d])[b](c),f.apply(e,c.get());return this.pushStack(e)}});var Cb,Db={};function Eb(b,c){var d,e=m(c.createElement(b)).appendTo(c.body),f=a.getDefaultComputedStyle&&(d=a.getDefaultComputedStyle(e[0]))?d.display:m.css(e[0],"display");return e.detach(),f}function Fb(a){var b=y,c=Db[a];return c||(c=Eb(a,b),"none"!==c&&c||(Cb=(Cb||m("<iframe frameborder='0' width='0' height='0'/>")).appendTo(b.documentElement),b=(Cb[0].contentWindow||Cb[0].contentDocument).document,b.write(),b.close(),c=Eb(a,b),Cb.detach()),Db[a]=c),c}!function(){var a;k.shrinkWrapBlocks=function(){if(null!=a)return a;a=!1;var b,c,d;return c=y.getElementsByTagName("body")[0],c&&c.style?(b=y.createElement("div"),d=y.createElement("div"),d.style.cssText="position:absolute;border:0;width:0;height:0;top:0;left:-9999px",c.appendChild(d).appendChild(b),typeof b.style.zoom!==K&&(b.style.cssText="-webkit-box-sizing:content-box;-moz-box-sizing:content-box;box-sizing:content-box;display:block;margin:0;border:0;padding:1px;width:1px;zoom:1",b.appendChild(y.createElement("div")).style.width="5px",a=3!==b.offsetWidth),c.removeChild(d),a):void 0}}();var Gb=/^margin/,Hb=new RegExp("^("+S+")(?!px)[a-z%]+$","i"),Ib,Jb,Kb=/^(top|right|bottom|left)$/;a.getComputedStyle?(Ib=function(b){return b.ownerDocument.defaultView.opener?b.ownerDocument.defaultView.getComputedStyle(b,null):a.getComputedStyle(b,null)},Jb=function(a,b,c){var d,e,f,g,h=a.style;return c=c||Ib(a),g=c?c.getPropertyValue(b)||c[b]:void 0,c&&(""!==g||m.contains(a.ownerDocument,a)||(g=m.style(a,b)),Hb.test(g)&&Gb.test(b)&&(d=h.width,e=h.minWidth,f=h.maxWidth,h.minWidth=h.maxWidth=h.width=g,g=c.width,h.width=d,h.minWidth=e,h.maxWidth=f)),void 0===g?g:g+""}):y.documentElement.currentStyle&&(Ib=function(a){return a.currentStyle},Jb=function(a,b,c){var d,e,f,g,h=a.style;return c=c||Ib(a),g=c?c[b]:void 0,null==g&&h&&h[b]&&(g=h[b]),Hb.test(g)&&!Kb.test(b)&&(d=h.left,e=a.runtimeStyle,f=e&&e.left,f&&(e.left=a.currentStyle.left),h.left="fontSize"===b?"1em":g,g=h.pixelLeft+"px",h.left=d,f&&(e.left=f)),void 0===g?g:g+""||"auto"});function Lb(a,b){return{get:function(){var c=a();if(null!=c)return c?void delete this.get:(this.get=b).apply(this,arguments)}}}!function(){var b,c,d,e,f,g,h;if(b=y.createElement("div"),b.innerHTML="  <link/><table></table><a href='/a'>a</a><input type='checkbox'/>",d=b.getElementsByTagName("a")[0],c=d&&d.style){c.cssText="float:left;opacity:.5",k.opacity="0.5"===c.opacity,k.cssFloat=!!c.cssFloat,b.style.backgroundClip="content-box",b.cloneNode(!0).style.backgroundClip="",k.clearCloneStyle="content-box"===b.style.backgroundClip,k.boxSizing=""===c.boxSizing||""===c.MozBoxSizing||""===c.WebkitBoxSizing,m.extend(k,{reliableHiddenOffsets:function(){return null==g&&i(),g},boxSizingReliable:function(){return null==f&&i(),f},pixelPosition:function(){return null==e&&i(),e},reliableMarginRight:function(){return null==h&&i(),h}});function i(){var b,c,d,i;c=y.getElementsByTagName("body")[0],c&&c.style&&(b=y.createElement("div"),d=y.createElement("div"),d.style.cssText="position:absolute;border:0;width:0;height:0;top:0;left:-9999px",c.appendChild(d).appendChild(b),b.style.cssText="-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box;display:block;margin-top:1%;top:1%;border:1px;padding:1px;width:4px;position:absolute",e=f=!1,h=!0,a.getComputedStyle&&(e="1%"!==(a.getComputedStyle(b,null)||{}).top,f="4px"===(a.getComputedStyle(b,null)||{width:"4px"}).width,i=b.appendChild(y.createElement("div")),i.style.cssText=b.style.cssText="-webkit-box-sizing:content-box;-moz-box-sizing:content-box;box-sizing:content-box;display:block;margin:0;border:0;padding:0",i.style.marginRight=i.style.width="0",b.style.width="1px",h=!parseFloat((a.getComputedStyle(i,null)||{}).marginRight),b.removeChild(i)),b.innerHTML="<table><tr><td></td><td>t</td></tr></table>",i=b.getElementsByTagName("td"),i[0].style.cssText="margin:0;border:0;padding:0;display:none",g=0===i[0].offsetHeight,g&&(i[0].style.display="",i[1].style.display="none",g=0===i[0].offsetHeight),c.removeChild(d))}}}(),m.swap=function(a,b,c,d){var e,f,g={};for(f in b)g[f]=a.style[f],a.style[f]=b[f];e=c.apply(a,d||[]);for(f in b)a.style[f]=g[f];return e};var Mb=/alpha\([^)]*\)/i,Nb=/opacity\s*=\s*([^)]*)/,Ob=/^(none|table(?!-c[ea]).+)/,Pb=new RegExp("^("+S+")(.*)$","i"),Qb=new RegExp("^([+-])=("+S+")","i"),Rb={position:"absolute",visibility:"hidden",display:"block"},Sb={letterSpacing:"0",fontWeight:"400"},Tb=["Webkit","O","Moz","ms"];function Ub(a,b){if(b in a)return b;var c=b.charAt(0).toUpperCase()+b.slice(1),d=b,e=Tb.length;while(e--)if(b=Tb[e]+c,b in a)return b;return d}function Vb(a,b){for(var c,d,e,f=[],g=0,h=a.length;h>g;g++)d=a[g],d.style&&(f[g]=m._data(d,"olddisplay"),c=d.style.display,b?(f[g]||"none"!==c||(d.style.display=""),""===d.style.display&&U(d)&&(f[g]=m._data(d,"olddisplay",Fb(d.nodeName)))):(e=U(d),(c&&"none"!==c||!e)&&m._data(d,"olddisplay",e?c:m.css(d,"display"))));for(g=0;h>g;g++)d=a[g],d.style&&(b&&"none"!==d.style.display&&""!==d.style.display||(d.style.display=b?f[g]||"":"none"));return a}function Wb(a,b,c){var d=Pb.exec(b);return d?Math.max(0,d[1]-(c||0))+(d[2]||"px"):b}function Xb(a,b,c,d,e){for(var f=c===(d?"border":"content")?4:"width"===b?1:0,g=0;4>f;f+=2)"margin"===c&&(g+=m.css(a,c+T[f],!0,e)),d?("content"===c&&(g-=m.css(a,"padding"+T[f],!0,e)),"margin"!==c&&(g-=m.css(a,"border"+T[f]+"Width",!0,e))):(g+=m.css(a,"padding"+T[f],!0,e),"padding"!==c&&(g+=m.css(a,"border"+T[f]+"Width",!0,e)));return g}function Yb(a,b,c){var d=!0,e="width"===b?a.offsetWidth:a.offsetHeight,f=Ib(a),g=k.boxSizing&&"border-box"===m.css(a,"boxSizing",!1,f);if(0>=e||null==e){if(e=Jb(a,b,f),(0>e||null==e)&&(e=a.style[b]),Hb.test(e))return e;d=g&&(k.boxSizingReliable()||e===a.style[b]),e=parseFloat(e)||0}return e+Xb(a,b,c||(g?"border":"content"),d,f)+"px"}m.extend({cssHooks:{opacity:{get:function(a,b){if(b){var c=Jb(a,"opacity");return""===c?"1":c}}}},cssNumber:{columnCount:!0,fillOpacity:!0,flexGrow:!0,flexShrink:!0,fontWeight:!0,lineHeight:!0,opacity:!0,order:!0,orphans:!0,widows:!0,zIndex:!0,zoom:!0},cssProps:{"float":k.cssFloat?"cssFloat":"styleFloat"},style:function(a,b,c,d){if(a&&3!==a.nodeType&&8!==a.nodeType&&a.style){var e,f,g,h=m.camelCase(b),i=a.style;if(b=m.cssProps[h]||(m.cssProps[h]=Ub(i,h)),g=m.cssHooks[b]||m.cssHooks[h],void 0===c)return g&&"get"in g&&void 0!==(e=g.get(a,!1,d))?e:i[b];if(f=typeof c,"string"===f&&(e=Qb.exec(c))&&(c=(e[1]+1)*e[2]+parseFloat(m.css(a,b)),f="number"),null!=c&&c===c&&("number"!==f||m.cssNumber[h]||(c+="px"),k.clearCloneStyle||""!==c||0!==b.indexOf("background")||(i[b]="inherit"),!(g&&"set"in g&&void 0===(c=g.set(a,c,d)))))try{i[b]=c}catch(j){}}},css:function(a,b,c,d){var e,f,g,h=m.camelCase(b);return b=m.cssProps[h]||(m.cssProps[h]=Ub(a.style,h)),g=m.cssHooks[b]||m.cssHooks[h],g&&"get"in g&&(f=g.get(a,!0,c)),void 0===f&&(f=Jb(a,b,d)),"normal"===f&&b in Sb&&(f=Sb[b]),""===c||c?(e=parseFloat(f),c===!0||m.isNumeric(e)?e||0:f):f}}),m.each(["height","width"],function(a,b){m.cssHooks[b]={get:function(a,c,d){return c?Ob.test(m.css(a,"display"))&&0===a.offsetWidth?m.swap(a,Rb,function(){return Yb(a,b,d)}):Yb(a,b,d):void 0},set:function(a,c,d){var e=d&&Ib(a);return Wb(a,c,d?Xb(a,b,d,k.boxSizing&&"border-box"===m.css(a,"boxSizing",!1,e),e):0)}}}),k.opacity||(m.cssHooks.opacity={get:function(a,b){return Nb.test((b&&a.currentStyle?a.currentStyle.filter:a.style.filter)||"")?.01*parseFloat(RegExp.$1)+"":b?"1":""},set:function(a,b){var c=a.style,d=a.currentStyle,e=m.isNumeric(b)?"alpha(opacity="+100*b+")":"",f=d&&d.filter||c.filter||"";c.zoom=1,(b>=1||""===b)&&""===m.trim(f.replace(Mb,""))&&c.removeAttribute&&(c.removeAttribute("filter"),""===b||d&&!d.filter)||(c.filter=Mb.test(f)?f.replace(Mb,e):f+" "+e)}}),m.cssHooks.marginRight=Lb(k.reliableMarginRight,function(a,b){return b?m.swap(a,{display:"inline-block"},Jb,[a,"marginRight"]):void 0}),m.each({margin:"",padding:"",border:"Width"},function(a,b){m.cssHooks[a+b]={expand:function(c){for(var d=0,e={},f="string"==typeof c?c.split(" "):[c];4>d;d++)e[a+T[d]+b]=f[d]||f[d-2]||f[0];return e}},Gb.test(a)||(m.cssHooks[a+b].set=Wb)}),m.fn.extend({css:function(a,b){return V(this,function(a,b,c){var d,e,f={},g=0;if(m.isArray(b)){for(d=Ib(a),e=b.length;e>g;g++)f[b[g]]=m.css(a,b[g],!1,d);return f}return void 0!==c?m.style(a,b,c):m.css(a,b)},a,b,arguments.length>1)},show:function(){return Vb(this,!0)},hide:function(){return Vb(this)},toggle:function(a){return"boolean"==typeof a?a?this.show():this.hide():this.each(function(){U(this)?m(this).show():m(this).hide()})}});function Zb(a,b,c,d,e){return new Zb.prototype.init(a,b,c,d,e)
}m.Tween=Zb,Zb.prototype={constructor:Zb,init:function(a,b,c,d,e,f){this.elem=a,this.prop=c,this.easing=e||"swing",this.options=b,this.start=this.now=this.cur(),this.end=d,this.unit=f||(m.cssNumber[c]?"":"px")},cur:function(){var a=Zb.propHooks[this.prop];return a&&a.get?a.get(this):Zb.propHooks._default.get(this)},run:function(a){var b,c=Zb.propHooks[this.prop];return this.pos=b=this.options.duration?m.easing[this.easing](a,this.options.duration*a,0,1,this.options.duration):a,this.now=(this.end-this.start)*b+this.start,this.options.step&&this.options.step.call(this.elem,this.now,this),c&&c.set?c.set(this):Zb.propHooks._default.set(this),this}},Zb.prototype.init.prototype=Zb.prototype,Zb.propHooks={_default:{get:function(a){var b;return null==a.elem[a.prop]||a.elem.style&&null!=a.elem.style[a.prop]?(b=m.css(a.elem,a.prop,""),b&&"auto"!==b?b:0):a.elem[a.prop]},set:function(a){m.fx.step[a.prop]?m.fx.step[a.prop](a):a.elem.style&&(null!=a.elem.style[m.cssProps[a.prop]]||m.cssHooks[a.prop])?m.style(a.elem,a.prop,a.now+a.unit):a.elem[a.prop]=a.now}}},Zb.propHooks.scrollTop=Zb.propHooks.scrollLeft={set:function(a){a.elem.nodeType&&a.elem.parentNode&&(a.elem[a.prop]=a.now)}},m.easing={linear:function(a){return a},swing:function(a){return.5-Math.cos(a*Math.PI)/2}},m.fx=Zb.prototype.init,m.fx.step={};var $b,_b,ac=/^(?:toggle|show|hide)$/,bc=new RegExp("^(?:([+-])=|)("+S+")([a-z%]*)$","i"),cc=/queueHooks$/,dc=[ic],ec={"*":[function(a,b){var c=this.createTween(a,b),d=c.cur(),e=bc.exec(b),f=e&&e[3]||(m.cssNumber[a]?"":"px"),g=(m.cssNumber[a]||"px"!==f&&+d)&&bc.exec(m.css(c.elem,a)),h=1,i=20;if(g&&g[3]!==f){f=f||g[3],e=e||[],g=+d||1;do h=h||".5",g/=h,m.style(c.elem,a,g+f);while(h!==(h=c.cur()/d)&&1!==h&&--i)}return e&&(g=c.start=+g||+d||0,c.unit=f,c.end=e[1]?g+(e[1]+1)*e[2]:+e[2]),c}]};function fc(){return setTimeout(function(){$b=void 0}),$b=m.now()}function gc(a,b){var c,d={height:a},e=0;for(b=b?1:0;4>e;e+=2-b)c=T[e],d["margin"+c]=d["padding"+c]=a;return b&&(d.opacity=d.width=a),d}function hc(a,b,c){for(var d,e=(ec[b]||[]).concat(ec["*"]),f=0,g=e.length;g>f;f++)if(d=e[f].call(c,b,a))return d}function ic(a,b,c){var d,e,f,g,h,i,j,l,n=this,o={},p=a.style,q=a.nodeType&&U(a),r=m._data(a,"fxshow");c.queue||(h=m._queueHooks(a,"fx"),null==h.unqueued&&(h.unqueued=0,i=h.empty.fire,h.empty.fire=function(){h.unqueued||i()}),h.unqueued++,n.always(function(){n.always(function(){h.unqueued--,m.queue(a,"fx").length||h.empty.fire()})})),1===a.nodeType&&("height"in b||"width"in b)&&(c.overflow=[p.overflow,p.overflowX,p.overflowY],j=m.css(a,"display"),l="none"===j?m._data(a,"olddisplay")||Fb(a.nodeName):j,"inline"===l&&"none"===m.css(a,"float")&&(k.inlineBlockNeedsLayout&&"inline"!==Fb(a.nodeName)?p.zoom=1:p.display="inline-block")),c.overflow&&(p.overflow="hidden",k.shrinkWrapBlocks()||n.always(function(){p.overflow=c.overflow[0],p.overflowX=c.overflow[1],p.overflowY=c.overflow[2]}));for(d in b)if(e=b[d],ac.exec(e)){if(delete b[d],f=f||"toggle"===e,e===(q?"hide":"show")){if("show"!==e||!r||void 0===r[d])continue;q=!0}o[d]=r&&r[d]||m.style(a,d)}else j=void 0;if(m.isEmptyObject(o))"inline"===("none"===j?Fb(a.nodeName):j)&&(p.display=j);else{r?"hidden"in r&&(q=r.hidden):r=m._data(a,"fxshow",{}),f&&(r.hidden=!q),q?m(a).show():n.done(function(){m(a).hide()}),n.done(function(){var b;m._removeData(a,"fxshow");for(b in o)m.style(a,b,o[b])});for(d in o)g=hc(q?r[d]:0,d,n),d in r||(r[d]=g.start,q&&(g.end=g.start,g.start="width"===d||"height"===d?1:0))}}function jc(a,b){var c,d,e,f,g;for(c in a)if(d=m.camelCase(c),e=b[d],f=a[c],m.isArray(f)&&(e=f[1],f=a[c]=f[0]),c!==d&&(a[d]=f,delete a[c]),g=m.cssHooks[d],g&&"expand"in g){f=g.expand(f),delete a[d];for(c in f)c in a||(a[c]=f[c],b[c]=e)}else b[d]=e}function kc(a,b,c){var d,e,f=0,g=dc.length,h=m.Deferred().always(function(){delete i.elem}),i=function(){if(e)return!1;for(var b=$b||fc(),c=Math.max(0,j.startTime+j.duration-b),d=c/j.duration||0,f=1-d,g=0,i=j.tweens.length;i>g;g++)j.tweens[g].run(f);return h.notifyWith(a,[j,f,c]),1>f&&i?c:(h.resolveWith(a,[j]),!1)},j=h.promise({elem:a,props:m.extend({},b),opts:m.extend(!0,{specialEasing:{}},c),originalProperties:b,originalOptions:c,startTime:$b||fc(),duration:c.duration,tweens:[],createTween:function(b,c){var d=m.Tween(a,j.opts,b,c,j.opts.specialEasing[b]||j.opts.easing);return j.tweens.push(d),d},stop:function(b){var c=0,d=b?j.tweens.length:0;if(e)return this;for(e=!0;d>c;c++)j.tweens[c].run(1);return b?h.resolveWith(a,[j,b]):h.rejectWith(a,[j,b]),this}}),k=j.props;for(jc(k,j.opts.specialEasing);g>f;f++)if(d=dc[f].call(j,a,k,j.opts))return d;return m.map(k,hc,j),m.isFunction(j.opts.start)&&j.opts.start.call(a,j),m.fx.timer(m.extend(i,{elem:a,anim:j,queue:j.opts.queue})),j.progress(j.opts.progress).done(j.opts.done,j.opts.complete).fail(j.opts.fail).always(j.opts.always)}m.Animation=m.extend(kc,{tweener:function(a,b){m.isFunction(a)?(b=a,a=["*"]):a=a.split(" ");for(var c,d=0,e=a.length;e>d;d++)c=a[d],ec[c]=ec[c]||[],ec[c].unshift(b)},prefilter:function(a,b){b?dc.unshift(a):dc.push(a)}}),m.speed=function(a,b,c){var d=a&&"object"==typeof a?m.extend({},a):{complete:c||!c&&b||m.isFunction(a)&&a,duration:a,easing:c&&b||b&&!m.isFunction(b)&&b};return d.duration=m.fx.off?0:"number"==typeof d.duration?d.duration:d.duration in m.fx.speeds?m.fx.speeds[d.duration]:m.fx.speeds._default,(null==d.queue||d.queue===!0)&&(d.queue="fx"),d.old=d.complete,d.complete=function(){m.isFunction(d.old)&&d.old.call(this),d.queue&&m.dequeue(this,d.queue)},d},m.fn.extend({fadeTo:function(a,b,c,d){return this.filter(U).css("opacity",0).show().end().animate({opacity:b},a,c,d)},animate:function(a,b,c,d){var e=m.isEmptyObject(a),f=m.speed(b,c,d),g=function(){var b=kc(this,m.extend({},a),f);(e||m._data(this,"finish"))&&b.stop(!0)};return g.finish=g,e||f.queue===!1?this.each(g):this.queue(f.queue,g)},stop:function(a,b,c){var d=function(a){var b=a.stop;delete a.stop,b(c)};return"string"!=typeof a&&(c=b,b=a,a=void 0),b&&a!==!1&&this.queue(a||"fx",[]),this.each(function(){var b=!0,e=null!=a&&a+"queueHooks",f=m.timers,g=m._data(this);if(e)g[e]&&g[e].stop&&d(g[e]);else for(e in g)g[e]&&g[e].stop&&cc.test(e)&&d(g[e]);for(e=f.length;e--;)f[e].elem!==this||null!=a&&f[e].queue!==a||(f[e].anim.stop(c),b=!1,f.splice(e,1));(b||!c)&&m.dequeue(this,a)})},finish:function(a){return a!==!1&&(a=a||"fx"),this.each(function(){var b,c=m._data(this),d=c[a+"queue"],e=c[a+"queueHooks"],f=m.timers,g=d?d.length:0;for(c.finish=!0,m.queue(this,a,[]),e&&e.stop&&e.stop.call(this,!0),b=f.length;b--;)f[b].elem===this&&f[b].queue===a&&(f[b].anim.stop(!0),f.splice(b,1));for(b=0;g>b;b++)d[b]&&d[b].finish&&d[b].finish.call(this);delete c.finish})}}),m.each(["toggle","show","hide"],function(a,b){var c=m.fn[b];m.fn[b]=function(a,d,e){return null==a||"boolean"==typeof a?c.apply(this,arguments):this.animate(gc(b,!0),a,d,e)}}),m.each({slideDown:gc("show"),slideUp:gc("hide"),slideToggle:gc("toggle"),fadeIn:{opacity:"show"},fadeOut:{opacity:"hide"},fadeToggle:{opacity:"toggle"}},function(a,b){m.fn[a]=function(a,c,d){return this.animate(b,a,c,d)}}),m.timers=[],m.fx.tick=function(){var a,b=m.timers,c=0;for($b=m.now();c<b.length;c++)a=b[c],a()||b[c]!==a||b.splice(c--,1);b.length||m.fx.stop(),$b=void 0},m.fx.timer=function(a){m.timers.push(a),a()?m.fx.start():m.timers.pop()},m.fx.interval=13,m.fx.start=function(){_b||(_b=setInterval(m.fx.tick,m.fx.interval))},m.fx.stop=function(){clearInterval(_b),_b=null},m.fx.speeds={slow:600,fast:200,_default:400},m.fn.delay=function(a,b){return a=m.fx?m.fx.speeds[a]||a:a,b=b||"fx",this.queue(b,function(b,c){var d=setTimeout(b,a);c.stop=function(){clearTimeout(d)}})},function(){var a,b,c,d,e;b=y.createElement("div"),b.setAttribute("className","t"),b.innerHTML="  <link/><table></table><a href='/a'>a</a><input type='checkbox'/>",d=b.getElementsByTagName("a")[0],c=y.createElement("select"),e=c.appendChild(y.createElement("option")),a=b.getElementsByTagName("input")[0],d.style.cssText="top:1px",k.getSetAttribute="t"!==b.className,k.style=/top/.test(d.getAttribute("style")),k.hrefNormalized="/a"===d.getAttribute("href"),k.checkOn=!!a.value,k.optSelected=e.selected,k.enctype=!!y.createElement("form").enctype,c.disabled=!0,k.optDisabled=!e.disabled,a=y.createElement("input"),a.setAttribute("value",""),k.input=""===a.getAttribute("value"),a.value="t",a.setAttribute("type","radio"),k.radioValue="t"===a.value}();var lc=/\r/g;m.fn.extend({val:function(a){var b,c,d,e=this[0];{if(arguments.length)return d=m.isFunction(a),this.each(function(c){var e;1===this.nodeType&&(e=d?a.call(this,c,m(this).val()):a,null==e?e="":"number"==typeof e?e+="":m.isArray(e)&&(e=m.map(e,function(a){return null==a?"":a+""})),b=m.valHooks[this.type]||m.valHooks[this.nodeName.toLowerCase()],b&&"set"in b&&void 0!==b.set(this,e,"value")||(this.value=e))});if(e)return b=m.valHooks[e.type]||m.valHooks[e.nodeName.toLowerCase()],b&&"get"in b&&void 0!==(c=b.get(e,"value"))?c:(c=e.value,"string"==typeof c?c.replace(lc,""):null==c?"":c)}}}),m.extend({valHooks:{option:{get:function(a){var b=m.find.attr(a,"value");return null!=b?b:m.trim(m.text(a))}},select:{get:function(a){for(var b,c,d=a.options,e=a.selectedIndex,f="select-one"===a.type||0>e,g=f?null:[],h=f?e+1:d.length,i=0>e?h:f?e:0;h>i;i++)if(c=d[i],!(!c.selected&&i!==e||(k.optDisabled?c.disabled:null!==c.getAttribute("disabled"))||c.parentNode.disabled&&m.nodeName(c.parentNode,"optgroup"))){if(b=m(c).val(),f)return b;g.push(b)}return g},set:function(a,b){var c,d,e=a.options,f=m.makeArray(b),g=e.length;while(g--)if(d=e[g],m.inArray(m.valHooks.option.get(d),f)>=0)try{d.selected=c=!0}catch(h){d.scrollHeight}else d.selected=!1;return c||(a.selectedIndex=-1),e}}}}),m.each(["radio","checkbox"],function(){m.valHooks[this]={set:function(a,b){return m.isArray(b)?a.checked=m.inArray(m(a).val(),b)>=0:void 0}},k.checkOn||(m.valHooks[this].get=function(a){return null===a.getAttribute("value")?"on":a.value})});var mc,nc,oc=m.expr.attrHandle,pc=/^(?:checked|selected)$/i,qc=k.getSetAttribute,rc=k.input;m.fn.extend({attr:function(a,b){return V(this,m.attr,a,b,arguments.length>1)},removeAttr:function(a){return this.each(function(){m.removeAttr(this,a)})}}),m.extend({attr:function(a,b,c){var d,e,f=a.nodeType;if(a&&3!==f&&8!==f&&2!==f)return typeof a.getAttribute===K?m.prop(a,b,c):(1===f&&m.isXMLDoc(a)||(b=b.toLowerCase(),d=m.attrHooks[b]||(m.expr.match.bool.test(b)?nc:mc)),void 0===c?d&&"get"in d&&null!==(e=d.get(a,b))?e:(e=m.find.attr(a,b),null==e?void 0:e):null!==c?d&&"set"in d&&void 0!==(e=d.set(a,c,b))?e:(a.setAttribute(b,c+""),c):void m.removeAttr(a,b))},removeAttr:function(a,b){var c,d,e=0,f=b&&b.match(E);if(f&&1===a.nodeType)while(c=f[e++])d=m.propFix[c]||c,m.expr.match.bool.test(c)?rc&&qc||!pc.test(c)?a[d]=!1:a[m.camelCase("default-"+c)]=a[d]=!1:m.attr(a,c,""),a.removeAttribute(qc?c:d)},attrHooks:{type:{set:function(a,b){if(!k.radioValue&&"radio"===b&&m.nodeName(a,"input")){var c=a.value;return a.setAttribute("type",b),c&&(a.value=c),b}}}}}),nc={set:function(a,b,c){return b===!1?m.removeAttr(a,c):rc&&qc||!pc.test(c)?a.setAttribute(!qc&&m.propFix[c]||c,c):a[m.camelCase("default-"+c)]=a[c]=!0,c}},m.each(m.expr.match.bool.source.match(/\w+/g),function(a,b){var c=oc[b]||m.find.attr;oc[b]=rc&&qc||!pc.test(b)?function(a,b,d){var e,f;return d||(f=oc[b],oc[b]=e,e=null!=c(a,b,d)?b.toLowerCase():null,oc[b]=f),e}:function(a,b,c){return c?void 0:a[m.camelCase("default-"+b)]?b.toLowerCase():null}}),rc&&qc||(m.attrHooks.value={set:function(a,b,c){return m.nodeName(a,"input")?void(a.defaultValue=b):mc&&mc.set(a,b,c)}}),qc||(mc={set:function(a,b,c){var d=a.getAttributeNode(c);return d||a.setAttributeNode(d=a.ownerDocument.createAttribute(c)),d.value=b+="","value"===c||b===a.getAttribute(c)?b:void 0}},oc.id=oc.name=oc.coords=function(a,b,c){var d;return c?void 0:(d=a.getAttributeNode(b))&&""!==d.value?d.value:null},m.valHooks.button={get:function(a,b){var c=a.getAttributeNode(b);return c&&c.specified?c.value:void 0},set:mc.set},m.attrHooks.contenteditable={set:function(a,b,c){mc.set(a,""===b?!1:b,c)}},m.each(["width","height"],function(a,b){m.attrHooks[b]={set:function(a,c){return""===c?(a.setAttribute(b,"auto"),c):void 0}}})),k.style||(m.attrHooks.style={get:function(a){return a.style.cssText||void 0},set:function(a,b){return a.style.cssText=b+""}});var sc=/^(?:input|select|textarea|button|object)$/i,tc=/^(?:a|area)$/i;m.fn.extend({prop:function(a,b){return V(this,m.prop,a,b,arguments.length>1)},removeProp:function(a){return a=m.propFix[a]||a,this.each(function(){try{this[a]=void 0,delete this[a]}catch(b){}})}}),m.extend({propFix:{"for":"htmlFor","class":"className"},prop:function(a,b,c){var d,e,f,g=a.nodeType;if(a&&3!==g&&8!==g&&2!==g)return f=1!==g||!m.isXMLDoc(a),f&&(b=m.propFix[b]||b,e=m.propHooks[b]),void 0!==c?e&&"set"in e&&void 0!==(d=e.set(a,c,b))?d:a[b]=c:e&&"get"in e&&null!==(d=e.get(a,b))?d:a[b]},propHooks:{tabIndex:{get:function(a){var b=m.find.attr(a,"tabindex");return b?parseInt(b,10):sc.test(a.nodeName)||tc.test(a.nodeName)&&a.href?0:-1}}}}),k.hrefNormalized||m.each(["href","src"],function(a,b){m.propHooks[b]={get:function(a){return a.getAttribute(b,4)}}}),k.optSelected||(m.propHooks.selected={get:function(a){var b=a.parentNode;return b&&(b.selectedIndex,b.parentNode&&b.parentNode.selectedIndex),null}}),m.each(["tabIndex","readOnly","maxLength","cellSpacing","cellPadding","rowSpan","colSpan","useMap","frameBorder","contentEditable"],function(){m.propFix[this.toLowerCase()]=this}),k.enctype||(m.propFix.enctype="encoding");var uc=/[\t\r\n\f]/g;m.fn.extend({addClass:function(a){var b,c,d,e,f,g,h=0,i=this.length,j="string"==typeof a&&a;if(m.isFunction(a))return this.each(function(b){m(this).addClass(a.call(this,b,this.className))});if(j)for(b=(a||"").match(E)||[];i>h;h++)if(c=this[h],d=1===c.nodeType&&(c.className?(" "+c.className+" ").replace(uc," "):" ")){f=0;while(e=b[f++])d.indexOf(" "+e+" ")<0&&(d+=e+" ");g=m.trim(d),c.className!==g&&(c.className=g)}return this},removeClass:function(a){var b,c,d,e,f,g,h=0,i=this.length,j=0===arguments.length||"string"==typeof a&&a;if(m.isFunction(a))return this.each(function(b){m(this).removeClass(a.call(this,b,this.className))});if(j)for(b=(a||"").match(E)||[];i>h;h++)if(c=this[h],d=1===c.nodeType&&(c.className?(" "+c.className+" ").replace(uc," "):"")){f=0;while(e=b[f++])while(d.indexOf(" "+e+" ")>=0)d=d.replace(" "+e+" "," ");g=a?m.trim(d):"",c.className!==g&&(c.className=g)}return this},toggleClass:function(a,b){var c=typeof a;return"boolean"==typeof b&&"string"===c?b?this.addClass(a):this.removeClass(a):this.each(m.isFunction(a)?function(c){m(this).toggleClass(a.call(this,c,this.className,b),b)}:function(){if("string"===c){var b,d=0,e=m(this),f=a.match(E)||[];while(b=f[d++])e.hasClass(b)?e.removeClass(b):e.addClass(b)}else(c===K||"boolean"===c)&&(this.className&&m._data(this,"__className__",this.className),this.className=this.className||a===!1?"":m._data(this,"__className__")||"")})},hasClass:function(a){for(var b=" "+a+" ",c=0,d=this.length;d>c;c++)if(1===this[c].nodeType&&(" "+this[c].className+" ").replace(uc," ").indexOf(b)>=0)return!0;return!1}}),m.each("blur focus focusin focusout load resize scroll unload click dblclick mousedown mouseup mousemove mouseover mouseout mouseenter mouseleave change select submit keydown keypress keyup error contextmenu".split(" "),function(a,b){m.fn[b]=function(a,c){return arguments.length>0?this.on(b,null,a,c):this.trigger(b)}}),m.fn.extend({hover:function(a,b){return this.mouseenter(a).mouseleave(b||a)},bind:function(a,b,c){return this.on(a,null,b,c)},unbind:function(a,b){return this.off(a,null,b)},delegate:function(a,b,c,d){return this.on(b,a,c,d)},undelegate:function(a,b,c){return 1===arguments.length?this.off(a,"**"):this.off(b,a||"**",c)}});var vc=m.now(),wc=/\?/,xc=/(,)|(\[|{)|(}|])|"(?:[^"\\\r\n]|\\["\\\/bfnrt]|\\u[\da-fA-F]{4})*"\s*:?|true|false|null|-?(?!0\d)\d+(?:\.\d+|)(?:[eE][+-]?\d+|)/g;m.parseJSON=function(b){if(a.JSON&&a.JSON.parse)return a.JSON.parse(b+"");var c,d=null,e=m.trim(b+"");return e&&!m.trim(e.replace(xc,function(a,b,e,f){return c&&b&&(d=0),0===d?a:(c=e||b,d+=!f-!e,"")}))?Function("return "+e)():m.error("Invalid JSON: "+b)},m.parseXML=function(b){var c,d;if(!b||"string"!=typeof b)return null;try{a.DOMParser?(d=new DOMParser,c=d.parseFromString(b,"text/xml")):(c=new ActiveXObject("Microsoft.XMLDOM"),c.async="false",c.loadXML(b))}catch(e){c=void 0}return c&&c.documentElement&&!c.getElementsByTagName("parsererror").length||m.error("Invalid XML: "+b),c};var yc,zc,Ac=/#.*$/,Bc=/([?&])_=[^&]*/,Cc=/^(.*?):[ \t]*([^\r\n]*)\r?$/gm,Dc=/^(?:about|app|app-storage|.+-extension|file|res|widget):$/,Ec=/^(?:GET|HEAD)$/,Fc=/^\/\//,Gc=/^([\w.+-]+:)(?:\/\/(?:[^\/?#]*@|)([^\/?#:]*)(?::(\d+)|)|)/,Hc={},Ic={},Jc="*/".concat("*");try{zc=location.href}catch(Kc){zc=y.createElement("a"),zc.href="",zc=zc.href}yc=Gc.exec(zc.toLowerCase())||[];function Lc(a){return function(b,c){"string"!=typeof b&&(c=b,b="*");var d,e=0,f=b.toLowerCase().match(E)||[];if(m.isFunction(c))while(d=f[e++])"+"===d.charAt(0)?(d=d.slice(1)||"*",(a[d]=a[d]||[]).unshift(c)):(a[d]=a[d]||[]).push(c)}}function Mc(a,b,c,d){var e={},f=a===Ic;function g(h){var i;return e[h]=!0,m.each(a[h]||[],function(a,h){var j=h(b,c,d);return"string"!=typeof j||f||e[j]?f?!(i=j):void 0:(b.dataTypes.unshift(j),g(j),!1)}),i}return g(b.dataTypes[0])||!e["*"]&&g("*")}function Nc(a,b){var c,d,e=m.ajaxSettings.flatOptions||{};for(d in b)void 0!==b[d]&&((e[d]?a:c||(c={}))[d]=b[d]);return c&&m.extend(!0,a,c),a}function Oc(a,b,c){var d,e,f,g,h=a.contents,i=a.dataTypes;while("*"===i[0])i.shift(),void 0===e&&(e=a.mimeType||b.getResponseHeader("Content-Type"));if(e)for(g in h)if(h[g]&&h[g].test(e)){i.unshift(g);break}if(i[0]in c)f=i[0];else{for(g in c){if(!i[0]||a.converters[g+" "+i[0]]){f=g;break}d||(d=g)}f=f||d}return f?(f!==i[0]&&i.unshift(f),c[f]):void 0}function Pc(a,b,c,d){var e,f,g,h,i,j={},k=a.dataTypes.slice();if(k[1])for(g in a.converters)j[g.toLowerCase()]=a.converters[g];f=k.shift();while(f)if(a.responseFields[f]&&(c[a.responseFields[f]]=b),!i&&d&&a.dataFilter&&(b=a.dataFilter(b,a.dataType)),i=f,f=k.shift())if("*"===f)f=i;else if("*"!==i&&i!==f){if(g=j[i+" "+f]||j["* "+f],!g)for(e in j)if(h=e.split(" "),h[1]===f&&(g=j[i+" "+h[0]]||j["* "+h[0]])){g===!0?g=j[e]:j[e]!==!0&&(f=h[0],k.unshift(h[1]));break}if(g!==!0)if(g&&a["throws"])b=g(b);else try{b=g(b)}catch(l){return{state:"parsererror",error:g?l:"No conversion from "+i+" to "+f}}}return{state:"success",data:b}}m.extend({active:0,lastModified:{},etag:{},ajaxSettings:{url:zc,type:"GET",isLocal:Dc.test(yc[1]),global:!0,processData:!0,async:!0,contentType:"application/x-www-form-urlencoded; charset=UTF-8",accepts:{"*":Jc,text:"text/plain",html:"text/html",xml:"application/xml, text/xml",json:"application/json, text/javascript"},contents:{xml:/xml/,html:/html/,json:/json/},responseFields:{xml:"responseXML",text:"responseText",json:"responseJSON"},converters:{"* text":String,"text html":!0,"text json":m.parseJSON,"text xml":m.parseXML},flatOptions:{url:!0,context:!0}},ajaxSetup:function(a,b){return b?Nc(Nc(a,m.ajaxSettings),b):Nc(m.ajaxSettings,a)},ajaxPrefilter:Lc(Hc),ajaxTransport:Lc(Ic),ajax:function(a,b){"object"==typeof a&&(b=a,a=void 0),b=b||{};var c,d,e,f,g,h,i,j,k=m.ajaxSetup({},b),l=k.context||k,n=k.context&&(l.nodeType||l.jquery)?m(l):m.event,o=m.Deferred(),p=m.Callbacks("once memory"),q=k.statusCode||{},r={},s={},t=0,u="canceled",v={readyState:0,getResponseHeader:function(a){var b;if(2===t){if(!j){j={};while(b=Cc.exec(f))j[b[1].toLowerCase()]=b[2]}b=j[a.toLowerCase()]}return null==b?null:b},getAllResponseHeaders:function(){return 2===t?f:null},setRequestHeader:function(a,b){var c=a.toLowerCase();return t||(a=s[c]=s[c]||a,r[a]=b),this},overrideMimeType:function(a){return t||(k.mimeType=a),this},statusCode:function(a){var b;if(a)if(2>t)for(b in a)q[b]=[q[b],a[b]];else v.always(a[v.status]);return this},abort:function(a){var b=a||u;return i&&i.abort(b),x(0,b),this}};if(o.promise(v).complete=p.add,v.success=v.done,v.error=v.fail,k.url=((a||k.url||zc)+"").replace(Ac,"").replace(Fc,yc[1]+"//"),k.type=b.method||b.type||k.method||k.type,k.dataTypes=m.trim(k.dataType||"*").toLowerCase().match(E)||[""],null==k.crossDomain&&(c=Gc.exec(k.url.toLowerCase()),k.crossDomain=!(!c||c[1]===yc[1]&&c[2]===yc[2]&&(c[3]||("http:"===c[1]?"80":"443"))===(yc[3]||("http:"===yc[1]?"80":"443")))),k.data&&k.processData&&"string"!=typeof k.data&&(k.data=m.param(k.data,k.traditional)),Mc(Hc,k,b,v),2===t)return v;h=m.event&&k.global,h&&0===m.active++&&m.event.trigger("ajaxStart"),k.type=k.type.toUpperCase(),k.hasContent=!Ec.test(k.type),e=k.url,k.hasContent||(k.data&&(e=k.url+=(wc.test(e)?"&":"?")+k.data,delete k.data),k.cache===!1&&(k.url=Bc.test(e)?e.replace(Bc,"$1_="+vc++):e+(wc.test(e)?"&":"?")+"_="+vc++)),k.ifModified&&(m.lastModified[e]&&v.setRequestHeader("If-Modified-Since",m.lastModified[e]),m.etag[e]&&v.setRequestHeader("If-None-Match",m.etag[e])),(k.data&&k.hasContent&&k.contentType!==!1||b.contentType)&&v.setRequestHeader("Content-Type",k.contentType),v.setRequestHeader("Accept",k.dataTypes[0]&&k.accepts[k.dataTypes[0]]?k.accepts[k.dataTypes[0]]+("*"!==k.dataTypes[0]?", "+Jc+"; q=0.01":""):k.accepts["*"]);for(d in k.headers)v.setRequestHeader(d,k.headers[d]);if(k.beforeSend&&(k.beforeSend.call(l,v,k)===!1||2===t))return v.abort();u="abort";for(d in{success:1,error:1,complete:1})v[d](k[d]);if(i=Mc(Ic,k,b,v)){v.readyState=1,h&&n.trigger("ajaxSend",[v,k]),k.async&&k.timeout>0&&(g=setTimeout(function(){v.abort("timeout")},k.timeout));try{t=1,i.send(r,x)}catch(w){if(!(2>t))throw w;x(-1,w)}}else x(-1,"No Transport");function x(a,b,c,d){var j,r,s,u,w,x=b;2!==t&&(t=2,g&&clearTimeout(g),i=void 0,f=d||"",v.readyState=a>0?4:0,j=a>=200&&300>a||304===a,c&&(u=Oc(k,v,c)),u=Pc(k,u,v,j),j?(k.ifModified&&(w=v.getResponseHeader("Last-Modified"),w&&(m.lastModified[e]=w),w=v.getResponseHeader("etag"),w&&(m.etag[e]=w)),204===a||"HEAD"===k.type?x="nocontent":304===a?x="notmodified":(x=u.state,r=u.data,s=u.error,j=!s)):(s=x,(a||!x)&&(x="error",0>a&&(a=0))),v.status=a,v.statusText=(b||x)+"",j?o.resolveWith(l,[r,x,v]):o.rejectWith(l,[v,x,s]),v.statusCode(q),q=void 0,h&&n.trigger(j?"ajaxSuccess":"ajaxError",[v,k,j?r:s]),p.fireWith(l,[v,x]),h&&(n.trigger("ajaxComplete",[v,k]),--m.active||m.event.trigger("ajaxStop")))}return v},getJSON:function(a,b,c){return m.get(a,b,c,"json")},getScript:function(a,b){return m.get(a,void 0,b,"script")}}),m.each(["get","post"],function(a,b){m[b]=function(a,c,d,e){return m.isFunction(c)&&(e=e||d,d=c,c=void 0),m.ajax({url:a,type:b,dataType:e,data:c,success:d})}}),m._evalUrl=function(a){return m.ajax({url:a,type:"GET",dataType:"script",async:!1,global:!1,"throws":!0})},m.fn.extend({wrapAll:function(a){if(m.isFunction(a))return this.each(function(b){m(this).wrapAll(a.call(this,b))});if(this[0]){var b=m(a,this[0].ownerDocument).eq(0).clone(!0);this[0].parentNode&&b.insertBefore(this[0]),b.map(function(){var a=this;while(a.firstChild&&1===a.firstChild.nodeType)a=a.firstChild;return a}).append(this)}return this},wrapInner:function(a){return this.each(m.isFunction(a)?function(b){m(this).wrapInner(a.call(this,b))}:function(){var b=m(this),c=b.contents();c.length?c.wrapAll(a):b.append(a)})},wrap:function(a){var b=m.isFunction(a);return this.each(function(c){m(this).wrapAll(b?a.call(this,c):a)})},unwrap:function(){return this.parent().each(function(){m.nodeName(this,"body")||m(this).replaceWith(this.childNodes)}).end()}}),m.expr.filters.hidden=function(a){return a.offsetWidth<=0&&a.offsetHeight<=0||!k.reliableHiddenOffsets()&&"none"===(a.style&&a.style.display||m.css(a,"display"))},m.expr.filters.visible=function(a){return!m.expr.filters.hidden(a)};var Qc=/%20/g,Rc=/\[\]$/,Sc=/\r?\n/g,Tc=/^(?:submit|button|image|reset|file)$/i,Uc=/^(?:input|select|textarea|keygen)/i;function Vc(a,b,c,d){var e;if(m.isArray(b))m.each(b,function(b,e){c||Rc.test(a)?d(a,e):Vc(a+"["+("object"==typeof e?b:"")+"]",e,c,d)});else if(c||"object"!==m.type(b))d(a,b);else for(e in b)Vc(a+"["+e+"]",b[e],c,d)}m.param=function(a,b){var c,d=[],e=function(a,b){b=m.isFunction(b)?b():null==b?"":b,d[d.length]=encodeURIComponent(a)+"="+encodeURIComponent(b)};if(void 0===b&&(b=m.ajaxSettings&&m.ajaxSettings.traditional),m.isArray(a)||a.jquery&&!m.isPlainObject(a))m.each(a,function(){e(this.name,this.value)});else for(c in a)Vc(c,a[c],b,e);return d.join("&").replace(Qc,"+")},m.fn.extend({serialize:function(){return m.param(this.serializeArray())},serializeArray:function(){return this.map(function(){var a=m.prop(this,"elements");return a?m.makeArray(a):this}).filter(function(){var a=this.type;return this.name&&!m(this).is(":disabled")&&Uc.test(this.nodeName)&&!Tc.test(a)&&(this.checked||!W.test(a))}).map(function(a,b){var c=m(this).val();return null==c?null:m.isArray(c)?m.map(c,function(a){return{name:b.name,value:a.replace(Sc,"\r\n")}}):{name:b.name,value:c.replace(Sc,"\r\n")}}).get()}}),m.ajaxSettings.xhr=void 0!==a.ActiveXObject?function(){return!this.isLocal&&/^(get|post|head|put|delete|options)$/i.test(this.type)&&Zc()||$c()}:Zc;var Wc=0,Xc={},Yc=m.ajaxSettings.xhr();a.attachEvent&&a.attachEvent("onunload",function(){for(var a in Xc)Xc[a](void 0,!0)}),k.cors=!!Yc&&"withCredentials"in Yc,Yc=k.ajax=!!Yc,Yc&&m.ajaxTransport(function(a){if(!a.crossDomain||k.cors){var b;return{send:function(c,d){var e,f=a.xhr(),g=++Wc;if(f.open(a.type,a.url,a.async,a.username,a.password),a.xhrFields)for(e in a.xhrFields)f[e]=a.xhrFields[e];a.mimeType&&f.overrideMimeType&&f.overrideMimeType(a.mimeType),a.crossDomain||c["X-Requested-With"]||(c["X-Requested-With"]="XMLHttpRequest");for(e in c)void 0!==c[e]&&f.setRequestHeader(e,c[e]+"");f.send(a.hasContent&&a.data||null),b=function(c,e){var h,i,j;if(b&&(e||4===f.readyState))if(delete Xc[g],b=void 0,f.onreadystatechange=m.noop,e)4!==f.readyState&&f.abort();else{j={},h=f.status,"string"==typeof f.responseText&&(j.text=f.responseText);try{i=f.statusText}catch(k){i=""}h||!a.isLocal||a.crossDomain?1223===h&&(h=204):h=j.text?200:404}j&&d(h,i,j,f.getAllResponseHeaders())},a.async?4===f.readyState?setTimeout(b):f.onreadystatechange=Xc[g]=b:b()},abort:function(){b&&b(void 0,!0)}}}});function Zc(){try{return new a.XMLHttpRequest}catch(b){}}function $c(){try{return new a.ActiveXObject("Microsoft.XMLHTTP")}catch(b){}}m.ajaxSetup({accepts:{script:"text/javascript, application/javascript, application/ecmascript, application/x-ecmascript"},contents:{script:/(?:java|ecma)script/},converters:{"text script":function(a){return m.globalEval(a),a}}}),m.ajaxPrefilter("script",function(a){void 0===a.cache&&(a.cache=!1),a.crossDomain&&(a.type="GET",a.global=!1)}),m.ajaxTransport("script",function(a){if(a.crossDomain){var b,c=y.head||m("head")[0]||y.documentElement;return{send:function(d,e){b=y.createElement("script"),b.async=!0,a.scriptCharset&&(b.charset=a.scriptCharset),b.src=a.url,b.onload=b.onreadystatechange=function(a,c){(c||!b.readyState||/loaded|complete/.test(b.readyState))&&(b.onload=b.onreadystatechange=null,b.parentNode&&b.parentNode.removeChild(b),b=null,c||e(200,"success"))},c.insertBefore(b,c.firstChild)},abort:function(){b&&b.onload(void 0,!0)}}}});var _c=[],ad=/(=)\?(?=&|$)|\?\?/;m.ajaxSetup({jsonp:"callback",jsonpCallback:function(){var a=_c.pop()||m.expando+"_"+vc++;return this[a]=!0,a}}),m.ajaxPrefilter("json jsonp",function(b,c,d){var e,f,g,h=b.jsonp!==!1&&(ad.test(b.url)?"url":"string"==typeof b.data&&!(b.contentType||"").indexOf("application/x-www-form-urlencoded")&&ad.test(b.data)&&"data");return h||"jsonp"===b.dataTypes[0]?(e=b.jsonpCallback=m.isFunction(b.jsonpCallback)?b.jsonpCallback():b.jsonpCallback,h?b[h]=b[h].replace(ad,"$1"+e):b.jsonp!==!1&&(b.url+=(wc.test(b.url)?"&":"?")+b.jsonp+"="+e),b.converters["script json"]=function(){return g||m.error(e+" was not called"),g[0]},b.dataTypes[0]="json",f=a[e],a[e]=function(){g=arguments},d.always(function(){a[e]=f,b[e]&&(b.jsonpCallback=c.jsonpCallback,_c.push(e)),g&&m.isFunction(f)&&f(g[0]),g=f=void 0}),"script"):void 0}),m.parseHTML=function(a,b,c){if(!a||"string"!=typeof a)return null;"boolean"==typeof b&&(c=b,b=!1),b=b||y;var d=u.exec(a),e=!c&&[];return d?[b.createElement(d[1])]:(d=m.buildFragment([a],b,e),e&&e.length&&m(e).remove(),m.merge([],d.childNodes))};var bd=m.fn.load;m.fn.load=function(a,b,c){if("string"!=typeof a&&bd)return bd.apply(this,arguments);var d,e,f,g=this,h=a.indexOf(" ");return h>=0&&(d=m.trim(a.slice(h,a.length)),a=a.slice(0,h)),m.isFunction(b)?(c=b,b=void 0):b&&"object"==typeof b&&(f="POST"),g.length>0&&m.ajax({url:a,type:f,dataType:"html",data:b}).done(function(a){e=arguments,g.html(d?m("<div>").append(m.parseHTML(a)).find(d):a)}).complete(c&&function(a,b){g.each(c,e||[a.responseText,b,a])}),this},m.each(["ajaxStart","ajaxStop","ajaxComplete","ajaxError","ajaxSuccess","ajaxSend"],function(a,b){m.fn[b]=function(a){return this.on(b,a)}}),m.expr.filters.animated=function(a){return m.grep(m.timers,function(b){return a===b.elem}).length};var cd=a.document.documentElement;function dd(a){return m.isWindow(a)?a:9===a.nodeType?a.defaultView||a.parentWindow:!1}m.offset={setOffset:function(a,b,c){var d,e,f,g,h,i,j,k=m.css(a,"position"),l=m(a),n={};"static"===k&&(a.style.position="relative"),h=l.offset(),f=m.css(a,"top"),i=m.css(a,"left"),j=("absolute"===k||"fixed"===k)&&m.inArray("auto",[f,i])>-1,j?(d=l.position(),g=d.top,e=d.left):(g=parseFloat(f)||0,e=parseFloat(i)||0),m.isFunction(b)&&(b=b.call(a,c,h)),null!=b.top&&(n.top=b.top-h.top+g),null!=b.left&&(n.left=b.left-h.left+e),"using"in b?b.using.call(a,n):l.css(n)}},m.fn.extend({offset:function(a){if(arguments.length)return void 0===a?this:this.each(function(b){m.offset.setOffset(this,a,b)});var b,c,d={top:0,left:0},e=this[0],f=e&&e.ownerDocument;if(f)return b=f.documentElement,m.contains(b,e)?(typeof e.getBoundingClientRect!==K&&(d=e.getBoundingClientRect()),c=dd(f),{top:d.top+(c.pageYOffset||b.scrollTop)-(b.clientTop||0),left:d.left+(c.pageXOffset||b.scrollLeft)-(b.clientLeft||0)}):d},position:function(){if(this[0]){var a,b,c={top:0,left:0},d=this[0];return"fixed"===m.css(d,"position")?b=d.getBoundingClientRect():(a=this.offsetParent(),b=this.offset(),m.nodeName(a[0],"html")||(c=a.offset()),c.top+=m.css(a[0],"borderTopWidth",!0),c.left+=m.css(a[0],"borderLeftWidth",!0)),{top:b.top-c.top-m.css(d,"marginTop",!0),left:b.left-c.left-m.css(d,"marginLeft",!0)}}},offsetParent:function(){return this.map(function(){var a=this.offsetParent||cd;while(a&&!m.nodeName(a,"html")&&"static"===m.css(a,"position"))a=a.offsetParent;return a||cd})}}),m.each({scrollLeft:"pageXOffset",scrollTop:"pageYOffset"},function(a,b){var c=/Y/.test(b);m.fn[a]=function(d){return V(this,function(a,d,e){var f=dd(a);return void 0===e?f?b in f?f[b]:f.document.documentElement[d]:a[d]:void(f?f.scrollTo(c?m(f).scrollLeft():e,c?e:m(f).scrollTop()):a[d]=e)},a,d,arguments.length,null)}}),m.each(["top","left"],function(a,b){m.cssHooks[b]=Lb(k.pixelPosition,function(a,c){return c?(c=Jb(a,b),Hb.test(c)?m(a).position()[b]+"px":c):void 0})}),m.each({Height:"height",Width:"width"},function(a,b){m.each({padding:"inner"+a,content:b,"":"outer"+a},function(c,d){m.fn[d]=function(d,e){var f=arguments.length&&(c||"boolean"!=typeof d),g=c||(d===!0||e===!0?"margin":"border");return V(this,function(b,c,d){var e;return m.isWindow(b)?b.document.documentElement["client"+a]:9===b.nodeType?(e=b.documentElement,Math.max(b.body["scroll"+a],e["scroll"+a],b.body["offset"+a],e["offset"+a],e["client"+a])):void 0===d?m.css(b,c,g):m.style(b,c,d,g)},b,f?d:void 0,f,null)}})}),m.fn.size=function(){return this.length},m.fn.andSelf=m.fn.addBack,"function"==typeof define&&define.amd&&define("jquery",[],function(){return m});var ed=a.jQuery,fd=a.$;return m.noConflict=function(b){return a.$===m&&(a.$=fd),b&&a.jQuery===m&&(a.jQuery=ed),m},typeof b===K&&(a.jQuery=a.$=m),m});
   
    </script>
    
    <!-- wdContextMenu -->
    <!-- http://www.web-delicious.com/jquery-plugins-demo/wdContextMenu/sample.htm -->
    <script type="text/javascript">
    
(function($) {
    function returnfalse() { return false; };
    $.fn.contextmenu = function(option) {
        option = $.extend({ alias: "cmroot", width: 150 }, option);
        var ruleName = null, target = null,
	    groups = {}, mitems = {}, actions = {}, showGroups = [],
        itemTpl = "<div class='b-m-$[type]' unselectable=on><nobr unselectable=on><img src='$[icon]' align='absmiddle'/><span unselectable=on>$[text]</span></nobr></div>";
        var gTemplet = $("<div/>").addClass("b-m-mpanel").attr("unselectable", "on").css("display", "none");
        var iTemplet = $("<div/>").addClass("b-m-item").attr("unselectable", "on");
        var sTemplet = $("<div/>").addClass("b-m-split");
        //build group item, which has sub items
        var buildGroup = function(obj) {
            groups[obj.alias] = this;
            this.gidx = obj.alias;
            this.id = obj.alias;
            if (obj.disable) {
                this.disable = obj.disable;
                this.className = "b-m-idisable";
            }
            $(this).width(obj.width).click(returnfalse).mousedown(returnfalse).appendTo($("body"));
            obj = null;
            return this;
        };
        var buildItem = function(obj) {
            var T = this;
            T.title = obj.text;
            T.idx = obj.alias;
            T.gidx = obj.gidx;
            T.data = obj;
            T.innerHTML = itemTpl.replace(/\$\[([^\]]+)\]/g, function() {
                return obj[arguments[1]];
            });
            if (obj.disable) {
                T.disable = obj.disable;
                T.className = "b-m-idisable";
            }
            obj.items && (T.group = true);
            obj.action && (actions[obj.alias] = obj.action);
            mitems[obj.alias] = T;
            T = obj = null;
            return this;
        };
        //add new items
        var addItems = function(gidx, items) {
            var tmp = null;
            for (var i = 0; i < items.length; i++) {
                if (items[i].type == "splitLine") {
                    //split line
                    tmp = sTemplet.clone()[0];
                } else {
                    items[i].gidx = gidx;
                    if (items[i].type == "group") {
                        //group 
                        buildGroup.apply(gTemplet.clone()[0], [items[i]]);
                        arguments.callee(items[i].alias, items[i].items);
                        items[i].type = "arrow";
                        tmp = buildItem.apply(iTemplet.clone()[0], [items[i]]);
                    } else {
                        //normal item
                        items[i].type = "ibody";
                        tmp = buildItem.apply(iTemplet.clone()[0], [items[i]]);
                        $(tmp).click(function(e) {
                            if (!this.disable) {
                                if ($.isFunction(actions[this.idx])) {
                                    actions[this.idx].call(this, target);
                                }
                                hideMenuPane();
                            }
                            return false;
                        });

                    } //end if
                    $(tmp).bind("contextmenu", returnfalse).hover(overItem, outItem);
                } 
                groups[gidx].appendChild(tmp);
                tmp = items[i] = items[i].items = null;
            } //end for
            gidx = items = null;
        };
        var overItem = function(e) {
            //menu item is disabled          
            if (this.disable)
                return false;
            hideMenuPane.call(groups[this.gidx]);
            //has sub items
            if (this.group) {
                var pos = $(this).offset();
                var width = $(this).outerWidth();
                showMenuGroup.apply(groups[this.idx], [pos, width]);
            }
            this.className = "b-m-ifocus";
            return false;
        };
        //menu loses focus
        var outItem = function(e) {
            //disabled item
            if (this.disable )
                return false;
            if (!this.group) {
                //normal item
                this.className = "b-m-item";
            } //Endif
            return false;
        };
        //show menu group at specified position
        var showMenuGroup = function(pos, width) {
            var bwidth = $("body").width();
            var bheight = document.documentElement.clientHeight;
            var mwidth = $(this).outerWidth();
            var mheight = $(this).outerHeight();
            pos.left = (pos.left + width + mwidth > bwidth) ? (pos.left - mwidth < 0 ? 0 : pos.left - mwidth) : pos.left + width;
            pos.top = (pos.top + mheight > bheight) ? (pos.top - mheight + (width > 0 ? 25 : 0) < 0 ? 0 : pos.top - mheight + (width > 0 ? 25 : 0)) : pos.top;
            $(this).css(pos).show();
            showGroups.push(this.gidx);
        };
        //to hide menu
        var hideMenuPane = function() {
            var alias = null;
            for (var i = showGroups.length - 1; i >= 0; i--) {
                if (showGroups[i] == this.gidx)
                    break;
                alias = showGroups.pop();
                groups[alias].style.display = "none";
                mitems[alias] && (mitems[alias].className = "b-m-item");
            } //Endfor
            //CollectGarbage();
        };
        function applyRule(rule) {
            if (ruleName && ruleName == rule.name)
                return false;
            for (var i in mitems)
                disable(i, !rule.disable);
            for (var i = 0; i < rule.items.length; i++)
                disable(rule.items[i], rule.disable);
            ruleName = rule.name;
        };
        function disable(alias, disabled) {
            var item = mitems[alias];
            item.className = (item.disable = item.lastChild.disabled = disabled) ? "b-m-idisable" : "b-m-item";
        };

        /* to show menu  */
        function showMenu(e, menutarget) {
            target = menutarget;
            showMenuGroup.call(groups.cmroot, { left: e.pageX, top: e.pageY }, 0);
            $(document).one('mousedown', hideMenuPane);
        }
        var $root = $("#" + option.alias);
        var root = null;
        if ($root.length == 0) {
            root = buildGroup.apply(gTemplet.clone()[0], [option]);
            root.applyrule = applyRule;
            root.showMenu = showMenu;
            addItems(option.alias, option.items);
        }
        else {
            root = $root[0];
        }
        var me = $(this).each(function() {
            return $(this).bind('contextmenu', function(e) {
                var bShowContext = (option.onContextMenu && $.isFunction(option.onContextMenu)) ? option.onContextMenu.call(this, e) : true;
                if (bShowContext) {
                    if (option.onShow && $.isFunction(option.onShow)) {
                        option.onShow.call(this, root);
                    }
                    root.showMenu(e, this);
                }
                return false;
            });
        });
        //to apply rule
        if (option.rule) {
            applyRule(option.rule);
        }
        gTemplet = iTemplet = sTemplet = itemTpl = buildGroup = buildItem = null;
        addItems = overItem = outItem = null;
        //CollectGarbage();
        return me;
    }
})(jQuery);
    
    </script>
    
    <script type="text/javascript">

      var SimpleGallery = {};

      // elements
      SimpleGallery.elements = {};
      {
          // content
    	  Object.defineProperty(SimpleGallery.elements, 'content', {
              get: function() {
                  return jQuery('#sgContent');
              },
          });
          
    	  // thumbs
    	  Object.defineProperty(SimpleGallery.elements, 'thumbs', {
              get: function() {
                  return this.content;
              },
          });
      }

      // events
      SimpleGallery.events = {};
      {
          // page loaded
    	  SimpleGallery.events.pageLoaded = function() {
    		  SimpleGallery.funcs.reloadThumbs();
    	  };
      }

      // functions
      SimpleGallery.funcs = {};
      {
    	  SimpleGallery.funcs.buildThumbItem = function(file) {
    		  var newItem = $('<div class="sgThumbItem"></div>');

    		  // image link
              var newLink = $('<a></a>');
              newLink.attr('href', './' + $.trim(file));
              newLink.attr('target', '_blank');
              newLink.appendTo(newItem);

              // thumb image
              var newImg = $('<img />').load(function() {
            	                                 SimpleGallery.funcs.loadNextImage();
                                             })
                                       .error(function() {
                                    	         SimpleGallery.funcs.loadNextImage();
                                              });
              newImg.attr('src', '<?php echo SG_SELF; ?>?m=1&f=' + encodeURIComponent(file));
              newImg.appendTo(newLink);

              return newItem;
    	  };

    	  SimpleGallery.funcs.createContextMenuOpts = function(file) {
        	  return {
        		  width: 150,
                  items: [
	                  {
	                      text: "Open",
	                      icon: "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAIAAAACACAYAAADDPmHLAAAmZklEQVR4Xu2dCZRcZZn3f897b229prPvJJCVRRIICQODIJviLoKKozMKgs4AIh8HFRycb+Y4yjebzqDjDJsjKqCjbMrAsDPsSADZhcTsWyedTm/VVXXvfZ+veU/dc0+dqupKxkQ6Tf/Pec+tSnVXuvv5v//n/zzve9/ibYMxjGEMYxjDGMYwhjGMYQxjGMMYxiD8gXDmmWem2yfNnW/81HLQoxBZBswCmgBlZEKA3wJPWXSlDeTB6//1b9eNEWAP8JkvXjo/TfoC4N0IB4pIiv0Uqtqjav/qmn/51j+PEaABjj/+eH/h4cdehshXRKSZUQRV/dmA7T33J1dd1TtGgBr47J9/9cBUylwnxpzAKIVavSvsL338+uv/vo/9GIa9jPPO/8phqbS5bzQHH0CMnOa3pH969tmXto4RgCTf4/u3iJi5xHjbkGAsBZhzL/raPUbkJBrnUNwAUEY0jBFEZNSmA9lr0v/Fy68QY/5m+MBDKSgB0NLUhOd7iAgjGQMDg4BijBmVJPDZCzjnoi8vBrmEYRBGEdYqhy6az/JlS1hw0Byam5vwjGGkwvd9Hn3yGW78zzuw1sYkaJQOHAneVgpw7kWX/4sRcyF1EIYhuVyOs854P3901FJEZH8q+fjhTbfw6BMrMUZGnRKY31v6zzuvSeD91EEURS74F5z7aY5ZfsR+FXzAzfxDFy/g6KMOR1Xd8z0whqOfADY3cQHIjHqzx1rl46e/l4Xz98/CQBXCMOKQRUMkWLZkj0kw6glglKNFJE0NlIKAxQsP4o+PXsZ+CwFVdR7mkMULWDHKSGD4/XFEvdlvRJzsx8/3Y8QkcOlgxVFLUPaMBKOXACLL6hGgqSnHvANnj6Y1AGdoD100RIIjl6DKHpFgtJaBs6gBBXzPo7mpCeAtVwER2eNgi4i7Eg9AoUyC+QA89cxv9rhEHG0pIEU9iIwY16+qe+drk3TgSLBi2eH7tRIY3kZQ1XjszmujgwRjBGhMhsZBb0yC5UfunyTwGeUQARD2HLqH1cF8AJ5euX95Av/tEPBSaOnOR2ztDdjUXWJHf0hvwbp/943QkjWMb/aZ3p5mxrgUbTlDa9YHwIhgTGMSRDEJ0DIJFGNkxJPA/0OZL1XdRy6++vUgsqzqLPLY6n6eWZvn6bUDrNpepBioCzqRhUiTSW4EfMFPGdKe0NHkseyAJpbPbeHIWVkyITSnwAgUI4jscCRYQKIEZq+QYEwBkrKsnry7oD/yRj//9VIPd7/cyxudBUr5CCIFX8CT5Js8A3612oeBHRqQH4zYtK3I7U/uRLIeM9vhqOmGFTPgiGnKuKwjAUEEuu9JMEaAahIkwV/fVeRXL/bwo6e6eGr1AFqIkoCnDKRpDEkYFSMmjCps6IYNO+CWV2DGOOH42fCBBcq88eqIUIqkZjpQlF+vfBFrGbEkMG9BEPdKOhgsWb5x52ZWXPka5/9gDU++2oeikPNc4DECwu8PATwgDQhs6oYbn4Nz7hC++aiwdhfkUooxCoCiWLVx25ijjjgsXhT7fauDsTLQGIMq/HxlF0d+81Wu+NkGtnYVIetBxoAI+xAJGVKQL8GtLwjn/srw/WeE0ELG12olODgmgR2RJPDZl1BQFKpn/W6rgIggAiKGDTuLfPnWjdz8WBcoLvBvGQyQhr4CXP9r4fGNwsUrLEdOg0IIkVaSAODXz74w4jyBzwhHHPxf/qabC27awPrNgy7wCLsPGy/sK1gFBVQrZR4BDzBl/2Bk94lg4LUt8MW7DJ9aonz2HUrGg2K0T0kw+gngGQGEK27fyDfu2ALqcjy7hUghtKDg5Xymjktz2Iwm5k7M0JY1pDxBcCC0kC9ZNu0q8ZuNeTbsLFEYKE/jtIBnaIgUFEO47inh+S3Cl4+xzB0Hg2ElCVThmedGDgn8t8AE7lZ973lCGMH5N63j6vs6ISVgDA0RWIiUprYU71zQwQkLWjluXguLpmVdfS8Mj4GiZe3OEo+t6ufRoXHfq71s2V4Aq5BpoDwGEFi5Fi7pN3zrJMvCCZAPYvMbK8HIIYEZgat0LviFQPnUdWu4+p5tkDZgpHHgi5ZFs5v55lkH8MLXD+WuC+bxlXdP4ZiDmhnvgt8YzRnDIdOynHfcRG747Bxe/KtD+OEX5vGuJR0YBQpO14c3ihnYuBO+fK/hle3QlKo2hsuWjgxjaEbawownEEaWz/5gLT99ZEfjfB+pC8rCWU1cd95BrLx8MZe9ZyoHTUrvlTJwQrPHn64Yz31fWsD9ly7klCPHQ6hQsg1TwpZdcNn9wms7apFg4V4iwchvBe9ZzjfChT/ewM2P7oBcg+AXLW1tKS59zzQuOnEyrVkTMwrdyy1nI7h08s75rdzy3C4uu2Ujq9YPQNaAyDAkkCESwD+eqhzQXmkMk3Tw4luWDswfSOYbDlDEwBW3bebf7uscfuZbhcGIoxe38fClC/nL9051wY/fS/chYY3AGUeM48nLFnP2KVMhBEIdVgk27RT+/nEhtE7hQEGtJQpds4hlS8pKoH94JTCMEPi+x63P7uIbv9wCKQGpK/lufGFo1t87JMtLZjUBu99dlFqjPmGHTQ3XffoA/vUzc2nKGAgsdZGGlRuFa541+EYRtMoTHLnkUNTuJgla01ePKgKkUsLvthe45GcbQQEj9YOvyrc+Ppvvf3I2LZl41lMXIvGI+VM9rIIqlV9fSYa6SvHn75zIL86fz6T29PC+wIMbnhceWiexH6ggwWEHL9x9Eoh84ryLLv/S/uIB4lFTYj1jQOEvb9vCms2F+nW+ixL80yfncPFJkwFFtZ6EJxwKIhgMzdBwj4mURAkkeWzK5X7mzeFD2k/kWqEuCUTgPYe08fPz53HG91axvacEKVN3wemqp4QF45VpLVCKqo0hwMrnX8IagxGhPuTys//ia7e6M4v250aQ5ws/fXonNz3RVb+1q0Co/O3HZrvg4/LlMDMeKIRCdxHygRBGgICR5HV31eS5LfeNioAEkDJCzofmlOJ7MRGqoQqgvHNeCzd+/iA++t036B2MwJeaKrC5W7j+ecNlx0aICKoxvxXdAxKIyCTf1xOBH4zkFDCsKfM9oW8w5Bt3bh3+VtVCxLknTeHy06YCWjf4RnDB3tIvrOkRuvLiXDeJpIMmVxQ0HpVBdbOzpwideXFXqw3WmlQ5eVHrkEIdEEe0rim883XhiQ3gaUAQhARhRBhG7nGxWGLRgoN4x6GLCINwWB+iokv3i06gqlat6QtgPOHb93by0rpByJi6pd6Kg9v5h4/OACxRpPEMqJL8nqK44BcjMKLxijCqgACV1/jioIkaVHAxsLCrAIMl6MhB2lNUq0tGq4pBOeeYCby0Mc937txcW9EECJXbVmc4ffnUsvpIBbtEhAPnzGT61Cnc88AjNTfDAAhy9JuHcT388MPhiDaBAlU+wPdg9bYC1zzSBV5909fWmuKqT8yiLefFwa8yZ0bcTHWzfjAkCSpgNVkLsriBAtbGub1yfUht5dfGGIxg2wDkAzB1/I1VAOWvPzCdI+e3OvLWhAePrbO8nh/H3LkHMOeAGcydPTQOcIM5s6cze+YM3nPScTTlssOZwgULFx7dvF96APEMd73Uy8bOEmRN3fbuxR+awlFzmrFRBFBDSdRJ/ub+sqqYRNINCROsgCSPHYwSo0INREmEQhMWh9YRjQk5aEtpmViVimStOrJe+dGZnPqd11GrVVVNvLnw2/du48R5OUQgsk4Z3QDwjDOGgIy+5WCDMjAYctPTu+prUOD6+lx44iTAorWcPsrWAWF9r8SSj01cPjYmgST5Pk4/QmVeFyqhlcQATcrIHXmQJmcQqQm1nLy4jbOWT+DG/+msnQpShnte7ePB1/t59yGtRKUaVQa6v+8IqrbOqpBKG7eB8/E1A7VLJnWDi0+ZzIRmnzC0VZ1Dg7KzAOt6BQXc0Er5tppco1jmUWxi/LDJ1Q1Nvid5v2SAQqiwPQ/FsDIdABWp4JJTJpNu9msbQuPyCk+tyYOR0XpfgKAkQUv0GO56uQ+KClmhCqFl4cwm13LFRjWWkJ2CsqYHSlZdyaYKUbIi64YBLCCJCsTKgZVY3pNyMFH7+FofxQi254XprYqhuldgrXLE7CY+tmw8P364jgoY4dFVAwTFCEFRpZbHGV0pwBNcnXz/a/3DmD/4xFEdjG/xCAJblfsNsLHP0FsSfJc/E8dvk4CCJPlcjJBk1CToMaTyNZDkWoXy632BqxAYn62uDOLnZx87gZ882YXWei9feHr9IKu2l1gwJU0QjToFUFQr2ex5sHp7kdU7SuBRDavk2nxOX9oOamOzlwRf1AV+c3+y6mcBVBCTBFY0yd8iYJzJik1gddCTx4IAJCSogsZCZmHHILSkIGVAofLntZaj5zZxxAFNrFzdX53ujNDTE/DIqgEWz8hQCi2VCgCgo0sBjC88uXaQQj6qnf8D5ZjFzSyemiEMtarcEuOC78q9lAdR7NhFEQumosMXD4FawU6+LpntaEXTSBrcOTgYwq6iMClnUa2qYsmlPU49uI2Vr/dBikoIECnPbciD7Rh1HqA6ePEvvD7vrqSphsKx85tJpQylUlTVdx8MXSkGKNaWZ3eFoxcADJCogaJC8ryG+zcIVHiBYX1AhfHcVVDGZyhXIZVlIegQAVr5f3d72JppwPD0ugK9g5aML1jV0esBjAg9g5H7hfHruP+sxzFzm2P5ryqfuwvQHyieJIGLpd4oqFGksrOXDASTxKBi5itaKf27TXAYCNygNa3V3U9rOXhqhmnjUmzqKlX7Hg/e6CyycyBkRruPRUaHAkiiANhYAYBd+ZDfDZP/J4/zWTglTRTaqhaytc55E0YgBtQkgTZxa9fl+nhGJwtA8ePYjBnFQaSy2SMoolL/OE2t5qxV6CtBa7r6FrbIKhNafN4xs8ndb1hFABG3EfX1bUUOGJ8iDCv/ZgAyWhTAE9jaG1IMtW7rd+HULJNbDGFkgcT8oUpooaeoKIIFxIKKxQCqgkgi/VomRaQkJaFaEBAVVKpNYJwCQBM7mDyt26iJrFMlrFW0mtOu7zF3Yrp2P0DAFiy/Xj/IqYe2oIGO3ptDjRE29QSUwjpLa1aZMz5F1hdKUTIDrLVlvyTkQ3GzSkhmuiq45yZ+DCIWlSSM1iRpAlFEpbJMTFI2giZEaNznIlJhMIx9SGVJaFUBZVKLX3850SpdfSHoKDeBiLrDGYi0dg9AoT3nYTyBygog/mMSRLbc2RNEwQJeXPNbi5K0e62CQXHPLQhgy6Ywdg5GKsq9hAy6+x7AqlJ0v5bWldS2rKmv5SIMhgo2MYCjthHUV3DRA78mAZwTRqhww7HcqkJ8vgOAIZ5xWg5kWdpVExIIGDT+t8pAG0UdI4DkkniCekSQSgWwQGBBraKmFneUrG+SEkOohOAUL7IKOqoUoPYvilIXpvJmUrTiMUSqRGXnZQExYJBEfgUkNnOAuNcSsiTlnyAWICZH9dZwUWpDqWwIoZikEVSTACLDujkXfB1tfQAFSOQMrMZyXRdWGRaRdWYQNUkQjSgGkhU+A0YFAFMOelTRG4gDLzUaQYAqUnfSV3NBFUysVlL7QAvbeLt5mfgJkdzzUeUBVGnOCJjhpZDIOuMXz8a4hLQWrFWiCFCJg4vGbycgCEZBca8RK7wR0NjXCxgA0aQMNIAmptBBdofhEAGxwkOlCYwDWXSs1brs9w2AxoQanR7AWujIeeDVN0O9BUtoFZHKP4RNrDWhuhfLsk8SXAGR5N8T1y9oOUUAmMoNIhgAm6wF7Hb8YwIoSeqqsx2urxCBrRN/VbKe4AnoaPYAkSrT2jx83xCGNRrtBjb3BBQDTfwXJAtJQMYovbZyZ6+KYEVJAu6uiAiWMilIdgZbQJJZWhmXxCeANjCBgIJTpLRRBItVqVIuFLoGovrRFWFCi4eM9rWAMMIRIOtBf1hDDj3h1a1Ftg+ETGnxXL5P1MPie8K4tLKpPyZAZUBNLPmaXKWy60di+ilXBRq/BpVeoL4HUBKUK5PWlOIJrkwVERIoxVLEuq4SGKnT/jYsnZUt9z5G8X4AVaUlLbTmPPoLliqIuE7h6u0lprVksC4VSIWhnNpkeXmnIbRg3MwvGz3AEpNCkkpAEuUViYcgKGjiB6jM/Qi7h7jCGJ9RbCVhERE8I+zKR7y8pVC79+F8kce8CT5BaAEzelOAdY0ewxEzM9y5vcbCiIFw0PLU2gInHJSt0t9IYUpOafGVnpKQMnGApeKmD6saz3AMIKbS6QtaThMKRogAU8nDYX2AkiC0MDGrtKfjrWZakQJ8g9vwsb4rqK0AERw4McXEFg/nfcwoTgGRQnNKOHxmmjufre+snlg7yGDQhiT5P/5+cj4c0Gp5ptMkQUaxCQGSxaByfS62su9vBIhzv9U4Dph6tR6xJ6mmRWRhZoviGyW0le4fcApw/28HCAaj2vc+RMqRM9O0ZqAQKEZ0dG8JCyPlqFkZyBios03qsd/lWdsdMrfDpxQlsh1FERZhwTjl5S4oRIJvqMz3iQeIS8cKYgiCc+2iSSgFB5MEO4mxClUQGwef9gzMaFb32NrKD5g0An1FywNDBEDqNwAOn5HBN4Kio9sEAhRCy7LZGeZNSrFqawn8aiPY3R1w9ysDXHR8e2yM4j3zTiY70nDoBOWxLeVZLsnMtigiBhElMXsae4Nky7hU3yQakSgFUn9nMxJ3JWFBu5L1lNBWl35pX3hqfZHH1+Rr73+wkGs2HDs3w2Bg47J3dCtAZGF8k+GE+TlWbSqCL9TCz57t58+Wt5L2ILISq4AzV5EKh46PWL0LtuSFtKfloMbO3lZ4AlTKFYICWhF0QwLKCiEC6DDbARSCCOa2K3PaXHWTLEIlJMMY4acrewny9eX/8OlZ5wHCSAEZZQqgYGvcHi7ASfOzXPtonY5gyvDrNXnueTXPGUua6S9aIPnjhpEl58E7p0fcusaj4PYHJqVgUhGUB0okYJCEFIlqxEA06dvXbwkpgYX2NCydqLj3VhyMMRWz/7WtRW5+pqcuybHKuxflaEm7Bhgio00BBFAFtEIeBwM45sAs8yanWbWtRhoQIFK+90gPpyzKkjKUewKJChRDmNakvGs63L3eUAqJ/UDV7d9uaGU5iFSlguQqVIYfrXD9OU85Zqpz/gRREvxk7wJ4Yvi3R3vY0RXUvvVNASNkUkLKq5s+R4cHsFYr3XwE43PCaQfnuGpznTSQMjzxRp6bnx3g83/U6swUses3xpGgFMGicZYgUu7dGCuBJjuDkYQAAGjl4g9S845gKnYFURl8XzluujK9yQU/8SeJ83dBfWxNgesf74aU1J8cnvA3d3czc5zHxw5vpreoJApgRwEB6rBZy17gU8ta+OHTffTmbY29cm7wD/d1c8K8HAeONxTCCql1JAgsHDYBUl7EveuFvkDIeIqIkKiAIpCMMisSA6l1T41PDo8QxmfhhBmWmc1KYF3AY0JWqEUpFDYMpmlpTpPPD9a/AcZAoWA57yfbQeFjS5oZCGP5l1HXCKrIbcVAOGSq737pa/+nt/YfyRc2dJb4+n/t5AefnIgnxPkWz/NiElBSZVG70HaQ8OBGYU2/4KH4yfmSlUqgigEQiKBaBSRharxkMW+c5dgpyrhMHPxyp8/zKma/MfDbXYaWljRXfHg237p9HZs7C5A21IQvrv4/78btgHLWke30FHT0f2CEdWsDwrnHtHLrC3m6+qPaJEgbbn+2j29PT/PVk1rJlxRFqkhQsMrUnHD6QfD8DsPKTqG7CEYoE0FIloHBYiGWb612+pEKAJOyyhGTlEUdFk9waQc0Dn4FqVMGNg14/K7PdwSZOT7DVz44mytvX8+W7Y1J8PkbdzhCfWxpC/3FUXRcvNbe4eNq34On+EPlXgv/dE8PeHVypYEr/3snszt8PnlErlwVJCQAyp5A8Yxw9GTLonHwSrfh1W7oHBSKEYiAJ1SqAlqxvVuAtOcMJos71NX5zSkIlbjWrwo+KCmBzQOG57p8EAWFgWI0RII0l35gFn/3yw1sbUCCwUA550fbUFU+cWQb/XkFHaUKAJRTAXxmefOQCgywZntY53AlIQiVS27ZQXNmEh86OEN/UUESEsSdwiiyWBFaUvBHUyKWThQ2Dwjr+2DbIPSWjCNDqAkJ0gZyPrSlXeCZ1eLWHBwRInWzHk3yfVXwfYGtecODmzz6Q8vEnJD2BKu4n3PG+AyXvG8W/3inI0FDJTj3J53OV7x/oQ8yqjqB1fVtMVTmdHj8zXvH8ekbdkC9BTFf6M1HfOGm7RTPnMCZh+XIB0pktWJWWmvdCEIlFMGIMqcV5rZCYCG0FqugUNG08YwLJL5JunzFkBhx4BGRilvdfIFNA4YHN3v0lsATZUdeGZcTMmUSDJQiZkxI86X3zuTb/7WBbduLwytBSTn3x518+yOtNPmW/H6tANq4tu0vWd63OMulJ7fx9/f0xtpclwR/fvMONu7q4PxjmuO8TGLCjBtRFMUlKCWbbBVPGa3zw0l8OliC5P1qHkplUDYMpHh2h09PUd1zC4QWugaU8TnPqYgjQdE6JfjSabP4zl1DJNhRhFR9EgyULBf9oocTspOY562jpGZ/JEDtTmC1Sjgl4KsntrJ2Z8h/PpWHrNSXyZLlitu7eGVbia+f0saMNs/NGkuCWKZjk6haud+uEiBSGfRYUWohlxIKEbzY7bOqL4UKTGyCrkHrgm/Aka5zIGR8U0KCfDFiWkeaC949k6vu3kRnV6E+CTwYjIR7B5ehGct8fwMlTe2PHsAFv+FnAEYWrIGvn9rG8xsD3tgSQFrq32OmcNNjfTyzrsjlp4zjA4uzZD2ckVISVAaS2kRMgl5XraTc3vWN8MS6Im/0Z8g2p4nizp9Ae9pj52BEoCBAZGF7f1QmQaIE0zsy/MW7Z/Cv/72Rzq5hlMAoIR73FZcD7BMSGPY5BEhmYeWo9gPT2zyu+XgHsyb5UGr0wQzCG5tLnP3j7Xz65i4eWVsi5UFTSur2XkQkJkXFqB18xQBZX8ilhVVdEZf8chcfvGYbX791M5u7i6R9Q+S8BfgetGcNqhBaxapSjJTO/jAmJuo8wZskSPOFU2YwaUIGAkt92JgEvBHOIi3B/kSAOMjVMyohQCU58iXL4dN9fnhWB7MmNiABQEpQgbue7efDQ4H51I07ueOVAn1FpSkt5FIkJ3TX/D+TQTnonqiT+Za0IVR4Yn2Ji2/fxanf38rVD3S7FNTVXeSquzaxYYcjAVZxREh5wvicAXVxLfsKZdubJChZFEcM8kXLtI4Mnz95iATj3zoS+IwIVJKjvwhLZ6a44VMdfObGbtZtDSErDdTAEFq467kB7nopz8JpaY4/KMMJ87Ku1zCx2bjdSJ6pzXpFiBQKodKVV1Z3hTzyuwIPvFHg2Q1Fwvj833hZ14jL4d8dkvEvnDrDGbyiizj4xm19oysfEVhFgFBh20DEhCaDOwgCVx0wpSPNOSdN59r7N7Nj5zDpAFuVDorq77et4IZk6CsI75jqc+MQCS79VS+Pv15eNDI0TAso/HZTkd+uL3L1Y31MaPVYNDnFAR2OCO6Y+UxKMIDiZqjrMO7MWzb2hLzeGbCxO8QWLOXSofZ6fsq4HP79ezbx+SE5n9GRYTC0ALgPoc4Ztg9EcTuZklU6+yMmNns4EihO8aZ2ZDj7xOlc/8CbJChBSnaPBN4GinSP9DJQaxJARBqRwDVR5k3w+NmnO7jywX6++/AAhAq+NLYdrqgHFLp6Ix7rDnnMaoXlr3uPlycgUivoNUmwvavIv9+7iXNPnu5yeyGwWBwJXCm4Ix/ieIE4b7ClL2SyI4HBWhgsWacEf/auafzHA1vo6m5MgvuLRxGmDd0cuD+UgfZ/u9HBmSffwBUnNXPEdJ8r7u5j0/YI0gJCY0h5GKHxNwj10ZgE19y7mbNPmuZIUAyUSFw3kXFZzylBZBUEwsiRgEnNPtmyEgwWrftswz89YRo3PNSYBAE+D5WWtYeRdxJwy8itApLau64JbITQutzMhw7JcNtnOzjr6BwoECgoIwKOBDuLXHf/FjZ2lfB9IbIQWMj44nI/KKG1KIozhn0hA4FFtWwMSxGTx6X4k+OnMr4jDYE2MIYGvOhHfO7JD41QAjTsAtZ35NUkcs5+ZrvhH9/fyg2fbOe4BWkEoKRgdSSQwBm5Hzz4JgmKpDywVt2MTxthYpOHlAmtiisRt/SWSQBJOhiX4qzjpjB+XKoBCRSQJoy58X9LAuH3xHkXfW2XiLRX8dNad9T5hed9yl3ju32DINiTFFDTK4hAzhenCg+tDrjh2UGeXh/Q12dB4hwOCHsHCkSabDT0GmhnYJnQkeHTJ0xlmksHFgApp7RtA3F1IFhVPIPzBLlyOanlptPW7iI3PbKN7p6gse9B8yhncc3yO0amAiQq8HtXDaqQL8vjuxem+eHH27jjs+P46/e3OlUY1yxgFYoKQTlwugfBtkCYfH/ah4XTfC54VzP/933NtKQEomGVwOXwHz20lU07i/g+RKqEqqRTuEpEIEkHobKlnA4QxaIUgojJHWk+dtxkOtpTECrDQ5oQbhpSghNHlAJccO6fVChAqVRqWAU0VoNqRUgZIevDQMmtxvHMxoDnN4e8sCXklW0huwYVLSo0OP/XZMSViHPHexw+zecd0zyWzUy5FcvWrDjluek3BS66pY9iCHjUR6Aul5/1zslMLRtDFESgEChb+0NczImPTRKmtZrYGGJx/oGtu0r8/NHO3VWC9Vg9hWtXvD6iG0GqWjOwe/49SbetFIERmNxs+PAhGU4/NOMI0ZVX3tgR8pstITsGnAEjiJK7hnzPlWxMaBIX9KHgu1nalhE8g3vfwPUK3OCMwzII8MVGJEgJO7tL3PRwJx8/brJz+cVQUXUSz8Rmn619IaFVKPcJNvVaprT45FLl6qCk7iPpTj9mMr94rJNdvY1IILNBvwecCuhbqgC5Nz3A5z5JU1OuSgEazfS9ARHBCG6kPCFlknSuWimDngERHDHCSHEpX+unrbascMtLxSEl6Kewm0pw5rGTmdLuSBArl9sVtdmRAIyAtc4TMLU1Rc6XxBN4wrZdJW59ohEJYtjLuHrFlW85AS445yx3VdUqAux7ElRD6lG/cUVZnwS3DjROB6HS0Z7mjGMnu1KvFBtDEfKBdR4giBQp2xfPCNNbfWcMI5sYw227itz+5HZ6+gLwhLpQu4EoPI7rj103AkzgHjeAqsrCvQWNh1I52HP0FtSlmX/+SDMZn+GNoS9095T4+WPb2LKzhO9Jebu5kvXFVQHG4IKNQhAqG3sC+koRiKJYimHEpPYUH1wxkfbWFERKXYiZhfFPHYlVgBt78wOo30of01uwfOSQNN8ZIkF2t0gQcMsTnWzZFcQkILC4mT61OYUg7rnFeQ8294Zu+RggUmcemTguzalHTiST8YbvfxhZypk/894yEygAUj+QIrKPDeW+L2NVoWdQ+cjiFKpNXHxbfvh04Au7egJue7yTDx49icntaVcGhuCUYGqrx6ZeZwwRwfmFDT0lprelaEoZXHVbUrfH8LC5LTzzWs9wDD2atlkLgFf3JQGC+oGBKLJVuV1V91Xg3jJF6C0qpx+SRhS+dHtDEjgjN5TLnZxPdtWBxSrkUrhScFNvUiIGEWzqccfJO5KoQhjBgplNvLi2n2LJ1nFzMg/jzQFe3ZcpYEM9ExeEIX39AxgjNQjQWOL3MzhP4NLBh5t2Kx309Abc8dQOV+dXpIOUYVprCiNCpKBQ3lkUYTWuZJSWnE9bk18/DQhZROfs2xSg+gwiS2sRoFgssXHzVmbPmEqgIUDF1u09lVsRYaSjp6B8aLGPtTn+zx2DjZTAkeBXQyR47/KJzuC5dKC4EnB6q8fG3pAwVHCewKkEBlAABc9QH4pBJbOvPcCz1IPACy+/wdLDFuN5Jj71i1QqRRRF8SLQXugIjqxdTf1FOP3QNEaEi2/PU2hMAu58eogER01wJCiGisXtbWRGq++OzYsUJjV5GFFUwYhQCEL6BiMQ6qGAsn6f9gE+d9HlSwzylIikqYEgCHnfqcfzxyuWMFgo8nZCa8b1Cbjg570uqI36BG1tKU4bIsHEoWsQJc0iVcpBB8WBtG94aW0/Dz2/E4zUY+Qm1J7BtUc/uc88gBnc8TroJmrDzfwHHnmSl19bTTaTxhjD2wV9ReWjh2X47hmtu+UJensD7nq6i+09ASnPoArWAprcpq5O9t3H7vPCmj4a4GmK8sa+U4CkG3iViFxAHURR5GT/pONWcMSSg8ll0oTxgdCjHAJuEekXLxa54Be9FEq2sRK0pjhpaQczJmYIo+SkcSNx8CMe+k0367cODt8NtPo1rl3+zX1OgHMu+vJij9QTlS3h6tZwZK0zhIcsmsfM6VNobWkm5fuoKqMbMQkKXPKrIoEVEKUuIiWTMRx8QAvzp+dobfIBKASW9Z0FXlzT78pIPGFYhPZkrl9x/z4nAMC5F33t60bkr2mAIAxRVZcOfN9HRHg7QICcb3m+bzL3FpYR4QG2wWfRQCrj0dbkYYwwMBiRHwyJ5WBYqF7HNcs/RwMIew/mvIsuv1fEnEhjJOfqorydkPUiXg9ncV9xBREGsDTeoKJJ0IXGQNdD6USu/uPVf0gC8Lnzv7rA+N5dInIgdTGGtARlEixPlGBvQvXNrWE3/8G3hF37vStfj9R+RNWuoS7GUNIUC/0NnJx5Go8I8Nh70Ctd8N+qPYHX/cu3XghK9mS19iHqYgxFTbHA38Bp2cdpkjxoam/M/G9w9fLL2AMI+wjHH3+8v/DwYy9D5Csi0kxdjKWDHXYc/1NcwiY7FbB7nhJUNyL2Qq4++jb2EMI+xme+eOn8NOkLEE4D5oqITwXG4BMR4fFcMJ8Xgrk6SJOA0hCqu1B+TMjf8R/LNwCMMAIkeM+FF2ZmSMtCT2W5GjlKYBkwA8gBytsTAgwCmwT7TEZLT/46XLz6idKSAzF2OcpyYAFCFsUgFIHVKE8juhKCeyqc/sglQDUZppvsHM+mJwg2ExmjvA3hWSuKKUam1LXZFtbefdVVRWKc9+8pwqUH4nEg2NmopIGNwOts7FrF3e8tsmcYwxjGMIYxjGEMYxjDGMYwhjGM4f8DToM89/gO7jUAAAAASUVORK5CYII=",
	                      alias: "1-1",
	                      action: function() {
	                    	  window.open(file, '_blank');
		                  },

	                      /*
	                      items: [
	    	              ],
	                      */
	                  },

	                  {
	                      type: "splitLine"
	                  },
	                  
	                  {
	                      text: "Save",
	                      icon: "data:image/png;base64,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",
	                      alias: "1-2",
	                      action: function() {
	                    	  window.open('<?php echo SG_SELF; ?>?m=2&f={0}'.format(encodeURIComponent(file)),
        	                              '_blank');
		                  }, 
	                  },
	                  
	                  {
	                      type: "splitLine"
	                  },

	                  {
	                      text: "Add to favorites",
	                      icon: "data:image/png;base64,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",
	                      alias: "1-3",
	                      action: function() {
	                      },
	                  },
	             ],
        	  };
    	  };
          
    	  SimpleGallery.funcs.reloadThumbs = function() {
          	  SimpleGallery.vars.imagesToLoad = [];
          	  SimpleGallery.elements.thumbs.html('');
        	
<?php 

	$allFiles = $sg->getFiles();
	usort($allFiles,
	      array($sg, 'sortStringsCaseInsensitiveDesc'));
	
    foreach ($allFiles as $file) {
        if (!$sg->isImageFile($file)) {
    	    continue;
        }
        
        $jsFile = trim($file);
        $jsFile = str_ireplace('\\', '\\\\', $jsFile);
        $jsFile = str_ireplace("'", "\\'", $jsFile);
    			
?>
              SimpleGallery.vars.imagesToLoad.push('<?php echo $jsFile; ?>');
<?php
    }
    
    unset($imageFiles);
?>

              SimpleGallery.vars.totalImageCount = SimpleGallery.vars.imagesToLoad.length;

              setTimeout(function() {
            	  SimpleGallery.funcs.loadNextImage();
              }, 1000);       	  
    	  };

    	  SimpleGallery.funcs.loadNextImage = function() {
        	  // calculate progress
			  var totalImageCount = SimpleGallery.vars.totalImageCount;
              if (totalImageCount < 1) {
			      this.updateProgess(1);
              }
              else {
            	  var imagesLeftCount   = SimpleGallery.vars.imagesToLoad.length;
                  var imagesLoadedCount = totalImageCount - imagesLeftCount;
                  
            	  this.updateProgess(imagesLoadedCount / totalImageCount);
              }
        	  
    		  var file = SimpleGallery.vars.imagesToLoad.pop();
              if (!file) {
                  return;
              }
              
              var newItem = this.buildThumbItem(file);
              SimpleGallery.elements.thumbs.append(newItem);

              // newItem.contextmenu(this.createContextMenuOpts(file));
    	  };

    	  SimpleGallery.funcs.updateProgess = function(progress) {
        	  var newTitle = 'simpleGallery';

        	  if (progress < 1) {
                  newTitle = newTitle +
                             ' (Loading: ' + parseInt(progress * 100) + '%)';
              }

              $('head title').text(newTitle);
    	  };
      }

      // variables
      SimpleGallery.vars = {};

      // page loaded
      $(document).ready(function() {
          if (SimpleGallery.events.pageLoaded) {
        	  SimpleGallery.events.pageLoaded();
          }
      });

    </script>
  </head>
  
  <body>
    <div id="sgHeaderWrapper">
        <div class="sgHeader"></div>
    </div>

    <div id="sgContent"></div>

<?php

// additional / custom styles
$customCssFile = realpath($sg->Config->custom->style);
if (false !== $customCssFile) {
?>

    <style type="text/css">

<?php readfile($customCssFile); ?>

    </style>

<?php
}


// additional / custom scripts
$customScriptFile = realpath($sg->Config->custom->script);
if (false !== $customScriptFile) {
?>

    <script type="text/javascript">

<?php readfile($customScriptFile); ?>

    </script>

<?php
}

?>
  </body>
</html>
<?php

endif;
