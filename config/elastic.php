<?php

return [

    'index' => 'sites',
    'body'  => [
        'settings' => [
            'number_of_shards'   => 10,
            'number_of_replicas' => 0,
            'analysis'           => [
                'char_filter' => [
                    'persian_arabic_chars' => [
                        'type'     => 'mapping',
                        'mappings' => [
                            'ك=>ک',
                            'ي=>ی',
                            'ؤ=>و',
                            'ئ=>ی',
                            'أ=>ا',
                            'ِ=>',
                            'ُ=>',
                            'َ=>',
                            'آ=>ا',
                            '‌=> '
                        ]
                    ]
                ],
                'analyzer'    => [
                    'persian_arabic_analyzer' => [
                        'type'        => 'custom',
                        'tokenizer'   => 'standard',
                        'char_filter' => ['persian_arabic_chars']
                    ]
                ]
            ]
        ],
        'mapping'  => [
            'default' => [
                'properties' => [
                    'title'    => [
                        'type'     => 'string',
                        'index'    => 'analyzed',
                        'analyzer' => 'persian_arabic_analyzer'
                    ],
                    'content'  => [
                        'type'     => 'string',
                        'index'    => 'analyzed',
                        'analyzer' => 'persian_arabic_analyzer'
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