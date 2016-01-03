<?php

return [

    'index' => 'sites',
    'body'  => [
        'settings' => [
            'number_of_shards'   => 20,
            'number_of_replicas' => 0,
        ],
        'mapping'  => [
            'default' => [
                'properties' => [
                    'title'    => [
                        'type'     => 'string',
                        'index'    => 'analyzed',
                        'analyzer' => 'english'
                    ],
                    'content'  => [
                        'type'     => 'string',
                        'index'    => 'analyzed',
                        'analyzer' => 'english'
                    ],
                    'hash_id'  => ['type' => 'string', 'index' => 'not_analyzed'],
                    'date'     => ['type' => 'date', 'format' => 'date_time_no_millis'],
                    'original' => [
                        'type'       => 'object',
                        'properties' => [
                            'title'   => ['type' => 'string', 'index' => 'no'],
                            'content' => ['type' => 'string', 'index' => 'no'],
                            'url'     => ['type' => 'string', 'index' => 'no']
                        ]
                    ]
                ]
            ]
        ]
    ]

];