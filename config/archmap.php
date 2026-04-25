<?php

return [
    'output_path' => base_path('docs'),
    'diagrams_path' => base_path('docs/diagrams'),
    'default_format' => 'mermaid',
    'formats' => [
        'mermaid' => true,
        'markdown' => true,
        'json' => true,
        'plantuml' => false,
    ],
    'scan' => [
        'routes' => true,
        'models' => true,
        'classes' => true,
        'components' => true,
        'sequence' => true,
    ],
    'paths' => [
        'models' => app_path('Models'),
        'controllers' => app_path('Http/Controllers'),
        'services' => app_path('Services'),
        'repositories' => app_path('Repositories'),
        'jobs' => app_path('Jobs'),
        'events' => app_path('Events'),
        'listeners' => app_path('Listeners'),
        'policies' => app_path('Policies'),
        'requests' => app_path('Http/Requests'),
        'resources' => app_path('Http/Resources'),
    ],
    'ignore' => [
        'paths' => [
            base_path('vendor'),
            base_path('storage'),
            base_path('bootstrap/cache'),
            base_path('node_modules'),
        ],
        'routes' => [
            'telescope.*',
            'horizon.*',
            'debugbar.*',
        ],
    ],
    'erd' => [
        'max_nodes' => 150,
    ],
    'classes' => [
        'include_protected' => false,
        'max_methods_per_class' => 15,
        'show_constructor_dependencies' => true,
    ],
    'report' => [
        'enabled' => true,
        'thresholds' => [
            'max_controller_public_methods' => 10,
            'max_service_dependencies' => 7,
            'max_model_relationships' => 12,
        ],
    ],
    'ci' => [
        'fail_on' => 'critical',
        'report_path' => base_path('docs/archmap-report.json'),
    ],
    'cache' => [
        'enabled' => true,
        'path' => base_path('storage/framework/cache/archmap'),
    ],
];
