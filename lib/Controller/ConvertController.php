<?php
namespace OCA\ImageConverter\Controller;

use OC\Core\Command\Broadcast\Test;
use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;

class ConvertController extends Controller {
	private $userId;
	private $config;
	private $storage;

	public function __construct($AppName, IRequest $request, $UserId, IConfig $config, \OCA\ImageConverter\Storage\ConvertStorage $ConvertStorage){
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
		$this->config = $config;
		$this->storage = $ConvertStorage;

	}

    /**
     * @NoAdminRequired
     */
	public function convertImage( $dir, $filename) {
		// Check if directory is the root dir, to not get double slashes
		if ($dir ===  "/") {
			$dir = "";
		}

		$completeDir = $this->config->getSystemValue('datadirectory', '').'/'. $this->userId.'/files'.$dir.'/';
		
		// Check if file is .heic or .heif and rename correctly 
		if (stripos($filename, ".heic") === false) {
			$newFilename = str_ireplace(".heif", ".jpg" , $filename );
		}
		else {
			$newFilename = str_ireplace(".heic", ".jpg" , $filename );
		}

		// Check if the file exists
		if (file_exists($completeDir.$filename)) {
			//Do the actual conversion
			$image = new \Imagick($completeDir.$filename);
			$image->setImageFormat("jpeg");
			$image->writeImage($completeDir.$newFilename);

			//nextcloud needs to get notified about the newly created file
			$this->storage->enableFile($dir.'/'.$newFilename);


			return new JSONResponse(["result" => " File was converted sucessfully at :". $dir.'/'.$newFilename]);
		}
		else {
			return new JSONResponse( ["error" => "file does not exist at :". $dir.'/'.$newFilename], Http::STATUS_NOT_FOUND);
		}
		
		
	}

}
