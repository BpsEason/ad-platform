<?php
return [
    'default' => 'default',
    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'Laravel Ad Platform API',
            ],
            'routes' => [
                'api' => 'api.php',
            ],
            'paths' => [
                'docs_json' => 'api-docs.json',
                'docs_yaml' => 'api-docs.yaml',
                'annotations' => [
                    base_path('app/Http/Controllers'),
                    base_path('app/Models'),
                ],
            ],
        ],
    ],
    'defaults' => [
        'proxy' => false,
        'additional_headers' => [],
        'operations_sort' => 'alpha',
    ],
];
