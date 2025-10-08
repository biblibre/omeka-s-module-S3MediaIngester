<?php
namespace S3MediaIngester\Controller\Admin;

use DOMDocument;
use DOMXPath;
use Laminas\View\Model\ViewModel;
use Laminas\Mvc\Controller\AbstractActionController;
use Omeka\Mvc\Exception\NotFoundException;
use S3MediaIngester\S3Client;

class FileSelectorController extends AbstractActionController
{
    protected array $config;
    protected S3Client $s3Client;

    public function __construct(array $config, S3Client $s3Client)
    {
        $this->config = $config;
        $this->s3Client = $s3Client;
    }

    public function browseAction()
    {
        $source = $this->params()->fromQuery('source');
        if (!$source || !isset($this->config['sources'][$source])) {
            throw new NotFoundException;
        }

        $prefix = $this->params()->fromQuery('prefix');

        $this->s3Client->setConfig($this->config['sources'][$source]);
        $response = $this->s3Client->listObjects($prefix);
        if (!$response->isOk()) {
            throw new NotFoundException;
        }

        $document = new DOMDocument;
        $document->loadXML($response->getBody());
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s3', 'http://s3.amazonaws.com/doc/2006-03-01/');

        $directories = [];
        foreach ($xpath->query('//s3:CommonPrefixes/s3:Prefix') as $prefixNode) {
            $directories[] = $prefixNode->textContent;
        }

        $files = [];
        foreach ($xpath->query('//s3:Contents/s3:Key') as $keyNode) {
            $files[] = $keyNode->textContent;
        }


        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('source', $source);
        $view->setVariable('prefix', $prefix);
        $view->setVariable('directories', $directories);
        $view->setVariable('files', $files);

        if ($prefix) {
            $parentPrefix = dirname($prefix);
            if ($parentPrefix === '.') {
                $parentPrefix = '';
            }
            $view->setVariable('parentPrefix', $parentPrefix);
        }

        return $view;
    }
}
