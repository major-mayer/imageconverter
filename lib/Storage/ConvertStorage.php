<?php

namespace OCA\ImageConverter\Storage;

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
     * Returns the content of a file by it's id
     *
     * @param int $id
     * @return resource
     */
    public function getFileContentById($id)
    {
        $files = $this->storage->getById($id);

        // A file can be multiple times shared
        if (count($files) > 1) {
            throw new Exception("more than one times shared!");
        }
        $file = $files[0];

        // return the file content as resource
        if ($file instanceof \OCP\Files\File) {

            /**@var \OCP\Files\File $file */
            $fileContent = $file->fopen("r");

            return $fileContent;
        } else {
            throw new Exception('Can not read from folder');
        }
    }

    /**
     * Returns the handle for a new file that shall be created at the same location as the old one
     *
     * @param int $originalImageid
     * @param string $newName
     * @param string $newContent
     * @return resource
     */
    public function saveNewImage($originalImageid, $newName, $newContent)
    {
        if (empty($newContent)) {
            throw new Exception("Could not save the new converted image, because the content is empty. Something went wrong with the conversion before!");
        }

        $parentFolder = $this->storage->getById($originalImageid)[0]->getParent();

        //Check if the path is writeable and a folder
        if ($parentFolder->isCreatable() && $parentFolder instanceof \OCP\Files\Folder) {
            /**@var \OCP\Files\Folder $path */
            $newFile = $parentFolder->newFile($newName);
            $result = $newFile->putContent($newContent);
            return $result;
        } else {
            throw new Exception("Path is not writeable");
        }
    }
}
