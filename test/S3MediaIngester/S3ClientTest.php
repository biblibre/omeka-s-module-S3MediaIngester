<?php

namespace S3MediaIngester\Test;

use DateTime;
use Laminas\Http\Request;
use S3MediaIngester\S3Client;

class S3ClientTest extends TestCase
{
    protected string $key = 'XXXXXXXXXXXXXXXXXXXX';
    protected string $secret = '00000000-0000-0000-0000-000000000000';
    protected string $region = 'nl-ams';
    protected string $endpoint = 'https://s3.nl-ams.scw.cloud';
    protected string $bucket = 's3-media-ingester-bucket';

    public function testSignature()
    {
        $client = $this->getServiceLocator()->get('S3MediaIngester\S3Client');
        $client->setConfig($this->getBucketConfig());
        $client->setNow(new DateTime('2008-07-01T22:35:17Z'));
        $request = new Request();
        $request->setUri(sprintf('https://%s.s3.nl-ams.scw.cloud/', $this->bucket));

        $client->signRequest($request);
        $this->assertEquals('AWS4-HMAC-SHA256 Credential=XXXXXXXXXXXXXXXXXXXX/20080701/nl-ams/s3/aws4_request, SignedHeaders=x-amz-content-sha256;x-amz-date, Signature=4ee0dd1d76972fb1841ad654884ed2c145a4a74452777872dbac13c141508d95', $request->getHeaders()->get('Authorization')->getFieldValue());
    }

    protected function getBucketConfig(): array
    {
        return [
            'key' => $this->key,
            'secret' => $this->secret,
            'region' => $this->region,
            'endpoint' => $this->endpoint,
            'bucket' => $this->bucket,
        ];
    }
}
