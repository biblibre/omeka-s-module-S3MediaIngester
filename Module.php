<?php
namespace S3MediaIngester;

use Laminas\ModuleManager\ModuleEvent;
use Laminas\ModuleManager\ModuleManager;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Omeka\Module\AbstractModule;
use Omeka\Stdlib\Message;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);

        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        $mediaIngesterManager = $services->get('Omeka\Media\Ingester\Manager');
        $config = $services->get('Config');
        $mediaIngesterConfig = ['factories' => []];

        foreach (array_keys($config['s3_media_ingester']['sources']) as $sourceKey) {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $sourceKey)) {
                $logger->warn(sprintf('S3MediaIngester: Invalid source key "%s". It must contain only letters, digits, "-" and "_"', $sourceKey));
                continue;
            }

            $name = sprintf('s3-%s', $sourceKey);
            $mediaIngesterConfig['factories'][$name] = Service\Media\Ingester\S3Factory::class;
        }

        $mediaIngesterManager->configure($mediaIngesterConfig);
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.hydrate.pre',
            [$this, 'onItemApiHydratePre']
        );
    }
    public function init(ModuleManager $moduleManager)
    {
        $events = $moduleManager->getEventManager();

        // Registering a listener at default priority, 1, which will trigger
        // after the ConfigListener merges config.
        $events->attach(ModuleEvent::EVENT_MERGE_CONFIG, [$this, 'onMergeConfig']);
    }

    public function onMergeConfig(ModuleEvent $e)
    {
        $configListener = $e->getConfigListener();
        $config = $configListener->getMergedConfig(false);

        foreach (array_keys($config['s3_media_ingester']['sources']) as $key) {
            $config['csv_import']['media_ingester_adapter']['s3-' . $key] = CSVImport\MediaIngesterAdapter\S3MediaIngesterAdapter::class;
        }

        // Pass the changed configuration back to the listener:
        $configListener->setMergedConfig($config);
    }

    public function onItemApiHydratePre(Event $event)
    {
        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');
        $data = $request->getContent();
        if (empty($data['o:media'])) {
            return;
        }

        $errorStore = $event->getParam('errorStore');

        $newDataMedias = [];
        foreach ($data['o:media'] as $dataMedia) {
            $newDataMedias[] = $dataMedia;

            if (empty($dataMedia['o:ingester']) || !str_starts_with($dataMedia['o:ingester'], 's3-')) {
                continue;
            }

            if (!array_key_exists('ingest_filename', $dataMedia)) {
                $errorStore->addError('ingest_filename', 'No ingest filename specified.'); // @translate
                continue;
            }

            $ingest_filename = (string) $dataMedia['ingest_filename'];

            if (!str_ends_with($ingest_filename, '/')) {
                // Not a directory, so no special treatment is needed
                continue;
            }

            $directory = $ingest_filename;

            $listFiles = $this->listFiles($dataMedia['o:ingester'], $directory, !empty($dataMedia['ingest_directory_recursively']));
            if (!count($listFiles)) {
                $errorStore->addError('ingest_filename', new Message(
                    'Ingest directory "%s" is empty.',  // @translate
                    $directory
                ));
                continue;
            }

            // Convert the media to a list of media for the item hydration.
            // Remove the added media directory from list of media.
            array_pop($newDataMedias);
            foreach ($listFiles as $filepath) {
                $dataMedia['ingest_filename'] = $filepath;
                $newDataMedias[] = $dataMedia;
            }
        }
        $data['o:media'] = $newDataMedias;
        $request->setContent($data);
    }

    protected function listFiles(string $ingesterName, string $directory, bool $recursive = false)
    {
        $ingesters = $this->getServiceLocator()->get('Omeka\Media\Ingester\Manager');
        $ingester = $ingesters->get($ingesterName);
        $s3Client = $ingester->getS3Client();

        $response = $s3Client->listObjects(rtrim($directory, '/') . '/');
        if (!$response->isOk()) {
            throw new \Exception('S3MediaIngester: failed to list objects');
        }

        $files = [];

        $document = new \DOMDocument;
        $document->loadXML($response->getBody());
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('s3', 'http://s3.amazonaws.com/doc/2006-03-01/');

        if ($recursive) {
            foreach ($xpath->query('//s3:CommonPrefixes/s3:Prefix') as $prefixNode) {
                $files = array_merge($files, $this->listFiles($ingesterName, $prefixNode->textContent, $recursive));
            }
        }

        foreach ($xpath->query('//s3:Contents/s3:Key') as $keyNode) {
            $files[] = $keyNode->textContent;
        }

        return $files;
    }

    public function getConfigForm($renderer)
    {
        $formElementManager = $this->getServiceLocator()->get('FormElementManager');
        $settings = $this->getServiceLocator()->get('Omeka\Settings');

        $form = $formElementManager->get('S3MediaIngester\Form\ConfigForm');
        $form->setData([
            's3mediaingester_original_file_action' => $settings->get('s3mediaingester_original_file_action', 'keep'),
        ]);

        return $renderer->formCollection($form, false);
    }

    public function handleConfigForm($controller)
    {
        $formElementManager = $this->getServiceLocator()->get('FormElementManager');
        $settings = $this->getServiceLocator()->get('Omeka\Settings');

        $form = $formElementManager->get('S3MediaIngester\Form\ConfigForm');
        $form->setData($controller->params()->fromPost());
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        $formData = $form->getData();
        $settings->set('s3mediaingester_original_file_action', $formData['s3mediaingester_original_file_action']);

        return true;
    }
}
