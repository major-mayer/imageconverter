<?php

namespace OCA\ImageConverter\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use Psr\Log\LoggerInterface;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;

class ConvertController extends Controller
{
	private $storage;
	private $logger;

	public function __construct(LoggerInterface $logger, $AppName, IRequest $request, \OCA\ImageConverter\Storage\ConvertStorage $ConvertStorage)
	{
		parent::__construct($AppName, $request);
		$this->storage = $ConvertStorage;
		$this->logger = $logger;
	}

	/**
	 * Endpoint to convert the HEIC/HEIF images
	 *
	 * @param string $filename
	 * @param int $id
	 * @param integer $compressionQuality
	 * @return void
	 */
	#[NoAdminRequired]
	public function convertImage($filename, $id, $compressionQuality = 100)
	{

		// Check if file is .heic or .heif and rename correctly 
		if (stripos($filename, ".heic") === false) {
			$newFilename = str_ireplace(".heif", ".jpg", $filename);
		} else {
			$newFilename = str_ireplace(".heic", ".jpg", $filename);
		}

		// Get the content of the original image and the handle for the converted file
		$originalFileContent = $this->storage->getFileContentById($id);

		// Do the actual conversion
		try {
			$image = new \Imagick();
			$image->readImageFile($originalFileContent);
			$image->setImageFormat("jpeg");
			$image->setImageCompressionQuality($compressionQuality);
			$blob =  $image->getImageBlob();
		} catch (\ImagickException $ex) {
			/**@var \Exception $ex */
			$this->logger->error("Imagick failed to convert the images: " . $ex->getMessage());
			return new JSONResponse(["error" => "Imagick failed to convert image " . $filename . ", check if you fulfill all requirements.", "details" => $ex->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		// Save the converted image at the same location as the original one
		$this->storage->saveNewImage($id, $newFilename, $blob);

		return new JSONResponse([
			"result" => "Image $filename was converted sucessfully!",
			"newFilename" => $newFilename
		]);
	}
}
