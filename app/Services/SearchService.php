<?php
namespace FalconSearch\Services;

use Carbon\Carbon;
use Elasticsearch\Client;
use Illuminate\Support\Str;

class SearchService
{

    const SIZE = 10;

    /**
     * @var Client
     */
    protected $client;

    protected $phrases = [];

    protected $mustPhrases = [];

    protected $mustNotPhrases = [];

    protected $mustTerms = [];

    protected $mustNotTerms = [];

    protected $terms = [];

    protected $page;

    /**
     * SearchService constructor.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function searchDocs($query, $page = 1)
    {
        $this->page = $page;
        $this->processQuery($query);

        return $this->runQuery();
    }

    protected function processQuery($query)
    {
        $pattern = "/\"[^\"]*\"|\+\"[^\"]*\"|-\"[^\"]*\"|[\S]+/";
        preg_match_all($pattern, $query, $matches);
        $matches = $matches[0];

        foreach ($matches as $match) {
            $this->checkPhrase($match);
        }
    }

    protected function checkPhrase($phrase)
    {
        if (preg_match("/^\"(.+)\"$/", $phrase, $match)) {
            $this->phrases[] = $match[1];
        } elseif (preg_match("/^\+\"(.+)\"$/", $phrase, $match)) {
            $this->mustPhrases[] = $match[1];
        } elseif (preg_match("/^-\"(.+)\"$/", $phrase, $match)) {
            $this->mustNotPhrases[] = $match[1];
        } elseif (preg_match("/^\+(.+)/", $phrase, $match)) {
            $this->mustTerms[] = $match[1];
        } elseif (preg_match("/^-(.+)/", $phrase, $match)) {
            $this->mustNotTerms[] = $match[1];
        } else {
            $this->terms[] = $phrase;
        }
    }

    protected function runQuery()
    {
        $params = [
            'index'   => 'sites',
            'type'    => 'default',
            'size'    => self::SIZE,
            'from'    => ($this->page - 1) * self::SIZE,
            'body'    => [
                'query'     => [
                    'filtered' => $this->buildQuery()
                ],
                'highlight' => [
                    'order'  => 'score',
                    'fields' => [
                        'content' => [
                            'fragment_size'       => 150,
                            'number_of_fragments' => 3
                        ],
                        'title'   => [
                            'pre_tags'  => ['<em>'],
                            'post_tags' => ['</em>']
                        ]
                    ]
                ]
            ],
            '_source' => ['title', 'date', 'url', 'content']
        ];

        return $this->pruneResponse($this->client->search($params));
    }

    protected function buildQuery()
    {
        return [
            'query'  => [
                'bool' => [
                    'should' => $this->getQueries()
                ]
            ],
            'filter' => [
                'bool' => [
                    'must'     => $this->getMustFilters(),
                    'must_not' => $this->getMustNotFilters()
                ]
            ]
        ];
    }

    protected function getMustFilters()
    {
        $mustFilter = array_merge(
            $this->generateMustTermsFilters(),
            $this->generateMustPhraseFilters()
        );

        return $mustFilter;
    }

    protected function generateMustTermsFilters()
    {
        $filters = [];
        foreach ($this->mustTerms as $term) {
            $filters[] = [
                'multi_match' => [
                    'query'  => $term,
                    'fields' => ['title', 'content']
                ]
            ];
        }

        return $filters;
    }

    protected function generateMustPhraseFilters()
    {
        $filters = [];
        foreach ($this->mustPhrases as $phrase) {
            $filters[] = [
                'multi_match' => [
                    'query'  => $phrase,
                    'fields' => ['title', 'content'],
                    'type'   => 'phrase'
                ]
            ];
        }

        return $filters;
    }

    protected function getMustNotFilters()
    {
        $mustFilter = array_merge(
            $this->generateMustNotTermsFilters(),
            $this->generateMustNotPhraseFilters()
        );

        return $mustFilter;
    }

    protected function generateMustNotTermsFilters()
    {
        $filters = [];
        foreach ($this->mustNotTerms as $term) {
            $filters[] = [
                'multi_match' => [
                    'query'  => $term,
                    'fields' => ['title', 'content']
                ]
            ];
        }

        return $filters;
    }

    protected function generateMustNotPhraseFilters()
    {
        $filters = [];
        foreach ($this->mustNotPhrases as $phrase) {
            $filters[] = [
                'multi_match' => [
                    'query'  => $phrase,
                    'fields' => ['title', 'content'],
                    'type'   => 'phrase'
                ]
            ];
        }

        return $filters;
    }

    protected function getQueries()
    {
        $mustFilter = array_merge(
            $this->generateTermQueries(),
            $this->generatePhraseQueries()
        );

        return $mustFilter;
    }

    protected function generateTermQueries()
    {
        $filters = [
            [
                'multi_match' => [
                    'query'  => implode(" ", $this->terms),
                    'fields' => ['title', 'content']
                ]
            ]
        ];

        return $filters;
    }

    protected function generatePhraseQueries()
    {
        $filters = [];
        foreach ($this->phrases as $phrase) {
            $filters[] = [
                'multi_match' => [
                    'query'  => $phrase,
                    'fields' => ['title', 'content'],
                    'type'   => 'phrase'
                ]
            ];
        }

        return $filters;
    }

    protected function pruneResponse($search)
    {
        unset($search['_shards']);

        foreach ($search['hits']['hits'] as $key => $hit) {
            $date = $hit['_source']['date'];
            if (!is_null($date)) {
                $newDate = Carbon::createFromFormat('Y-m-d\TG:i:sP', $date);
                $search['hits']['hits'][$key]['_source']['date'] = $newDate->format('M j, Y');
            }

            $search['hits']['hits'][$key]['_source']['content'] = str_limit($hit['_source']['content'], 300);
        }

        return $search;
    }

}