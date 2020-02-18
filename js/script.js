// example for calling the PUT /notes/1 URL
var baseUrl = OC.generateUrl('/apps/test');

OCA.Files.fileActions.registerAction({
    name: 'convertImage',
    displayName: 'Convert this Image to JPEG',
    mime: "image/heic",
    permissions: OC.PERMISSION_UPDATE,
    iconClass: 'icon-picture',
    actionHandler: (filename, context) => startConversion(filename, context), 
});


OCA.Files.fileActions.registerAction({
    name: 'convertImage',
    displayName: 'Convert this Image to JPEG',
    mime: "image/heif",
    permissions: OC.PERMISSION_UPDATE,
    iconClass: 'icon-picture',
    actionHandler: (filename, context) => startConversion(filename, context), 
});

function startConversion (filename, context) {
    
    // Prepare everything for request to backend
    console.log("Started converting " + context.dir + filename);

    var tr = context.fileList.findFileEl(filename);
    context.fileList.showFileBusyState(tr, true);

    var data = {
        filename : filename,
        dir : context.dir,
    }
    
    $.ajax({
        url: baseUrl + '/convert',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(data)
    })
    
    .done(function (response) {
        // handle success
        context.fileList.showFileBusyState(tr, false);
        context.fileList.reload();
        OC.dialogs.alert("Finished converting", "Heic Converter");
        console.log("Finished converting: " + response);

    })
    
    .fail(function (response, code) {
        // handle failure
        context.fileList.showFileBusyState(tr, false);
        OC.dialogs.alert("An Error occured!");
        console.log("Backend error occured:" + response);
    });
}
