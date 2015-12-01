<?php

return [

    'index' => 'sites',
    'body'  => [
        'mapping' => [
            'default' => [
                'properties' => [
                    'title'   => ['type' => 'string'],
                    'content' => ['type' => 'string'],
                    'hash_id' => ['type' => 'string', 'index' => 'not_analyzed'],
                    'original' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string', 'index' => 'not_analyzed'],
                            'content' => ['type' => 'string', 'index' => 'not_analyzed'],
                        ]
                    ]
                ]
            ]
        ]
    ]

];