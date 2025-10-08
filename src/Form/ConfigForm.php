<?php

namespace S3MediaIngester\Form;

use Laminas\Form\Element\Radio;
use Laminas\Form\Form;

class ConfigForm extends Form
{
    public function init()
    {
        $radio = new Radio('s3mediaingester_original_file_action');
        $radio->setOptions([
            'label' => 'Default action on original file', // @translate
            'info' => 'What to do with the original file after it has been successfully imported', // @translate
        ]);
        $radio->setAttributes([
            'id' => 's3mediaingester-original-file-action',
            'required' => true,
        ]);
        $radio->setValueOptions([
            'keep' => 'Keep', // @translate
            'delete' => 'Delete', // @translate
        ]);

        $this->add($radio);
    }
}
