<?php

namespace FalconSearch\Console\Commands\Crawl;

use Carbon\Carbon;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\ElasticsearchException;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
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
     * @var Repository
     */
    protected $config;

    protected $counter = [
        'indexed'      => 0,
        'updated'      => 0,
        'empty_url'    => 0,
        'failed_url'   => 0,
        'failed_index' => 0
    ];

    /**
     * Create a new command instance.
     *
     * @param Database   $redis
     * @param Client     $client
     * @param Repository $config
     */
    public function __construct(Database $redis, Client $client, Repository $config)
    {
        parent::__construct();
        $this->redis = $redis;
        $this->client = $client;
        $this->dom = new \DOMDocument();
        $this->config = $config;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $limit = $this->config->get('settings.cron.limit');
        while ($limit > 0) {
            $data = json_decode($this->redis->rPop('nodes-queue'), true);

            if (!isset($data['url']) || (filter_var($data['url'], FILTER_VALIDATE_URL) === FALSE)) {
                $this->counter['failed_url']++;
                continue;
            }

            $url = $data['url'];
            $date = (isset($data['date']) || $data['date']) ? $data['date'] : null;

            $cachePage = storage_path('caches/' . md5($url) . '.html');

            if (!file_exists($cachePage)) {
                try {
                    $htmlContent = file_get_contents($url);
                } catch (\Exception $e) {
                    $this->error("Cannot get content of $url");
                    $this->counter['failed_url']++;

                    continue;
                }
                file_put_contents($cachePage, html_entity_decode($htmlContent));
            }

            @$this->dom->loadHTMLFile($cachePage);

            $title = $this->dom->getElementsByTagName('title')->item(0)->textContent;
            $content = $this->getContent(
                $this->pruneContent($this->dom->getElementsByTagName('body')->item(0))
            );

            $this->saveNode($title, $content, $url, $date);
            $limit--;
        }

        $this->printResultTable();
    }

    /**
     * @param \DOMNode $node
     * @return \DOMNode|null
     */
    protected function pruneContent($node)
    {
        if (is_null($node)) {
            return null;
        }

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
                    $newChild = $this->pruneContent($child);
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

    /**
     * @param \DOMNode $content
     * @return string
     */
    protected function getContent($content)
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

    protected function saveNode($title, $content, $url, $date)
    {
        $content = $this->cleanContent($content);

        if (is_null($content)) {
            $this->counter['empty_url']++;
            return;
        }

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
                    'url'     => $url,
                    'date'    => ($date) ? Carbon::createFromTimestamp($date)->toW3cString() : null
                ]
            ]
        ];

        try {
            $response = $this->client->index($params);
            if (!$response['created']) {
                $this->counter['updated']++;
            } else {
                $this->counter['indexed']++;
            }
        } catch (ElasticsearchException $e) {
            $this->error("Cannot Index content of $url");
            $this->redis->lpush('failed-nodes', json_encode([
                'error'  => $e->getMessage(),
                'params' => $url
            ]));
            $this->counter['failed_index']++;
        }
    }

    protected function printResultTable()
    {
        $this->info("Cron finished with the following results:");

        $headers = [
            'indexed urls', 'failed urls', 'no content urls', 'failed indexed', 'updated indices'
        ];

        $rows = [
            [
                $this->counter['indexed'],
                $this->counter['failed_url'],
                $this->counter['empty_url'],
                $this->counter['failed_index'],
                $this->counter['updated']
            ]
        ];

        $this->table($headers, $rows);
    }

    protected function cleanContent($content)
    {
        return preg_replace('/[\x00-\x1F\x80-\xFF]/', '', utf8_encode($content));
    }

}
