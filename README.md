# S3 Media Ingester

Adds a media ingester for files stored on Amazon S3 compatible object storage.

## Installation

See general end user documentation for [Installing a module](http://omeka.org/s/docs/user-manual/modules/#installing-modules)

## Configuration

In Omeka's `config/local.config.php`, add a section like this:

```php
<?php

return [
    /* ... */
    's3_media_ingester' => [
        'sources' => [
            'my-s3-source' => [
                'label' => 'My S3 source',
                'key' => '<key>',
                'secret' => '<secret>',
                'region' => '<region>',
                'endpoint' => '<endpoint>',
                'bucket' => '<bucket>',
            ],
        ],
    ],
];
```

In the module global settings (Admin > Modules > S3MediaIngester's
"Configure" button) you can choose the default action on original files (keep
or delete).
This default action can be overriden when importing new media.

## Compatibility with other modules

* S3 Media Ingester can be used as a media source for [CSVImport](https://github.com/omeka-s-modules/CSVImport)

## How to use in PHP code

You can use this ingester in PHP code like this

```php
$itemData['o:media'][] = [
    'o:ingester' => 's3',
    'bucket' => 'default',
    'ingest_filename' => '/path/to/file.png',
    'original_file_action' => 'keep', // or 'delete'
];
$api->create('items', $itemData);
```
