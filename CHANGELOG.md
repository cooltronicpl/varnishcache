# Varnish Cache & Preload to static HTML Changelog

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