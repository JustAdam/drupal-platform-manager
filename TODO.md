TODO
----

- Global: Write unit tests
- Global: Core config options other than directories should be alterable via command line switches
- Update: add --rebuild-asset=asset option to force redownloading of asset
- Global: Wrap use of cwd SplStack into an directory moving wrapper
- Downloader: Refactor ModuleDownloaderInterface classes using proc_open()*
- Downloader: Refactor GetDownload archive extraction and processing
- General refactoring and reduction of dupe code!
- Downloader: Fix GetDownloader archive extraction to test if the archive contents are archived within a directory or not.  If they are not our directory gets messy!
- Downloader: implement patching of assets downloaded via GIT
- Update: Add option to use relative paths in symlinks
- Update/Application: Move $asset_types variable up into the Application (getAssetTypes())
- Update: Add support for downloading Drupal core
- Install: add option to support rebuilding (and cleaning up) of directory structure after changes in config.yml