# Usage
Right-click an image in Nextcloud and choose the conversion action. The app creates a converted file in the same folder using the format supported by your ImageMagick installation.

# Features
- Convert HEIC/HEIF images and other image formats using ImageMagick.
- Convert images to JPEG, PNG, WEBP, AVIF, JXL, and HEIC when those formats are supported by the installed build.
- Works directly from the Nextcloud file context menu.
- The exact supported formats depend on the available ImageMagick codecs and PHP Imagick support.

# Requirements
This app requires the PHP [Imagick](https://github.com/Imagick/imagick) extension to be installed on the server, with image format support enabled in the underlying ImageMagick build.

To check whether your PHP installation supports a given format, run "php -i" in the terminal and look for the Imagick section.

[This tutorial](https://medium.com/@eplt/5-minutes-to-install-imagemagick-with-heic-support-on-ubuntu-18-04-digitalocean-fe2d09dcef1) is a good reference for compiling ImageMagick with HEIC support and installing the PHP extension.

The official [Docker Nextcloud image](https://github.com/nextcloud/docker) already includes the Imagick extension with HEIC support.

# ToDo
- Testing
- Preserving EXIF data
- ...
