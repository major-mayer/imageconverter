<?php
namespace OCA\Test\Controller;

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

	public function __construct($AppName, IRequest $request, $UserId, IConfig $config, \OCA\Test\Storage\ConvertStorage $ConvertStorage){
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
		$this->config = $config;
		$this->storage = $ConvertStorage;

	}

	public function convertImage( $dir, $filename) {
		// Check if directory is the root dir, to not get double slashes
		if ($dir ===  "/") {
			$dir = "";
		}

		$fileLocation = $this->config->getSystemValue('datadirectory', '').'/'. $this->userId.'/files'.$dir.'/'.$filename;

		// Check if the file exists
		if (file_exists($fileLocation)) {
			//Do the actual conversion
			$image = new \Imagick($fileLocation);
			$image->setImageFormat("jpeg");
			$image->writeImage($fileLocation.".jpg");

			//nextcloud needs to get notified about the newly created file
			$this->storage->enableFile($dir.'/'.$filename.".jpg");


			return new JSONResponse(["result" => " File was converted sucessfully at :". $dir.'/'.$filename.".jpg"]);
		}
		else {
			return new JSONResponse( ["error" => "file does not exist!"], Http::STATUS_NOT_FOUND);
		}
		
		
	}

}
