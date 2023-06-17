# Varnish Cache with Preload (Preheat) to static HTML Changelog

## 1.1.0 2023-06-17

- **Improvement:** Refactored many plugin functions for enhanced performance and clarity.
- **New Feature:** Added option to disable CRON jobs as per user preference.
- **New Feature:** Introduced the ability to disable Varnish Server Cache clearing, and changed the default method to BAN.
- **Bugfix:** Corrected the malformed plugin icon for improved visualization.
- **New Feature:** Provided an option to set a custom interval for the Preload for Varnish Server as a CRON job.
- **Improvement:** Refined the behavior of Preload to ensure greater stability when used with cURL as a CRON Job.
- **New Feature:** Added statistics displaying total cache size, average lifetime, and total cached entries as HTML pages.
- **Improvement:** Introduced Tabs into the plugin interface for better navigation and user experience.
- This version is equivalent to version 2.2.0 from the Craft CMS 4.x branch.

## 1.0.0 2023-06-14

- Initial release of Varnish Cache with Preload (Preheat) to static HTML Helper for Craft CMS 3.x.
- This version is equivalent to version 2.1.0 from the Craft CMS 4.x branch.