<?php

namespace S3MediaIngester;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Laminas\Http\Client as HttpClient;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Uri;

class S3Client
{
    protected array $config;
    protected HttpClient $httpClient;

    // Used for tests only
    protected DateTimeImmutable $now;

    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    public function listObjects(string $prefix = null, string $delimiter = '/'): Response
    {
        $request = new Request();
        $request->setMethod('GET');

        $uri = new Uri\Http($this->getConfig('endpoint'));
        $uri->setHost(sprintf('%s.%s', $this->getConfig('bucket'), $uri->getHost()));
        $uri->setPath('/');
        $query = ['delimiter' => $delimiter];
        if ($prefix) {
            $query['prefix'] = $prefix;
        }
        $uri->setQuery($query);
        $request->setUri($uri);
        $this->signRequest($request);

        return $this->httpClient->send($request);
    }

    public function getObject(string $key): Response
    {
        $request = new Request();
        $request->setMethod('GET');

        $uri = new Uri\Http($this->getConfig('endpoint'));
        $uri->setHost(sprintf('%s.%s', $this->getConfig('bucket'), $uri->getHost()));

        $encodedKey = implode('/', array_map('rawurlencode', explode('/', ltrim($key, '/'))));
        $uri->setPath('/' . $encodedKey);

        $request->setUri($uri);
        $this->signRequest($request);

        return $this->httpClient->send($request);
    }

    public function deleteObject(string $key): Response
    {
        $request = new Request();
        $request->setMethod('DELETE');

        $uri = new Uri\Http($this->getConfig('endpoint'));
        $uri->setHost(sprintf('%s.%s', $this->getConfig('bucket'), $uri->getHost()));

        $encodedKey = implode('/', array_map('rawurlencode', explode('/', ltrim($key, '/'))));
        $uri->setPath('/' . $encodedKey);

        $request->setUri($uri);
        $this->signRequest($request);

        return $this->httpClient->send($request);
    }

    public function signRequest(Request $request): void
    {
        $date = $this->getNow();
        $dateISO8601 = $date->format('Ymd\THis\Z');
        $dateYmd = $date->format('Ymd');

        $hashedPayload = hash('sha256', $request->getContent() ?? '');

        $request->getHeaders()->addHeaderLine('x-amz-content-sha256', $hashedPayload);
        $request->getHeaders()->addHeaderLine('x-amz-date', $dateISO8601);

        $method = strtoupper($request->getMethod());
        $path = $request->getUri()->getPath() ?? '';
        $query = $request->getUri()->getQuery() ?? '';
        $canonicalHeaders = '';
        $signedHeadersArray = [];
        foreach ($request->getHeaders() as $header) {
            $fieldName = strtolower($header->getFieldName());
            $fieldValue = trim($header->getFieldValue());
            $canonicalHeaders .= sprintf("%s:%s\n", $fieldName, $fieldValue);
            $signedHeadersArray[] = $fieldName;
        }
        sort($signedHeadersArray);
        $signedHeaders = implode(';', $signedHeadersArray);

        $canonicalRequest = sprintf(
            "%s\n%s\n%s\n%s\n%s\n%s",
            $method,
            $path,
            $query,
            $canonicalHeaders,
            $signedHeaders,
            $hashedPayload
        );

        $stringToSign = sprintf(
            "%s\n%s\n%s\n%s",
            'AWS4-HMAC-SHA256',
            $dateISO8601,
            sprintf('%s/%s/s3/aws4_request', $dateYmd, $this->getConfig('region')),
            hash('sha256', $canonicalRequest)
        );

        $dateKey = hash_hmac('sha256', $dateYmd, "AWS4" . $this->getConfig('secret'), true);
        $regionKey = hash_hmac('sha256', $this->getConfig('region'), $dateKey, true);
        $serviceKey = hash_hmac('sha256', 's3', $regionKey, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $serviceKey, true);

        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $credential = sprintf('%s/%s/%s/s3/aws4_request', $this->getConfig('key'), $dateYmd, $this->getConfig('region'));
        $authorizationHeaderValue = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s, SignedHeaders=%s, Signature=%s',
            $credential,
            $signedHeaders,
            $signature
        );

        $request->getHeaders()->addHeaderLine('Authorization', $authorizationHeaderValue);
    }

    /**
     * This is only useful for tests. Do not use it outside of tests
     */
    public function setNow(DateTimeInterface $now)
    {
        $this->now = DateTimeImmutable::createFromInterface($now)->setTimezone(new DateTimeZone('UTC'));
    }

    public function getNow(): DateTimeImmutable
    {
        if (isset($this->now)) {
            return $this->now;
        }

        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    protected function getConfig(string $key)
    {
        return $this->config[$key];
    }
}
