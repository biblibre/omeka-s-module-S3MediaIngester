<?php

namespace S3MediaIngester;

return [
    'controllers' => [
        'factories' => [
            'S3MediaIngester\Controller\Admin\FileSelector' => Service\Controller\Admin\FileSelectorControllerFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            'S3MediaIngester\Form\ConfigForm' => Form\ConfigForm::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    's3-media-ingester' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/s3-media-ingester/:controller[/:action]',
                            'defaults' => [
                                '__NAMESPACE__' => 'S3MediaIngester\Controller\Admin',
                                'action' => 'browse',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    's3_media_ingester' => [
        'sources' => [
            //'my-s3-source' => [
            //    'label' => 'My S3 source',
            //    'key' => '<key>',
            //    'secret' => '<secret>',
            //    'region' => '<region>',
            //    'endpoint' => '<endpoint>',
            //    'bucket' => '<bucket>',
            //],
        ],
    ],
    'service_manager' => [
        'factories' => [
            'S3MediaIngester\S3Client' => Service\S3ClientFactory::class,
        ],
        'shared' => [
            'S3MediaIngester\S3Client' => false,
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
];
