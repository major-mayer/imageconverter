// example for calling the PUT /notes/1 URL
var baseUrl = OC.generateUrl('/apps/imageconverter');

/**
 * Register conversion action for HEIC
 */
OCA.Files.fileActions.registerAction({
    name: 'convertImage',
    displayName: 'Convert this image to JPEG',
    mime: "image/heic",
    permissions: OC.PERMISSION_UPDATE,
    iconClass: 'icon-picture',
    actionHandler: (filename, context) => startSingleConversion(filename, context),
});


/**
 * Register conversion action for HEIF
 */
OCA.Files.fileActions.registerAction({
    name: 'convertImage',
    displayName: 'Convert this image to JPEG',
    mime: "image/heif",
    permissions: OC.PERMISSION_UPDATE,
    iconClass: 'icon-picture',
    actionHandler: (filename, context) => startSingleConversion(filename, context),
});

/**
 * "Register" conversion action for multiple files
 */
var timer = setInterval(() => {
    // check if fileList has already loaded
    if (OCA.Files.App.fileList != null) {
        OCA.Files.App.fileList.multiSelectMenuItems.push({
            action: startMultiConversion,
            displayName: "Convert selection to JPEG ",
            iconClass: 'icon-picture',
            name: 'convertImages'
        });

        OCA.Files.App.fileList.fileMultiSelectMenu.render(OCA.Files.App.fileList.multiSelectMenuItems)
        clearInterval(timer);
    }
    else {
        // has not been loaded so try again later
        console.info("Waiting for Filelist to initialize...");
    }

}, 100);


function startSingleConversion(filename, context) {

    // Prepare everything for request to backend
    console.log("Started converting " + context.dir + filename);

    let fileElement = context.fileList.findFileEl(filename);
    context.fileList.showFileBusyState(fileElement, true);

    let data = {
        filename: filename,
        dir: context.dir,
    }

    $.ajax({
        url: baseUrl + '/convert',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(data)
    })

        .done(function (response) {
            // handle success
            context.fileList.showFileBusyState(fileElement, false);
            context.fileList.reload();
            OC.dialogs.alert("Image has been successfully converted", "Conversion successfull!");
            console.log("Finished converting: " + response);

        })

        .fail(function (response, code) {
            // handle failure
            context.fileList.showFileBusyState(fileElement, false);
            OC.dialogs.alert("An Error occured!");
            console.log("Backend error occured:" + "Conversion failed!");
        });
}

/**
 * Start the conversion for multiple images
 */
function startMultiConversion() {
    let currentDir = this.FileList.getCurrentDirectory();
    let files = this.FileList.getSelectedFiles();

    // Check for wrong mimetype
    for (let file of files) {
        if (!(file.mimetype == "image/heic" || file.mimetype == "image/heif")) {
            OC.dialogs.alert("Selection contains at least one image that is not in the HEIC or HEIF format", "Conversion failed!")
            return 1;
        }
    }

    let ajaxArray = [];

    // Generate requests for all images
    for (let file of files) {
        let fileElement = this.FileList.findFileEl(file.name);
        this.FileList.showFileBusyState(fileElement, true);


        let data = {
            filename: file.name,
            dir: currentDir,
        };

        var ajaxRequest = $.ajax({
            url: baseUrl + '/convert',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data)
        });

        ajaxArray.push(ajaxRequest);
    }

    // Wait for all conversion to be done
    $.when.apply($, ajaxArray).then(
        // Success
        () => {
            this.FileList.reload();
            OC.dialogs.alert("Images have been successfully converted", "Conversion successfull!");
        },
        // Error 
        (reason) => {
            this.FileList.reload();
            OC.dialogs.alert("An Error occured!");
            console.error("Backend error occured:");
            console.error(reason);
        });

}