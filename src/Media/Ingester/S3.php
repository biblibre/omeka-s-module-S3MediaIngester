<?php
namespace S3MediaIngester\Media\Ingester;

use Laminas\Form\Element\Hidden;
use Laminas\Form\Element\Radio;
use Laminas\Form\Element\Select;
use Laminas\Form\Element\Text;
use Laminas\Form\Fieldset;
use Laminas\Log\LoggerInterface;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Request;
use Omeka\Entity\Media;
use Omeka\File\TempFileFactory;
use Omeka\File\TempFile;
use Omeka\File\Validator;
use Omeka\Media\Ingester\IngesterInterface;
use Omeka\Settings\SettingsInterface;
use Omeka\Stdlib\ErrorStore;
use S3MediaIngester\S3Client;

class S3 implements IngesterInterface
{
    protected TempFileFactory $tempFileFactory;
    protected Validator $validator;
    protected array $config;
    protected SettingsInterface $settings;
    protected LoggerInterface $logger;
    protected S3Client $s3Client;

    public function __construct(TempFileFactory $tempFileFactory, Validator $validator, array $config, SettingsInterface $settings, LoggerInterface $logger, S3Client $s3Client)
    {
        $this->tempFileFactory = $tempFileFactory;
        $this->validator = $validator;
        $this->config = $config;
        $this->settings = $settings;
        $this->logger = $logger;
        $this->s3Client = $s3Client;
    }

    public function getLabel()
    {
        if (isset($this->config['label'])) {
            return sprintf('S3 (%s)', $this->config['label']);
        }

        return 'S3';
    }

    public function getRenderer()
    {
        return 'file';
    }

    /**
     * Ingest from an S3 bucket
     *
     * Accepts the following non-prefixed keys:
     *
     * + bucket: (required) Name of the bucket
     * + ingest_filename: (required) The filename to ingest.
     * + original_file_action: 'keep' or 'delete'
     *
     * {@inheritDoc}
     */
    public function ingest(Media $media, Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();

        if (!isset($data['ingest_filename'])) {
            $errorStore->addError('ingest_filename', 'No ingest filename specified'); // @translate;
            return;
        }

        $filepath = $data['ingest_filename'];
        try {
            $tempFile = $this->downloadFile($filepath);
        } catch (\Exception $e) {
            $errorStore->addError(
                'ingest_filename',
                sprintf(
                    'Cannot load file "%s". %s', // @translate
                    $filepath,
                    $e->getMessage()
                )
            );
            return;
        }

        if (!$this->validator->validate($tempFile, $errorStore)) {
            return;
        }

        if (!array_key_exists('o:source', $data)) {
            $media->setSource(basename($data['ingest_filename']));
        }

        $storeOriginal = $data['store_original'] ?? true;
        $storeThumbnails = $data['store_thumbnails'] ?? true;
        $deleteTempFile = $data['delete_temp_file'] ?? true;
        $hydrateFileMetadataOnStoreOriginalFalse = $data['hydrate_file_metadata_on_store_original_false'] ?? false;

        $tempFile->mediaIngestFile($media, $request, $errorStore, $storeOriginal, $storeThumbnails, $deleteTempFile, $hydrateFileMetadataOnStoreOriginalFalse);

        $this->dealWithOriginalFile($filepath, $data);
    }

    protected function downloadFile(string $filepath): TempFile
    {
        $tempFile = $this->tempFileFactory->build();
        $tempFile->setSourceName($filepath);

        $response = $this->s3Client->getObject($filepath);
        if (!$response->isOk()) {
            throw new \Exception(sprintf('S3MediaIngester: failed to get object %s from source %s', $filepath, $this->config['name']));
        }

        if (false === file_put_contents($tempFile->getTempPath(), $response->getBody())) {
            throw new \Exception('S3MediaIngester: failed to write to temporary file');
        }

        return $tempFile;
    }

    public function form(PhpRenderer $view, array $options = [])
    {
        return $view->partial('s3-media-ingester/common/media-ingester-form', ['source' => $this->config['name']]);
    }

    public function getS3Client(): S3Client
    {
        return $this->s3Client;
    }

    protected function dealWithOriginalFile(string $filepath, array $data)
    {
        $original_file_action = $data['original_file_action'] ?? 'default';
        if (!in_array($original_file_action, ['default', 'keep', 'delete'])) {
            $message = sprintf('S3MediaIngester: Unknown action "%s"', $original_file_action);
            $this->logger->err($message);
            return;
        }

        if ($original_file_action === 'default') {
            $original_file_action = $this->settings->get('s3mediaingester_original_file_action', 'keep');
        }

        if ($original_file_action === 'delete') {
            $response = $this->s3Client->deleteObject($filepath);
            if (!$response->isSuccess()) {
                $message = sprintf('S3MediaIngester: Failed to delete file "%s"', $filepath);
                $this->logger->warn($message);
            }
        }
    }

    protected function getBucketValueOptions(): array
    {
        $valueOptions = [];
        foreach ($this->config['buckets'] as $key => $bucket) {
            $valueOptions[$key] = $key;
        }

        return $valueOptions;
    }
}

