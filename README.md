drupal-module-update
====================

Drupal asset (modules/themes/libraries) manager and release builder.

Features
--------

* Yaml configuration file
* Downloads only assets that have changed - FAST

Requirements
------------

* PHP >= 5.5
* drush
* git
* wget

Installation quick start
------------------------

	$ composer install

	$ cp data/config.yml.default data/config.yml

	$ vim data/config.yml

	$ touch data/dmu.state

	$ cd bin

	$ ./dmu install

Later

Update assets folders and then create a release

	$ ./dmu update

Only create a release

	$ ./dmu update --create-release

config.yml
----------

	drupal_core: Default version of Drupal being used
	method: Default downloader method to use (drush, git, get)
		directories: Core app directories and asset mapping
		base: Base directory for all files
		releases: Where to store releases
		downloads: where to store all downloads
		modules: Module mapping
			base: Directory name
			subdir: list of subdirectories to use
		libraries: libraries
		themes: themes

	assets:
		asset_type:  modules, libraries or themes
			asset_name: 
				method: Download method, defaults to drush if not specified (drush, git or get)
				drupal_core: Drupal core version to use, defaults to value in config.yml if not specified
				version: Version of asset to get, required by drush method
				patches:
					- list of patches by URL
				revision: used by git, defaults to HEAD (recommended to not use HEAD - see Warnings)
				url: URL of asset, required by git, get

Example:

	assets:
	  modules:
	    drupagram:
	      subdir: contrib
	      version: 1.2
	      patches:
	        - https://drupal.org/files/drupagram_doesnt_import_special_chars-1876620-1.patch
	    advagg:
	      subdir: contrib
	      version: 2.3
	  libraries:
	    phpsass:
	      method: get
	      url: https://github.com/richthegeek/phpsass/archive/master.zip
	    imagesloaded:
	      method: get
	      url: https://github.com/desandro/imagesloaded/archive/v2.1.2.tar.gz
	  themes:
	    mothership:
	      version: 2.8
	    adaptivetheme:
	      version: 3.1


Downloading an asset will create a unique directory based on the options passed to the download.
This means an asset will have a different instance available for each time update is run and it has a different configuration.
Thus there will be multiple directories for the same asset each time:
- the version number increments (or decrements)
- a patch is added (or removed)
- download method is changed


WARNINGS
--------

- When using the get downloader method with archives, it assumes that the contents of the archive are held within a directory.  If they are not then things will go a little wrong ..

- Git updates using HEAD will result in the folder being used always being the same 'version'.  Release history for this asset will not be available. To enable release history then you need to specify a particular revision to pull from.
