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
                    'title'   => [
                        'type'        => 'string',
                        'index'       => 'analyzed',
                        'analyzer'    => 'english',
                        'term_vector' => 'with_positions_offsets'
                    ],
                    'content' => [
                        'type'        => 'string',
                        'index'       => 'analyzed',
                        'analyzer'    => 'english',
                        'term_vector' => 'with_positions_offsets'
                    ],
                    'hash_id' => ['type' => 'string', 'index' => 'not_analyzed'],
                    'date'    => ['type' => 'date', 'format' => 'date_time_no_millis'],
                    'url'     => ['type' => 'string', 'index' => 'no']
                ]
            ]
        ]
    ]
];