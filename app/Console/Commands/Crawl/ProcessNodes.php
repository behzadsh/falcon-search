<?php

namespace FalconSearch\Console\Commands\Crawl;

use Illuminate\Console\Command;
use Illuminate\Contracts\Redis\Database;
use PHPHtmlParser\Dom;

class ProcessNodes extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crawl:process-nodes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crawl nodes data and index them';

    protected $ignoringTags = [
        'head',
        'style',
        'script',
        'noscript',
        'iframe',
        'form',
        'header',
        'nav',
        'navbar',
        'aside',
        'button',
        'meta',
        'link',
        'textarea'
    ];

    /**
     * @var Database
     */
    protected $redis;

    /**
     * @var Dom
     */
    protected $dom;

    /**
     * Create a new command instance.
     *
     * @param Database $redis
     * @param Dom      $dom
     */
    public function __construct(Database $redis, Dom $dom)
    {
        parent::__construct();
        $this->redis = $redis;
        $this->dom = $dom;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->redis->subscribe(['nodes-channel'], function ($url) {
            $cachePage = storage_path('caches/' . md5($url) . '.html');
            if (!file_exists($cachePage)) {
                file_put_contents($cachePage, file_get_contents($url));
            }
            $this->dom->load($cachePage);
            $this->info($this->stripTags($this->cleanContent($this->dom->root)));
        });
    }

    protected function cleanContent(Dom\AbstractNode $node)
    {
        if ($node->hasChildren()) {
            foreach ($node->getChildren() as &$child) {
                if ($this->shoulIgnore($child->getTag()->name())) {
                    $node = $node->removeChild($child->id());
                } else {
                    $child = $this->cleanContent($child);
                }
            }
        }

        return $node;
    }

    protected function shoulIgnore($name)
    {
        return in_array($name, $this->ignoringTags);
    }

    protected function stripTags($content)
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/<[^>]+>/', ' ', $content)));
    }

}
