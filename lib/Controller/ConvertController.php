<?php

namespace OCA\ImageConverter\Controller;

use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use Psr\Log\LoggerInterface;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCA\ImageConverter\Storage\ConvertStorage;

class ConvertController extends Controller
{
	private static array $supportedFormats = ["JPEG", "PNG", "WEBP", "AVIF", "JXL", "BMP"];

	private ConvertStorage $storage;
	private LoggerInterface $logger;

	/**
	 * @param string $AppName The application name.
	 */
	public function __construct(LoggerInterface $logger, string $AppName, IRequest $request, ConvertStorage $ConvertStorage)
	{
		parent::__construct($AppName, $request);
		$this->storage = $ConvertStorage;
		$this->logger = $logger;
	}

	/**
	 * Return the supported output formats available in the current Imagick installation.
	 *
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	public function formats(): JSONResponse
	{
		try {
			return new JSONResponse([
				"formats" => $this->getSupportedFormats()
			]);
		} catch (\ImagickException $ex) {
			$this->logger->error("Imagick failed to query supported image formats.", ["exception" => $ex]);
			return new JSONResponse(["error" => "Unable to query supported image formats."], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Convert an image to a format supported by Imagick.
	 *
	 * @param string $filename
	 * @param int $id
	 * @param string $targetFormat
	 * @param integer $compressionQuality
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	public function convertImage(string $filename, int $id, string $targetFormat = "jpeg", int $compressionQuality = 100): JSONResponse
	{
		// Check if format is supported
		$targetFormat = strtoupper($targetFormat);
		try {
			$supportedFormats = $this->getSupportedFormats();
		} catch (\ImagickException $ex) {
			$this->logger->error("Imagick failed to query supported image formats for conversion.", [
				"filename" => $filename,
				"exception" => $ex
			]);
			return new JSONResponse(["error" => "Unable to query supported image formats."], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		if (!in_array($targetFormat, $supportedFormats, true)) {
			$this->logger->warning("Rejected unsupported image conversion format.", [
				"filename" => $filename,
				"targetFormat" => $targetFormat
			]);
			return new JSONResponse([
				"error" => "Unsupported target format.",
				"formats" => $supportedFormats
			], Http::STATUS_BAD_REQUEST);
		}


		$extension = strtolower($targetFormat);
		$newFilename = pathinfo($filename, PATHINFO_FILENAME) . "." . $extension;

		// Get the content of the original image and the handle for the converted file
		$originalFile = $this->storage->getFileById($id);
		$originalFileContent = $originalFile->fopen("r");

		// Do the actual conversion
		try {
			$image = new \Imagick();
			$image->readImageFile($originalFileContent);
			$image->setImageFormat($targetFormat);
			$image->setImageCompressionQuality($compressionQuality);
			$blob =  $image->getImageBlob();
		} catch (\ImagickException $ex) {
			/**@var \Exception $ex */
			$this->logger->error("Imagick failed to convert the image.", [
				"filename" => $filename,
				"targetFormat" => $targetFormat,
				"exception" => $ex
			]);
			return new JSONResponse(["error" => "Imagick failed to convert image " . $filename . ", check if you fulfill all requirements.", "details" => $ex->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		// Save the converted image at the same location as the original one
		$this->storage->saveNewImage($originalFile, $newFilename, $blob);

		return new JSONResponse([
			"result" => "Image $filename was converted sucessfully!",
			"newFilename" => $newFilename
		]);
	}


	// Utilities

	private function getSupportedFormats(): array
	{
		return array_values(array_intersect(self::$supportedFormats, \Imagick::queryFormats()));
	}
}
