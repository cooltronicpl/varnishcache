<?php
namespace cooltronicpl\varnishcache\jobs;

use Craft;
use craft\queue\BaseJob;
use craft\queue\QueueInterface;


class PreloadCacheJob extends BaseJob
{
    public $url;

    public function execute($queue): void
    {
        // Preload the cache for the URL
        $ch = curl_init();
        $varnishhost = 'Host: ' . $_SERVER['SERVER_NAME'];
        curl_setopt($ch, CURLOPT_HTTPHEADER, array($varnishhost));
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    
        if(curl_exec($ch) === false) {
            $error = curl_error($ch);
            \Craft::error('Preload Error: ' . var_dump($error));
            throw new \Exception('Failed to preload cache for URL: ' . $this->url . '. Error: ' . $error);
        } else {
             \Craft::info('Preload - Successful for URL: ' . $this->url);
        }
    
        curl_close($ch);
    }
    protected function defaultDescription(): string
    {
        return 'Preloading cache for URL: ' . $this->url;
    }
}