<?php

namespace FalconSearch\Console\Commands\Crawl;

use Illuminate\Console\Command;
use Illuminate\Contracts\Redis\Database;
use webignition\RobotsTxt\Directive\Directive;
use webignition\RobotsTxt\File\Parser;

class QueueNodes extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:get-nodes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue seeds\' sitemaps.';

    /**
     * @var Parser
     */
    protected $parser;

    /**
     * @var Database
     */
    protected $redis;

    protected $dept = 0;

    /**
     * Create a new command instance.
     *
     * @param Parser   $parser
     * @param Database $redis
     */
    public function __construct(Parser $parser, Database $redis)
    {
        parent::__construct();
        $this->parser = $parser;
        $this->redis = $redis;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $seeds = config('seeds');

        foreach ($seeds as $seed) {
            try {
                $this->parser->setSource(file_get_contents($seed));
            } catch (\Exception $e) {
                continue;
            }

            /** @var Directive[] $sitemaps */
            $sitemaps = $this->parser->getFile()
                                     ->directiveList()
                                     ->filter(['field' => 'sitemap'])
                                     ->get();

            $urls = [];
            foreach ($sitemaps as $sitemap) {
                $urls = array_merge($urls, $this->gatherLinks((string) $sitemap->getValue()));
            }

            $this->publishLinks($urls);
        }
    }

    protected function gatherLinks($sitemapUrl)
    {
        try {
            $sitemapObject = new \SimpleXMLElement(file_get_contents($sitemapUrl));

            return $this->fetchUrls($sitemapObject);
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function fetchUrls(\SimpleXMLElement $sitemapObject)
    {
        $urlsList = [];

        foreach ($sitemapObject->children() as $child) {
            $urlsList[] = (string) $child->loc;
        }

        return $urlsList;
    }

    protected function publishLinks($urls, $deep = true)
    {
        foreach ($urls as $url) {
            if ($this->isSitemap($url)) {
                if ($deep) {
                    $this->publishLinks($this->fetchUrls(
                        new \SimpleXMLElement(file_get_contents($url))
                    ), false);
                }
                continue;
            }

            $this->redis->publish('nodes-channel', $url);
        }
    }

    protected function isSitemap($url)
    {
        return ends_with($url, '.xml');
    }

}
