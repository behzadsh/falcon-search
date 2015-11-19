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
    protected $signature = 'crawl:queue-nodes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue seeds\' nodes from seeds\' sitemap.';

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
            $this->info("Reading '$seed'");
            try {
                $this->parser->setSource(file_get_contents($seed));
            } catch (\Exception $e) {
                $this->warn("Error reading '$seed'. File may not exists.");
                continue;
            }

            $this->info("Filtering sitemaps from robot.txt");
            /** @var Directive[] $sitemaps */
            $sitemaps = $this->parser->getFile()
                                     ->directiveList()
                                     ->filter(['field' => 'sitemap'])
                                     ->get();

            $this->info('Getting urls');
            $urls = [];
            foreach ($sitemaps as $sitemap) {
                $urls = array_merge($urls, $this->gatherLinks((string) $sitemap->getValue()));
            }
            $this->info(count($urls) . ' URL(s) found.');

            $this->info('Publish links to the channel');
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
                $this->comment('URL is a sitemap');
                if ($deep) {
                    $this->comment('Getting sitemap links');
                    $this->publishLinks($this->fetchUrls(
                        new \SimpleXMLElement(file_get_contents($url))
                    ), false);
                } else {
                    $this->comment('Ignoring the sitemap due crawl dept');
                }
                continue;
            }

            $this->redis->publish('nodes-channel', $url);
        }
        $this->info(count($urls) . ' URL(s) published to the channel.');
    }

    protected function isSitemap($url)
    {
        return ends_with($url, '.xml');
    }

}
