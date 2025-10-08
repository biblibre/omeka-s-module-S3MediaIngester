<?php
namespace S3MediaIngester\Service;

use S3MediaIngester\S3Client;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class S3ClientFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $httpClient = $services->get('Omeka\HttpClient');

        return new S3Client($httpClient);
    }
}
