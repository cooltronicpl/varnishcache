# Varnish Cloudflare & Preload Plugin for Craft CMS 4.x

With ❤️ [CoolTRONIC.pl sp. z o.o.](https://cooltronic.pl) presents caching helper solution written by [Pawel Potacki](https://potacki.com). This plugin generates static HTML files from your dynamic CMS projects and purges the Varnish cache, resulting in faster page load times and improved Core Web Vitals.

![Icon](resources/black.png#gh-light-mode-only)
![Icon](resources/white.png#gh-dark-mode-only)

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Usage](#usage)
  - [Preloading Server Cache from Sitemap](#preloading-server-cache-from-sitemap)
  - [Configuring Varnish Cache](#configuring-varnish-cache)
  - [Using Varnish Cache](#using-varnish-cache)
  - [How to use Cloudflare with this plugin?](#how-to-use-cloudflare-with-this-plugin)
  - [Disabling or Clearing Some URL](#disabling-or-clearing-some-url)
- [FAQ](#faq)
  - [Q: Are all cache files deleted when updating an entry, or only the ones with a relation?](#q-are-all-cache-files-deleted-when-updating-an-entry-or-only-the-ones-with-a-relation)
  - [Q: The installation fails and plugin does not work.](#q-the-installation-fails-and-plugin-does-not-work)
  - [Q: How to set Varnish Server?](#q-how-to-set-varnish-server)
  - [Q: Which branch should I install for my Craft CMS?](#q-which-branch-should-i-install-for-my-craft-cms)
  - [Q: My Preloading CRON failed. What could be the reason?](#q-my-preloading-cron-failed-what-could-be-the-reason)
  - [Q: Some URL Preloading failed?](#q-some-url-preloading-failed)
  - [Q: How to reset Cache?](#q-how-to-reset-cache)
  - [Q: What if the plugin's control panel settings don't match the screenshots?](#q-what-if-the-plugins-control-panel-settings-dont-match-the-screenshots)
- [Support](#support)
- [Contribution](#contribution)
- [License](#license)
- [Changelog](#changelog)

## Features

- **Static HTML Generation**: Generates static HTML files from your dynamic CMS projects, improving page load times and Core Web Vitals.
- **Varnish Cache Purging**: Purges the Varnish cache, ensuring that your website always serves the most recent version of your files.
- **Cloudflare Cache Purging**: The plugin clears the Cloudflare cache when you update your content, so your visitors always see the latest version of your website.
- **Caching Helper Solution**: Provides a caching helper solution, making it easy to manage your cache and improve the performance of your website.

## Installation

To install this plugin, copy the following command to your terminal:

```
composer require cooltronicpl/varnishcache
```

You can also install the plugin directly from the [Craft CMS plugin store](https://plugins.craftcms.com/varnishcache/).

## Usage

This section provides detailed instructions and examples on how to use the Varnish Cloudflare & Preload to static HTML Helper Plugin.

### Preloading Server Cache from Sitemap

The preloading of the server cache from the sitemap is initiated once the settings in the plugin options are enabled. The plugin adds the target URLs from the sitemap to a queue for preloading. However, if CraftCMS 4 or 3 is not active, the next iteration of preload may be paused. After the next login to the admin panel, the preload cron will resume. This ensures that all your sites are continuously preloaded in the Varnish Server with PURGE and the static HTML Cache is recreated. For sites with long initial generation times, such as those generating PDFs with your plugin, the preload is initiated on the first website listed in the sitemap.xml. The duration between preloads can be adjusted from the default value of 60 minutes.

### Configuring Varnish Cache

The plugin works out of the box and does not require special cache tags, unless you want to disable Varnish. If DevMode in Craft CMS is enabled, you will need to manually enable the plugin by activating the 'Force On' setting in the plugin. You can also exclude certain URL paths from generating HTML files.

### Using Varnish Cache

Varnish Cache has a settings page where you can enable or disable it and flush the cache. If the plugin is working correctly, you will see the cached files in the `storage/runtime/varnishcache/` folder. To check the performance improvement, please use the browser inspector. There, you will be able to see the improved loading times.

### How to use Cloudflare with this plugin?

To use Cloudflare with this plugin, you need to enter your Cloudflare account details in the plugin settings. You need the following information:

- Your Cloudflare API Key, which you can find in your [Cloudflare Account Profile].
- Your Cloudflare Zone ID, which you can find in your [Cloudflare dashboard] under the Overview tab for your domain.
- Your Cloudflare Email Address, which is the email address that you use to log in to your Cloudflare account.

After you enter your Cloudflare account details, you should test the connection to Cloudflare by clicking the “Test Cloudflare connection” button. If the connection is successful, you will see a notice that says “Cloudflare connection successful”. This means that the plugin can communicate with Cloudflare and clear the Cloudflare cache when you clear the Craft CMS cache.

### Disabling or Clearing Some URL

To disable Varnish Cache for a specific page, you can disable this slug in the admin panel or prevent the creation of HTML files for the entire website using REGEX.

```
{% header "Cache-Control: no-cache" %}
{% header "Pragma: no-cache" %}
```

To clear a specific URL with Varnish and some linked HTML files, this is executed by the Craft Job Queue with a delay that you specify as the last argument in the `clearCustomUrlUriTimeout` function.

Example:

```
{{ craft.varnish.clearCustomUrlUriTimeout("test", "https://domain.com/test/",10) }}
```

This command will clear the cache for the URL https://domain.com/test/ after a delay of 10 seconds.

## FAQ

### Q: Are all cache files deleted when updating an entry, or only the ones with a relation?

A: Only related cache files will be deleted and sites preloaded via Varnish after an update.

### Q: The installation fails and plugin does not work.

A: Make sure that the folder `storage/runtime/varniscache` is created and there are read/write permissions.

### Q: How to set Varnish Server?

A: You can use the vcl file from [our project](https://github.com/cooltronicpl/-ispconfig3-varnish/blob/master/etc/varnish/default.vcl) which contains a modified WordPress Purge mechanism for Craft CMS.

### Q: Which branch should I install for my Craft CMS?

A: The 1.x branch is suitable for Craft CMS 3, whereas the 2.x branch is for Craft CMS 4.

### Q: My Preloading CRON failed. What could be the reason?

A: The failure could be due to the inaccessibility of the `sitemap.xml` file or poorly formatted entries within it. Please check the `sitemap.xml` file for any potential issues.

### Q: Some URL Preloading failed?

A: If some URL preloading is failing, you can try to increase the preload time in the Preloading Tab. If the problem persists, consider disabling the preload for the affected page.

### Q: How to reset Cache?

A: To reset the cache, navigate to the settings and enable the **Purge all Cache now?** option. Don't forget to click "Save" to apply the changes.

### Q: What if the plugin's control panel settings don't match the screenshots?

A: You might need to clear the Craft CMS Cache. Navigate to the **Utilities** Tab, select **Caches**, and then click on **Clear cache**.

## Support

If you encounter any issues or have questions about the plugin, please create an issue in the GitHub repository or contact us directly at craft@cooltronic.pl.

## Contribution

We welcome contributions to the Varnish Cloudflare & Preload to static HTML Helper plugin. Please read our contribution guidelines and submit your pull requests.

## License

This project is licensed under the Craft License. See the [LICENSE.md](https://github.com/cooltronicpl/varnishcache/LICENSE.md) file for details.

## Changelog

See the [CHANGELOG.md](https://github.com/cooltronicpl/varnishcache/blob/master/CHANGELOG.md) file for a list of changes in each version of the plugin.

---

Copyright © [CoolTRONIC.pl sp. z o.o.](https://cooltronic.pl)
