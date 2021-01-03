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


async function startSingleConversion(filename, context) {
    // Prepare everything for request to backend
    console.log("Started converting " + context.dir + filename);

    let fileElement = context.fileList.findFileEl(filename);
    context.fileList.showFileBusyState(fileElement, true);

    let data = {
        filename: filename,
        fileId: context.fileInfoModel.id,
    }
    
    try {
        let response = await fetch(baseUrl + "/convert", {
            method : "POST",
            headers: {
                'Content-Type': 'application/json',
                "requesttoken" : OC.requestToken,
            },
            body : JSON.stringify(data)
        });

        if(!response.ok) {
            throw new Error("Backend returned wrong statuscode!")
        }

        let json = await response.json();

        // handle success
        context.fileList.showFileBusyState(fileElement, false);
        context.fileList.reload();
        OC.dialogs.alert("Image "+ filename + " has been successfully converted", "Conversion successfull");
        console.log("Finished converting: " + json.result);

    }
    catch (ex) {
        // handle failure
        context.fileList.showFileBusyState(fileElement, false);
        OC.dialogs.alert("An Error occured while converting the image!", "Error");
        console.error("Backend error occured: Error "+ ex);
    }
}

/**
 * Start the conversion for multiple images
 */
async function startMultiConversion() {
    let files = this.FileList.getSelectedFiles();

    // Check for wrong mimetype
    for (let file of files) {
        if (!(file.mimetype == "image/heic" || file.mimetype == "image/heif")) {
            OC.dialogs.alert("Selection contains at least one image that is not in the HEIC or HEIF format", "Conversion failed!")
            return 1;
        }
    }

    let requestPromises = [];

    try {
        // Generate requests for all images
        for (let file of files) {
            let fileElement = this.FileList.findFileEl(file.name);

            this.FileList.showFileBusyState(fileElement, true);


            let data = {
                filename: file.name,
                fileId: file.id,
            };

            // Create a request promise for each selected file
            let requestPromise = fetch(baseUrl + "/convert", {
                method : "POST",
                headers: {
                    'Content-Type': 'application/json',
                    "requesttoken" : OC.requestToken,
                },
                body : JSON.stringify(data)
            });

            // Add it to an array to execute them all in parallel
            requestPromises.push(requestPromise)
        }

        // Wait for all conversions to be done
        let responses = await Promise.all(requestPromises);

        this.FileList.reload();

        // Check if a statuscode of one of the responses is incorrect 
        responses.forEach((response) => {
            if (!response.ok) {
                throw {
                    message : "Backend returned wrong statuscode!",
                    code : response.status, 
                    response: response.text()   // Will return a pending promise, but meh ...
                }
            }
        })

        OC.dialogs.alert("Images have been successfully converted", "Conversion successfull");


    } catch (ex) {
        OC.dialogs.alert("An Error occured while converting the images!", "Error");
        console.error("Backend error occured");
        console.error(ex);
    }

}