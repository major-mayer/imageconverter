# Usage
Just right click your .heif/ .heic image and select "Convert this image to JPEG". It converts it to a jpeg file using the same location.

# Requirements
Needs the PHP [Imagick](https://github.com/Imagick/imagick) extension to be installed on your host compiled with support for the HEIC/ HEIF format. Check if your PHP installation supports the HEIC format by entering "php -i" in the terminal and go to the Imagick section.

[Here](https://medium.com/@eplt/5-minutes-to-install-imagemagick-with-heic-support-on-ubuntu-18-04-digitalocean-fe2d09dcef1) you can find a good tutorial about how to compile Imagemagick with HEIC support and install the PHP extension.

The official [Docker Nextcloud image](https://github.com/nextcloud/docker) already includes the Imagick extension with support for the HEIC format.

# ToDos
- Testing
- Option to choose JPEG quality
- Option to convert to other formats than JPEG
- ...