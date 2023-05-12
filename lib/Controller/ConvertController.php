<?php
namespace OCA\ImageConverter\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class ConvertController extends Controller {
	private $userId;
	private $config;
	private $storage;
    private $logger;

	public function __construct(LoggerInterface $logger, $AppName, IRequest $request, $UserId, IConfig $config, \OCA\ImageConverter\Storage\ConvertStorage $ConvertStorage){
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
		$this->config = $config;
		$this->storage = $ConvertStorage;
		$this->logger = $logger;
	}

	/**
	 * Endpoint to convert the HEIC/HEIF images
	 *
	 * @param string $filename
	 * @param int $fileId
	 * @param integer $compressionQuality
	 * @return void
	 * @NoAdminRequired
	 */
	public function convertImage( $filename, $fileId, $compressionQuality = 100) {

		//unused
		//$completeDir = $this->config->getSystemValue('datadirectory', '').'/'. $this->userId.'/files/';

		// Check if file is .heic or .heif and rename correctly 
		if (stripos($filename, ".heic") === false) {
			$newFilename = str_ireplace(".heif", ".jpg" , $filename );
		}
		else {
			$newFilename = str_ireplace(".heic", ".jpg" , $filename );
		}

		// Get the content of the original image and the handle for the converted file
		$originalFileContent = $this->storage->getFileContentById($fileId);
		

		// Do the actual conversion
		try {
			$image = new \Imagick();
			$image->readImageFile($originalFileContent);
			$image->setImageFormat("jpeg");
			$image->setImageCompressionQuality($compressionQuality);
			$blob =  $image->getImageBlob();
	
			$this->storage->saveNewImage($fileId, $newFilename, $blob);
		} 
		catch (\ImagickException $ex) {
			/**@var \Exception $ex */
			$this->logger->error("Imagick failed to convert the images: " . $ex->getMessage());
			return new JSONResponse(["error" => "Imagick failed to convert the images, check if you fulfill all requirements." , "details" => $ex->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);

		}
		

		return new JSONResponse(["result" => "Image $filename was converted sucessfully!"]);
		
	}

}
