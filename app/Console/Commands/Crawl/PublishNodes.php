<?php

namespace FalconSearch\Console\Commands\Crawl;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
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

    /**
     * @var Repository
     */
    protected $config;

    protected $dept = 0;

    protected $counter = [
        'failed'   => 0,
        'succeeed' => 0,
        'skipped'  => 0
    ];

    /**
     * Create a new command instance.
     *
     * @param Parser     $parser
     * @param Database   $redis
     * @param Repository $config
     */
    public function __construct(Parser $parser, Database $redis, Repository $config)
    {
        parent::__construct();
        $this->parser = $parser;
        $this->redis = $redis;
        $this->config = $config;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $seeds = $this->config->get('seeds');

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
            if (empty($sitemaps)) {
                $this->info("No sitemap found in $seed. trying to guess sitemap url...");
                $urls = $this->gatherLinks($this->guessSitemap($seed));
            } else {
                $this->info(count($sitemaps) . " sitemap(s) found in $seed");
                foreach ($sitemaps as $sitemap) {
                    $urls = array_merge(
                        $urls,
                        $this->gatherLinks((string) $sitemap->getValue())
                    );
                }
            }

            $count = $this->publishLinks($urls);
            $this->info("$count urls published on queue for $seed");
        }

        $this->printResultTable();
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
            $lastmod = strtotime($child->lastmod);

            if (!$lastmod || time() - $lastmod < (365 * 24 * 60 * 60)) {
                $urlsList[] = [
                    'lastmod' => ($lastmod) ?: null,
                    'url'     => (string) $child->loc
                ];
            } else {
                $this->counter['skipped']++;
            }
        }

        return $urlsList;
    }

    protected function publishLinks($urlSet, $count = 0, $deep = true)
    {
        foreach ($urlSet as $set) {
            if ($count >= $this->config->get('settings.publish.limit')) {
                $this->info("Per-node-link limit reached!");
                break;
            }

            $url = $set['url'];
            $date = $set['lastmod'];

            if ($this->isSitemap($url)) {
                if ($deep) {
                    $count = $this->publishLinks($this->gatherLinks($url), $count, false);
                }
                continue;
            }

            $result = $this->redis->lPush('nodes-queue', json_encode(compact('url', 'date')));

            if ($result > 0) {
                $count++;
                $this->counter['succeeed']++;
            } else {
                $this->counter['failed']++;
            }
        }

        return $count;
    }

    protected function isSitemap($url)
    {
        return ends_with($url, '.xml');
    }

    protected function guessSitemap($url)
    {
        $urlData = parse_url($url);

        return "http://{$urlData['host']}/sitemap.xml";
    }

    protected function printResultTable()
    {
        $this->info("Command finished with the following results:");

        $headers = ['Published URLs', 'Failed URLs', 'Skipped URLs', 'Total URLs'];

        $rows = [
            [
                $this->counter['succeeed'],
                $this->counter['failed'],
                $this->counter['skipped'],
                $this->counter['succeeed'] + $this->counter['failed'] + $this->counter['skipped']
            ]
        ];

        $this->table($headers, $rows);
    }

}
