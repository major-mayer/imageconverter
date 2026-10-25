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
	private static array $supportedFormats = ["JPEG", "PNG", "WEBP", "AVIF", "JXL", "HEIC"];

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
	 * Return image MIME types recognized by the current Imagick installation.
	 *
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	public function mimeTypes(): JSONResponse
	{
		try {
			return new JSONResponse([
				"mimeTypes" => $this->getSupportedMimeTypes()
			]);
		} catch (\ImagickException $ex) {
			$this->logger->error("Imagick failed to query supported image MIME types.", ["exception" => $ex]);
			return new JSONResponse(["error" => "Unable to query supported image MIME types."], Http::STATUS_INTERNAL_SERVER_ERROR);
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
	public function convertImage(string $filename, int $id, string $targetFormat = "jpeg", int $compressionQuality = 90): JSONResponse
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
		try {
			$this->storage->saveNewImage($originalFile, $newFilename, $blob);
		} catch (\OCP\Lock\LockedException $ex) {
			$this->logger->warning("Converted image could not be saved because the destination is locked.", [
				"filename" => $newFilename,
				"exception" => $ex
			]);
			return new JSONResponse([
				"error" => "The destination image is locked. Use a different file name or close any operation using the file and try again."
			], Http::STATUS_CONFLICT);
		}

		return new JSONResponse([
			"result" => "Image $filename was converted sucessfully to $targetFormat!",
			"newFilename" => $newFilename
		]);
	}


	// Utilities

	private function getSupportedFormats(): array
	{
		return array_values(array_intersect(self::$supportedFormats, \Imagick::queryFormats()));
	}

	private function getSupportedMimeTypes(): array
	{
		$image = new \Imagick();
		$image->newImage(1, 1, new \ImagickPixel("white"));
		$mimeTypes = [];

		foreach (\Imagick::queryFormats() as $format) {
			try {
				$image->setImageFormat($format);
				$mimeType = $image->getImageMimeType();
				if (str_starts_with($mimeType, "image/")) {
					$mimeTypes[] = $mimeType;
					// Imagick uses a lot of non-standard MIME types like image/x-jxl or image/x-heif
					// To support also the standard mime types, we strip the x- prefix and add them to the list
					if (str_starts_with($mimeType, "image/x-")) {
						$mimeTypes[] = "image/" . substr($mimeType, 8);
					}
				}
			} catch (\ImagickException) {
				continue;
			}
		}

		$image->clear();
		$mimeTypes = array_values(array_unique($mimeTypes));
		sort($mimeTypes);
		return $mimeTypes;
	}
}
