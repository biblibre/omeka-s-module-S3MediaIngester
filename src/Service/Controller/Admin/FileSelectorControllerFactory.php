<?php
namespace S3MediaIngester\Service\Controller\Admin;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use S3MediaIngester\Controller\Admin\FileSelectorController;

class FileSelectorControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');
        $s3Client = $services->get('S3MediaIngester\S3Client');

        return new FileSelectorController($config['s3_media_ingester'], $s3Client);
    }
}
