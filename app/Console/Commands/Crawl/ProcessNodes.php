<?php

namespace FalconSearch\Console\Commands\Crawl;

use Elasticsearch\Client;
use Illuminate\Console\Command;
use Illuminate\Contracts\Redis\Database;

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
     * @var \DOMDocument
     */
    protected $dom;

    /**
     * Create a new command instance.
     *
     * @param Database     $redis
     * @param Client       $client
     * @param \DOMDocument $dom
     */
    public function __construct(Database $redis, Client $client, \DOMDocument $dom)
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
        $count = $limit = 10;
        while ($limit > 0) {
            $url = $this->redis->rPop('nodes-queue');
            $cachePage = storage_path('caches/' . md5($url) . '.html');

            if (!file_exists($cachePage)) {
                try {
                    $htmlContent = file_get_contents($url);
                } catch (\Exception $e) {
                    $this->error("Cannot get content of $url");

                    return;
                }
                file_put_contents($cachePage, html_entity_decode($htmlContent));
            }

            @$this->dom->loadHTMLFile($cachePage);

            $title = $this->dom->getElementsByTagName('title')->item(0)->textContent;
            $content = $this->getContent(
                $this->cleanContent($this->dom->getElementsByTagName('body')->item(0))
            );

            $this->saveNode($title, $content, $url);
            $limit--;
        }
        $this->info("$count url(s) processed.");
    }

    /**
     * @param \DOMNode $node
     * @return \DOMNode|null
     */
    protected function cleanContent(\DOMNode $node)
    {
        if ($node->hasChildNodes()) {
            $blackList = [];

            /** @var \DOMNode $child */
            foreach ($node->childNodes as $child) {
                if (!$child instanceof \DOMElement) {
                    continue;
                }

                /** @var \DOMElement $child */
                if ($this->shouldBeIgnored($child)) {
                    array_push($blackList, $child);
                } else {
                    $newChild = $this->cleanContent($child);
                    if (is_null($newChild)) {
                        array_push($blackList, $child);
                    } else {
                        $node->replaceChild($newChild, $child);
                    }
                }
            }

            $node = $this->removeBlackNodes($node, $blackList);
        } elseif (empty($node->textContent)) {
            return null;
        }

        return $node;
    }

    protected function shouldBeIgnored(\DOMElement $node)
    {
        return in_array($node->tagName, $this->ignoringTags);
    }

    protected function removeBlackNodes(\DOMNode $node, array $blackList)
    {
        foreach ($blackList as $blackNode) {
            $node->removeChild($blackNode);
        }

        return $node;
    }

    protected function getContent(\DOMNode $content)
    {
        $cleanContent = '';
        if ($content instanceof \DOMElement && $content->hasChildNodes()) {
            foreach ($content->childNodes as $childNode) {
                $subContent = $this->getContent($childNode);

                if (!empty($subContent)) {
                    $cleanContent .= " " . $subContent;
                }
            }
        } elseif (!empty($content->textContent)) {
            $cleanContent .= " " . $content->textContent;
        }

        return trim(preg_replace('/\s+/', ' ', $cleanContent));
    }

    protected function saveNode($title, $content, $url)
    {
        $hashId = md5($url);
        $params = [
            'index' => 'sites',
            'type'  => 'default',
            'id'    => $hashId,
            'body'  => [
                'title'    => $title,
                'content'  => $content,
                'hash_id'  => $hashId,
                'original' => [
                    'title'   => $title,
                    'content' => $content,
                    'url'     => $url
                ]
            ]
        ];
        try {
            $response = $this->client->index($params);
            if (!$response['created']) {
                $this->info("Content of $url has updated. [version: {$response['_version']}]");
            } else {
                $this->info("Content of $url indexed.");
            }
        } catch (ElasticsearchException $e) {
            $this->error("Cannot Index content of $url");
            $this->database->lpush('failed_nodes', json_encode([
                'error'  => $e->getMessage(),
                'params' => $params
            ]));
            $this->redis->lPush('failed-urls', $url);
        }
    }

}
