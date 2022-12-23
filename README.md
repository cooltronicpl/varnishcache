
# Varnish Cache Helper Plugin for CraftCMS 4.x with our ISP Config 3 Varnish SSL plugin

With ❤️ [CoolTRONIC.pl sp. z o.o.](https://cooltronic.pl) presents caching helper solution written by [Pawel Potacki](https://potacki.com)

Plugin can cache pages to HTML files and boost website performance by making Varnish Cache preloaded from sitemap on your server. You can use it with our [solution for our TSPConfig 3 hosting panel](https://github.com/cooltronicpl/-ispconfig3-varnish). Maybe you want to try run CraftCMS on WP Engine with Varnish (untested).

<img src="https://github.com/cooltronicpl/varnishcache/blob/master/src/icon.svg" width="192" />

This plugin generates static HTML files and purges Varnish Cache from your dynamic Craft CMS project. When HTML files generated, your webserver will serve that file instead of processing heavier and running every time PHP and SQL.

Your visitors and Google served without running any code or database queries. Everything preloaded from your sitemap. Cached files and Varnish improve your Core Web Vitals dramatically. That could be some POST request (like AJAX forms) like:

* sections with a login, carts, some contact forms should not beeing cached, it needs testing
* the admin panel is also NOT cached

## Disabling HTML Cache

You can disable some URLs, or all website by regex.

## Requirements

Version 2.x of plugin requires Craft CMS 4.x or later.

## Varnish Cache HTML files overview

Creates HTML Cached page for any non-cp GET request for the duration of one hour (60 minutes, wchich is configurable) or until an entry is updated. To work in DEV-mode: use the force option in the settings. You can use preload to make cache automatically, when you need to have sitemap.xml file and point to it in plugin settings (it may be URL from other domain, some XML file).

## Preaload server cache from sitemap

It is runned after enabling settings in plugin options. Now it is added Queue target urls from sitemap is preloaded, but when CraftCMS 4 is not active, next iteration of preload may be sleeped. After logging next time into admin panel, preload cron will resume. So all the time, all of your sites should been preloaded in Varnish Server with PURGE and recreated static HTML Cache. Very long first generated sites in example with [generation of PDFs in your plugin](https://github.com/cooltronicpl/Craft-document-helpers) were run on first website on sitemap.xml website preaload. Duration between preload can changed from default value of 60 minutes.

## Configuring Varnish Cache

Plugin works out of the box no special cache tags are needed, it may be needed when you want to disable Varnish. If DevMode in Craft CMS is enabled, you will have to force enable the plugin by enable the 'Force On' plugin setting. You can also exclude url path(s) from being generated HTML files.

## Using Varnish Cache

Varnish Cache has a settings page where you can enable or disable it and flush the cache. If the plugin works correctly you will see the cached files in storage/runtime/varnishcache/ folder. To check the performance improvement please use the browser inspector. There you will be able to see that the loading times are improved.

## Disable or clear some URL

To disable actual page Varnish Cache, also you can disable this slug in admin panel or all website HTML files creation via REGEX.

```
{% header "Cache-Control: no-cache" %}
{% header "Pragma: no-cache" %}
```

To clear some URL with Varnish and some linked HTML files, this is executed by Craft Job Queue with delay you pass with last argument: ```clearCustomUrlUriTimeout(SLUG to unlink HTML file, VARNISH URL to clear,timeout)```

```
{{ craft.varnish.clearCustomUrlUriTimeout("test", "https://domain.com/test/",10) }}
```

## License

### Craft License

Copyright © [CoolTRONIC.pl sp. z o.o.](https://cooltronic.pl) more in [LICENSE.md file](https://github.com/cooltronicpl/varnishcache/LICENSE.md).

## FAQ

**Q:** Are all cache files deleted when updating an entry, or only the ones with a relation?
**A:** Only related cache files will be deleted and sites preloaded via Varnish after an update.

**Q:** The installation fails and plugin does not work. **  
**A:** Make sure that the folder `storage/runtime/varniscache` is created and there are read/write permissions.
