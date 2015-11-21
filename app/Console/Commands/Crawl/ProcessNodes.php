<?php

namespace FalconSearch\Console\Commands\Crawl;

use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\ElasticsearchException;
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
        'footer',
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
     * @var Client
     */
    protected $client;

    /**
     * @var Dom
     */
    protected $dom;

    /**
     * Create a new command instance.
     *
     * @param Database $redis
     * @param Client   $client
     * @param Dom      $dom
     */
    public function __construct(Database $redis, Client $client, Dom $dom)
    {
        parent::__construct();
        $this->redis = $redis;
        $this->database = new \Predis\Client();
        $this->client = $client;
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
                file_put_contents($cachePage, html_entity_decode(file_get_contents($url)));
            }
            $this->dom->load($cachePage);

            $title = $this->dom->getElementsByTag('title')[0]->text;
            $content = $this->stripTags($this->cleanContent($this->dom->root));

            $this->saveNode($title, $content, $url);
        });
    }

    protected function cleanContent(Dom\AbstractNode $node)
    {
        if ($node->hasChildren()) {
            foreach ($node->getChildren() as &$child) {
                if ($this->shoulIgnore($child)) {
                    $node = $node->removeChild($child->id());
                } else {
                    $child = $this->cleanContent($child);
                }
            }
        } elseif (empty($node->text)) {
            return null;
        }

        return $node;
    }

    protected function shoulIgnore(Dom\AbstractNode $node)
    {
        return in_array($node->getTag()->name(), $this->ignoringTags);
    }

    protected function stripTags($content)
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/<[^>]+>/', ' ', $content)));
    }

    protected function saveNode($title, $content, $url)
    {
        $hashId = md5($url);
        $this->database->hMSet('sites:' . $hashId, [
            'title'   => $title,
            'content' => $content,
            'url'     => $url
        ]);
        $this->comment("Content of $url stored/updated in redis");

        $params = [
            'index' => 'sites',
            'type'  => 'default',
            'id'    => $hashId,
            'body'  => [
                'title'   => $title,
                'content' => $content,
                'hash_id' => $hashId
            ]
        ];

        try {
            $response = $this->client->index($params);
            if (!$response['created']) {
                $this->comment("Content of $url has updated. [version: {$response['_version']}]");
            } else {
                $this->comment("Content of $url indexed.");
            }
        } catch (ElasticsearchException $e) {
            $this->error("Cannot Index content of $url");
            $this->database->lpush('failed_nodes', json_encode([
                'error'  => $e->getMessage(),
                'params' => $params
            ]));
        }
    }

}
