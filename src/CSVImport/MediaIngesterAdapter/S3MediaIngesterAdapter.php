<?php

namespace S3MediaIngester\CSVImport\MediaIngesterAdapter;

use CSVImport\MediaIngesterAdapter\MediaIngesterAdapterInterface;

class S3MediaIngesterAdapter implements MediaIngesterAdapterInterface
{
    public function getJson($mediaDatum)
    {
        $mediaDatumJson = [
            'ingest_filename' => $mediaDatum,
        ];

        return $mediaDatumJson;
    }
}
