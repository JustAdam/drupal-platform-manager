drupal-platform-manager
=======================

Drupal core and asset (modules/themes/libraries) manager and release builder.

Features
--------

* Yaml configuration files
* Asset distributions (multisite configuration for different asset combinations)
* Downloads only assets that have changed

Requirements
------------

* PHP >= 5.4
* composer
* drush
* git
* wget

Installation quick start
------------------------

	$ composer install

	$ cp data/config.yml.default data/config.yml

	$ vim data/config.yml

	$ cp data/distribution.yml.default data/distribution-name.yml
	(update config.yml:distribution_info)

	$ vim data/distribution-name.yml

	$ cp data/sites.yml.default data/sites.yml

	$ vim sites.yml

	$ touch data/dpm.state

	$ cd bin

	$ ./dpm install

**Later**

Update assets folders and then create a release

	$ ./dpm update

Update only this distrubution

	$ ./dpm update distribution-name

Create a release only

	$ ./dpm update --create-release

Create a release only for this distribution

	$ ./dpm update distribution-name --create-release

Clean up releases folder and remove assets (from downloads folder) which are no longer used (determined by which symlinks exist in releases).
_--dirs=3_ will keep the latest 3 directories in releases.  Defaults to 5.

	$ ./dpm cleanup --dirs=3

config.yml
----------

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
	distribution_info:
		distribution-name:  Name of a distribution you have created
			buildfile: Location to distribution asset configuration file. (deleting an existing will remove it from the releases directory after running cleanup)
			overrides: List of files to link to in sites/all that are found outside of a Drupal build
				sites.php: Location to your sites.php file
				settings.php: Location to your settings.php file

distribution-name.yml
---------------------
core:
	version: Version of Drupal to use
	patches:
		- list of patches by URL
assets:
	asset_type:  modules, libraries or themes
		asset_name: 
			method: Download method, defaults to drush if not specified (drush, git, get or symlink)
			version: Version of asset to get, required by drush method
			patches:
				- list of patches by URL
			revision: used by git, defaults to HEAD (recommended to not use HEAD - see Warnings)
			url: URL of asset, required by git, get, symlink

**Example:**

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

sites.yml
---------

	site-name.com: Domain name for the site
  		distribution: Distribution this site will be build into
  		source: Location to the source files for the site itself (this will be outside of the dpm and in a seperate repository)
  		document_root: Location of site's document root as specified in your webserver's configuration (this will be updated to point to the relevant distribution)


All distributions will be built from the shared assets downloads folder, so an asset will only need to be downloaded once and it is available to all distributions.  Each distribution can run a difference version of a module, or the same version; one with a patch and one without.

Distributions are stored in the releases folder, release/distribution-name/timestamp.  A symlink called latest will point to the latest version.

Sites which are using this distribution will be symlinked from the sites folder to where their source files reside.
The document root (used by the webserver) location for this site will then be changed to point to this distribution.

Downloading an asset will create a unique directory based on the options passed to the download.
This means an asset will have a different instance available for each time update is run and it has a different configuration.
Thus there will be multiple directories for the same asset each time:
- the version number increments (or decrements)
- a patch is added (or removed)
- download method is changed

Assets which are no longer in use can be removed with the cleanup command.  Assets are marked for deletion when they are no longer referenced by any symlink within the releases folder (thus they must also be removed from the configuration file first).


WARNINGS
--------

- When using the get downloader method with archives, it assumes that the contents of the archive are held within a directory.  If they are not then things will go a little wrong ..

- Git updates using HEAD will result in the folder being used always being the same 'version'.  Release history for this asset will not be available. To enable release history then you need to specify a particular revision to pull from.

- Source code for actual websites needs to be managed and deployed by another tool and is outside the scope of the dpm
