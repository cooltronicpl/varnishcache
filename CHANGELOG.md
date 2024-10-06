# CDN Cache & Preload for Craft CMS 3.x, 4.x, 5.x Changelog

## 3.1.0 - 2024-10-06

### Fixed

- Resolved [issue](https://github.com/cooltronicpl/varnishcache/issues/2#issue-2560868356) related to removed and deprecated code in Craft CMS `5.4`. Updated to ensure compatibility with Craft CMS `5.4`.

## 3.0.0 - 2024-10-05

### Added

- Support for Craft CMS `5.0` and later versions.

### Fixed

- A bug that prevented accessing plugin settings in internal plugin code in `5.0`.

### Codename

- Autumnal Equinox

## 2.7.2 - 2024-02-19

### Added

- Support for Craft CMS `5.0.0-beta` and later versions.

### Fixed

- A bug that prevented accessing plugin settings in Craft CMS `5.0.0-beta`.

### Codename

- Washington’s Birthday

## 2.7.1 - 2024-01-09

### Fixed

- Fixed cache issue by clearing cache after preload workaround in PreloadCacheJob (introduced in 2.7.0)
- Reformatted classes and twig code

### Codename

- Happy #PlayGodDay

## 2.7.0 - 2024-01-08

### Fixed

- Fixed preload issue for certain URLs by adding a fallback mechanism
- Fixed unnecessary cache issue for certain URLs

### Improved

- HTML preload performance and stability

### Codename

- Team CoolTRONIC.pl LLC wishes everyone a Merry Orthodox Christmas

## 2.6.0 - 2024-01-02

### Added

- Configurable Varnish clearing domain and URL in Settings
- Customizable timeout setting for Preloading in Settings
- urlmode method to clear cache by URL header
- Compatibility with `5.0.0.alpha`

## Changed

- Set Varnish clearing URL to http://localhost when using Cloudflare
- Changed severity log level from debug to info
- Disabled SSL verification for Varnish clearing URLs

## Fixed

- Fixed Varnish Server Cache clearing issues with Cloudflare
- Fixed minor bugs related to Varnish Cache clearing

## Codename

- Happy New Year

## 2.5.2 - 2023-12-24

### Codename

- Happy Christmas Eve from CoolTRONIC.pl LLC team

### Changed 

- Optimize plugin keywords in composer.json.
- Change log severity from info to debug.

### Fixed

- Fix issue with double zone in URL when clearing Cloudflare zones.

### Removed

- Remove unnecessary alerts import from plugin settings panel.

## 2.5.1 - 2023-12-13

### Fixed

- Fixed a typo in the readme file.
- Fixed a typo in the 2.5.0 entry of the changelog file.
- Fixed a typo in composer file.

## 2.5.0 - 2023-12-13

### Changed

- Unified the plugin codebase for Craft CMS 3.x and 4.x by merging the `craft3` branch into the `master` branch.
- Renamed the plugin throughout the project to match the name on the plugin store.
- Improved the plugin code quality and consistency by refactoring the internal code and variables.

## 2.4.0 - 2023-12-04

- **Enhancement**: Added support for Cloudflare cache clearing. You can enter your Cloudflare API Key, Zone ID, and Email in the plugin settings and test the connection to Cloudflare. The plugin will automatically clear the Cloudflare cache when you clear the Craft CMS cache.
- **Fix**: Fixed an issue that prevented adding multiple custom sitemaps. You can now enter multiple sitemap strings in the plugin settings and the plugin will generate the corresponding sitemap files for your site.
- **Note**: We have dropped support for the craft3 brach for Craft CMS 3.x for feature updates. If you are using the 3.x branch, please upgrade to the 4.x branch to get the latest features and bug fixes.

## 2.3.1 - 2023-08-09

- **Fix**: Rectified an issue where the Live Preview functionality in Craft CMS was not displaying the latest changes when the plugin was enabled. The caching mechanism has been updated to recognize and bypass the cache for Live Preview requests, ensuring that users can see their real-time edits without interference from cached versions of the page.

## 2.3.0 - 2023-06-21 [CRITICAL]

- **Enhancement**: Implemented an event to trigger automatic cache refresh when entries are updated or undergo modifications.
- **Fix**: Rectified an issue with incorrect class import for an event, ensuring the cache refreshes automatically after a URI or Slug update.

## 2.2.4 - 2023-06-21

- **Enhancement**: Introduced an event to automatically refresh the cache after a URI or Slug update.
- **Feature**: Updated README with a new section answering a common question about cache clearing during a plugin update.
- **Feature**: Modified the plugin icon in the README to PNG format for compatibility with both dark and light modes.

## 2.2.3 - 2023-06-18

- **Enhancement**: Merged repository with 3.x branch from tag 1.1.2.

## 2.2.2 - 2023-06-18

- **Enhancement**: Revamped Statistics Tab and improved descriptions for better readability in the plugin.
- **Feature**: Introduced a list view of available data on the Statistics Tab for each cached page.
- **Feature**: Incorporated execution time statistics for single Preload Job of cached page.
- **Feature**: Integrated curl request time statistics for the first access to a cached page.
- **Feature**: Added overall data statistics including execution of single preload job and request times.
- **Fix**: Updated CHANGELOG and addressed minor bugs.
- **Enhancement**: Refactored plugin code for better readability.
- **Feature**: Introduced an option for delay-free preload.

## 2.2.1 - 2023-06-17

- **Bugfix**: Modified the cURL mechanism to prevent the display of Varnish purge information.
- **Bugfix**: Fixed an issue within the Varnish cache clearing function.

## 2.2.0 - 2023-06-17

- **Improvement:** Refactored many plugin functions for enhanced performance and clarity.
- **New Feature:** Added option to disable CRON jobs as per user preference.
- **New Feature:** Introduced the ability to disable Varnish Server Cache clearing, and changed the default method to BAN.
- **Bugfix:** Corrected the malformed plugin icon for improved visualization.
- **New Feature:** Provided an option to set a custom interval for the Preload for Varnish Server as a CRON job.
- **Improvement:** Refined the behavior of Preload to ensure greater stability when used with cURL as a CRON Job.
- **New Feature:** Added statistics displaying total cache size, average lifetime, and total cached entries as HTML pages.
- **Improvement:** Introduced Tabs into the plugin interface for better navigation and user experience.

## 2.1.0 - 2023-06-14

- Added feature to exclude specific URLs from preloading
- Introduced setting to disable the queue reset option
- Implemented support for multiple sitemaps

## 2.0.1 - 2022-12-26

- Update plugin names

## 2.0.0 - 2022-12-23

- Initial Varnish Cache & Preload to static HTML for Craft 4.x

## 1.2.1 - 2023-08-09

- **Fix**: Rectified an issue where the Live Preview functionality in Craft CMS was not displaying the latest changes when the plugin was enabled. The caching mechanism has been updated to recognize and bypass the cache for Live Preview requests, ensuring that users can see their real-time edits without interference from cached versions of the page.

## 1.2.0 - 2023-06-21 [CRITICAL]

- **Enhancement**: Implemented an event to trigger automatic cache refresh when entries are updated or undergo modifications.
- **Fix**: Rectified an issue with incorrect class import for an event, ensuring the cache refreshes automatically after a URI or Slug update.

## 1.1.4 - 2023-06-21

- **Enhancement**: Introduced an event to automatically refresh the cache after a URI or Slug update.
- **Feature**: Updated README with a new section answering a common question about cache clearing during a plugin update.
- **Feature**: Modified the plugin icon in the README to PNG format for compatibility with both dark and light modes.

## 1.1.2 - 2023-06-18

- **Enhancement**: Revamped Statistics Tab and improved descriptions for better readability in the plugin.
- **Feature**: Introduced a list view of available data on the Statistics Tab for each cached page.
- **Feature**: Incorporated execution time statistics for single Preload Job of cached page.
- **Feature**: Integrated curl request time statistics for the first access to a cached page.
- **Feature**: Added overall data statistics including execution of single preload job and request times.
- **Fix**: Updated CHANGELOG and addressed minor bugs.
- **Enhancement**: Refactored plugin code for better readability.
- **Feature**: Introduced an option for delay-free preload.

## 1.1.1 - 2023-06-17

- **Bugfix**: Modified the cURL mechanism to prevent the display of Varnish purge information.
- **Bugfix**: Fixed an issue within the Varnish cache clearing function.

## 1.1.0 - 2023-06-17

- **Improvement:** Refactored many plugin functions for enhanced performance and clarity.
- **New Feature:** Added option to disable CRON jobs as per user preference.
- **New Feature:** Introduced the ability to disable Varnish Server Cache clearing, and changed the default method to BAN.
- **Bugfix:** Corrected the malformed plugin icon for improved visualization.
- **New Feature:** Provided an option to set a custom interval for the Preload for Varnish Server as a CRON job.
- **Improvement:** Refined the behavior of Preload to ensure greater stability when used with cURL as a CRON Job.
- **New Feature:** Added statistics displaying total cache size, average lifetime, and total cached entries as HTML pages.
- **Improvement:** Introduced Tabs into the plugin interface for better navigation and user experience.
- This version is equivalent to version 2.2.0 from the Craft CMS 4.x branch.

## 1.0.0 - 2023-06-14

- Initial release of Varnish Cache & Preload to static HTML Helper for Craft CMS 3.x.
- This version is equivalent to version 2.1.0 from the Craft CMS 4.x branch.
