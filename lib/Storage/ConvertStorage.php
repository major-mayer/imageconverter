<?php

namespace OCA\ImageConverter\Storage;

use Exception;
use OCA\WorkflowEngine\Check\FileName;
use OCP\Files\File;
use OCP\Files\IRootFolder;

class ConvertStorage
{
    private IRootFolder $storage;

    public function __construct(IRootFolder $rootFolder)
    {
        $this->storage = $rootFolder;
    }

    /**
     * Returns the content of a file by it's id
     *
     * @param int $id
     * @return File
     * @throws Exception
     */
    public function getFileById($id): File
    {
        $files = $this->storage->getById($id);

        // A file can be multiple times shared
        if (count($files) > 1) {
            throw new Exception("more than one times shared!");
        }
        $file = $files[0];

        // return the file content as resource
        if ($file instanceof File) {
            return $file;
        } else {
            throw new Exception('Can not read from folder');
        }
    }

    /**
     * Returns the handle for a new file that shall be created at the same location as the old one
     *
     * @param File $originalFile
     * @param string $newName
     * @param string $newContent
     * @return File
     * @throws Exception
     */
    public function saveNewImage(File $originalFile, string $newName, string $newContent)
    {
        if (empty($newContent)) {
            throw new Exception("Could not save the new converted image, because the content is empty. Something went wrong with the conversion before!");
        }

        $parentFolder = $originalFile->getParent();

        //Check if the path is writeable and a folder
        if ($parentFolder->isCreatable() && $parentFolder instanceof \OCP\Files\Folder) {
            /**@var \OCP\Files\Folder $path */
            $newFile = $parentFolder->newFile($newName);
            $newFile->putContent($newContent);
            return $newFile->getId();
        } else {
            throw new Exception("Path is not writeable");
        }
    }
}
