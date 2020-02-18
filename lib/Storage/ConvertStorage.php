<?php

namespace OCA\Test\Storage;

use Exception;
use OCA\WorkflowEngine\Check\FileName;

class ConvertStorage
{

    /** @var \OC\Files\Node\Folder */
    private $storage;

    public function __construct($storage)
    {
        $this->storage = $storage;
    }

    /**
     * Executes a get method for the new file, so that nextcloud recognizes it
     *
     * @param [type] $filename
     * @return void
     */
    public function enableFile($filename)
    {
        // check if file exists 
        try {
            $file = $this->storage->get($filename);
        } catch (\OCP\Files\NotFoundException $e) {
            throw $e;
        }
    }
    
    // Doesn't work
    public function createNewFile($filename) {
        var_dump($this->storage);

        $this->storage->touch($filename);
        $file = $this->storage->get($filename);
        var_dump(get_class($file));
        return $file;
    }

    public function test() {
        var_dump(get_class($this->storage));
    }
}