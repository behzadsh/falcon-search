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
    protected $signature = 'elastic:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $this->info('Checking for index existance...');

        try {
            $mapping = $this->client->indices()->getMapping([
                'index' => 'sites',
                'type'  => 'default'
            ]);

            $this->info('Index exists. checking for mapping existance...');

            if (empty($mapping)) {
                $this->info('Add mapping to index...');
                $this->client->indices()->putMapping($this->getMappingParams());
                $this->info('Index mapping completed!');
            } else {
                $this->info('Mapping exists. Continue indexing logs...');
            }
        } catch (Missing404Exception $e) {
            $this->info('Index not found, createing now...');
            $this->client->indices()->create($this->config->get('elastic'));
            $this->client->indices()->putMapping($this->getMappingParams());
            $this->info('Index created with settings and mappings!');
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
}
