How to use in PHP code
======================

You can use this ingester in PHP code like this::

    $itemData['o:media'][] = [
        // The ingester name is "s3-" followed by the name of the source configured earlier.
        'o:ingester' => 's3-my-s3-source',
        'ingest_filename' => 'path/to/file.png',
        'original_file_action' => 'keep', // or 'delete'
    ];
    $api->create('items', $itemData);
