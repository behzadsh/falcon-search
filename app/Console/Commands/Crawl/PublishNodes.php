<?php

namespace FalconSearch\Console\Commands\Crawl;

use Illuminate\Console\Command;
use Illuminate\Contracts\Redis\Database;
use webignition\RobotsTxt\Directive\Directive;
use webignition\RobotsTxt\File\Parser;

class PublishNodes extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:publish-nodes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish seeds\' nodes on redis queue from seeds\' sitemap.';

    /**
     * @var Parser
     */
    protected $parser;

    /**
     * @var Database
     */
    protected $redis;

    protected $dept = 0;

    protected $count = [
        'failed'   => 0,
        'succeeed' => 0
    ];

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
                $this->warn("Error reading '$seed'. File may not exists.");
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
            $this->info(count($urls) . " sitemap(s) found in $seed");
            $count = $this->publishLinks($urls);
            $this->info("$count urls published on queue for $seed");
        }

        $this->info("{$this->count['succeeed']} node(s) published on queue.");

        if ($this->count['failed'] > 0) {
            $this->warn("{$this->count['failed']} node(s) failed to published on queue.");
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
            if (time() - strtotime($child->lastmod) < (365 * 24 * 60 * 60)) {
                $urlsList[] = (string) $child->loc;
            }
        }

        return $urlsList;
    }

    protected function publishLinks($urls, $count = 0, $deep = true)
    {
        foreach ($urls as $url) {
            if ($this->isSitemap($url)) {
                if ($deep) {
                    $count = $this->publishLinks($this->fetchUrls(
                        new \SimpleXMLElement(file_get_contents($url))
                    ), $count, false);
                }
                continue;
            }

            $result = $this->redis->lPush('nodes-queue', $url);

            if ($result > 0) {
                $count++;
                $this->count['succeeed']++;
            } else {
                $this->count['failed']++;
            }
        }

        return $count;
    }

    protected function isSitemap($url)
    {
        return ends_with($url, '.xml');
    }

}
