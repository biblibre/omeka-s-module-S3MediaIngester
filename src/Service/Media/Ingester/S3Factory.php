<?php
namespace S3MediaIngester\Service\Media\Ingester;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Omeka\Service\Exception\ConfigException;
use S3MediaIngester\Media\Ingester\S3;

class S3Factory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $tempFileFactory = $services->get('Omeka\File\TempFileFactory');
        $validator = $services->get('Omeka\File\Validator');
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $logger = $services->get('Omeka\Logger');
        $s3Client = $services->get('S3MediaIngester\S3Client');

        [, $sourceKey] = explode('-', $requestedName, 2);
        if (!isset($config['s3_media_ingester']['sources'][$sourceKey])) {
            throw new ConfigException('Missing s3 media ingester source configuration');
        }

        $sourceConfig = $config['s3_media_ingester']['sources'][$sourceKey];
        $sourceConfig['name'] = $sourceKey;
        $s3Client->setConfig($sourceConfig);

        return new S3($tempFileFactory, $validator, $sourceConfig, $settings, $logger, $s3Client);
    }
}
