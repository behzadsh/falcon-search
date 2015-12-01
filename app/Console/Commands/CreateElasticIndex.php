<?php

namespace FalconSearch\Console\Commands;

use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;

class CreateElasticIndex extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elastic:create {--f|flush : Flush mapping an remap indexes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Try to create index and mapping for storing sites';

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Repository
     */
    protected $config;

    /**
     * Create a new command instance.
     *
     * @param Client     $client
     * @param Repository $config
     */
    public function __construct(Client $client, Repository $config)
    {
        parent::__construct();
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->option('flush')) {
            $this->info('Flushing indexes...');
            try {
                $this->client->indices()->delete(['index' => 'sites']);
                $this->info('Index deleted. Recreating now...');
            } catch (Missing404Exception $e) {
                $this->info('No index found to flush. Creating now...');
            } finally {
                $this->createIndex();
            }
        } else {
            $this->info('Checking for index existance...');
            try {
                $mapping = $this->client->indices()->getMapping([
                    'index' => 'sites',
                    'type'  => 'default'
                ]);

                $this->info('Index exists. Checking for mapping existance...');

                if (empty($mapping)) {
                    $this->info('Add mapping to index...');
                    $this->client->indices()->putMapping($this->getMappingParams());
                    $this->info('Index mapping completed!');
                } else {
                    $this->info('Mapping exists. Continue indexing logs...');
                }
            } catch (Missing404Exception $e) {
                $this->info('Index not found. Createing now...');
                $this->createIndex();
            }
        }
    }

    /**
     * @return array
     */
    protected function getMappingParams()
    {
        return [
            'index' => 'sites',
            'type'  => 'default',
            'body'  => $this->config->get('elastic.body.mapping.default')
        ];
    }

    protected function createIndex()
    {
        $this->client->indices()->create($this->config->get('elastic'));
        $this->client->indices()->putMapping($this->getMappingParams());
        $this->info('Index created with settings and mappings!');
    }
}
