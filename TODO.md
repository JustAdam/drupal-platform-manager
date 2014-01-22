TODO
----

- Add config support for multiple versions of an asset
- Release profile configuration (multisite configuration with different modules)
- Write unit tests
- Core config options other than directories should be alterable via command line switches
- --rebuild-asset=asset option to force redownloading of asset
- Wrap use of cwd SplStack into an directory moving wrapper
- Refactor ModuleDownloaderInterface classes using proc_open()*
- Refactor GetDownload archive extraction and processing
- General refactoring and reduction of dupe code!
- Fix GetDownloader archive extraction to test if the archive contents are archived within a directory or not.  If they are not our directory gets messy!
- implement patching of assets downloaded via GIT
- Git switching to a revision requires error checking