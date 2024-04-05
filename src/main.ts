import {
    FileAction,
    Node,
    NodeStatus,
    View,
    davGetClient,
    davGetDefaultPropfind,
    davResultToNode,
    davRootPath,
    registerFileAction,
} from '@nextcloud/files'
import { emit } from '@nextcloud/event-bus'
import type { ResponseDataDetailed, FileStat } from "webdav"
import { showSuccess, showError } from '@nextcloud/dialogs'
import { getRequestToken } from '@nextcloud/auth'
import '@nextcloud/dialogs/style.css'
import { generateUrl } from "@nextcloud/router";
import imageIcon from "@mdi/svg/svg/image-edit.svg?raw";

// example for calling the PUT /notes/1 URL
const baseUrl = generateUrl('/apps/imageconverter');

// Register file actions for a single image
registerFileAction(new FileAction({
    id: "convertImage",
    displayName: () => 'Convert to JPEG',
    enabled: (files, view) => {
        for (const file of files) {
            if (file.mime !== "image/heic" && file.mime !== "image/heif") {
                return false
            }
        }
        return true;
    },
    exec: startSingleConversion,
    iconSvgInline: () => {
        return imageIcon;
    },
    execBatch: startMultiConversion
})
)

async function startSingleConversion(file: Node, view: View, dir: string) {
    try {
        // Add a trailing slash to dir
        if (!dir.endsWith("/")) {
            dir = dir + "/"
        }

        console.log("Started converting " + file.path);
        file.status = NodeStatus.LOADING;

        // Prepare everything for request to backend
        let data = {
            filename: file.basename,
            fileId: file.fileid,
        }

        const requestToken = getRequestToken();
        if (!requestToken) {
            throw Error("Unable to get a request token!");
        }

        let response = await fetch(baseUrl + "/convert", {
            method: "POST",
            headers: {
                'Content-Type': 'application/json',
                "requesttoken": requestToken,
            },
            body: JSON.stringify(data)
        });

        if (!response.ok) {
            const backendError = await response.text();
            throw new Error("Backend returned wrong statuscode! Details : " + backendError)
        }

        let json = await response.json();

        // Emit a files:node:created event for converted image, so they show up in the files app
        const davClient = davGetClient();
        const result = await davClient.stat(davRootPath + dir + json.newFilename, { details: true, data: davGetDefaultPropfind() })

        // FileStat type guard
        function isResponseDataDetailed(result: FileStat | ResponseDataDetailed<FileStat>): result is ResponseDataDetailed<FileStat> {
            return (result as ResponseDataDetailed<FileStat>).status !== undefined;
        }

        if (isResponseDataDetailed(result)) {
            emit("files:node:created", davResultToNode(result.data))
        }
        else {
            throw Error("Dav result is not detailed (missing the 'props' property");
        }

        showSuccess("Image " + file.path + " has been successfully converted")
        console.log("ImageConverter: Finished converting: " + json.result);
        return true;

    }
    catch (ex) {
        // handle failure
        file.status = undefined;
        showError("An Error occured while converting the image!", { timeout: -1 })
        console.error("ImageConverter: Error: " + ex);
        return false;
    }
    finally {
        file.status = undefined;
    }
}


async function startMultiConversion(files: Node[], view: View, dir: string): Promise<(boolean | null)[]> {
    console.log("Started conversion of multiple files");

    let executionError = false;

    // Add a trailing slash to dir
    if (!dir.endsWith("/")) {
        dir = dir + "/"
    }

    // Check for wrong mimetype
    const returnArray = files.map(file => {
        if (!(file.mime == "image/heic" || file.mime == "image/heif")) {
            showError("Selection contains at least one image that is not in the HEIC or HEIF format", { timeout: -1 })
            executionError = true;
            return false;
        }
        return true;
    })

    if (executionError) {
        return returnArray
    }

    let requestPromises: Promise<Response>[] = [];

    try {
        // Generate requests for all images
        for (let file of files) {
            // Prepare everything for request to backend
            let data = {
                filename: file.basename,
                fileId: file.fileid,
            }

            const requestToken = getRequestToken();
            if (!requestToken) {
                throw Error("Unable to get a request token!");
            }

            // Create a request promise for each selected file
            let requestPromise = fetch(baseUrl + "/convert", {
                method: "POST",
                headers: {
                    'Content-Type': 'application/json',
                    "requesttoken": requestToken,
                },
                body: JSON.stringify(data)
            })

            // Add it to an array to execute them all in parallel
            requestPromises.push(requestPromise)
        }

        // Wait for all conversions to be done
        let responses = await Promise.all(requestPromises);

        // FileStat type guard
        function isResponseDataDetailed(result: FileStat | ResponseDataDetailed<FileStat>): result is ResponseDataDetailed<FileStat> {
            return (result as ResponseDataDetailed<FileStat>).status !== undefined;
        }

        const returnPromises = responses.map(async (response) => {
            // Check if a statuscode of one of the responses is incorrect 
            if (!response.ok) {
                const backendError = await response.text();
                console.error("Backend returned wrong statuscode! Details : " + backendError)
                executionError = true;
                return false
            }

            const json = await response.json();

            // Emit a files:node:created event for all converted images, so they show up in the files app
            const davClient = davGetClient();
            const result = await davClient.stat(davRootPath + dir + json.newFilename, { details: true, data: davGetDefaultPropfind() })

            if (isResponseDataDetailed(result)) {
                emit("files:node:created", davResultToNode(result.data))
                return true
            }
            else {
                console.error("Dav result is not detailed (missing the 'props' property");
                executionError = true;
                return false;
            }
        })

        const returnArray = await Promise.all(returnPromises)
        if (executionError) {
            showError("An error occured while converting the images!", { timeout: -1 });
            return returnArray;
        } else {
            showSuccess("Images have been successfully converted");
            return returnArray;
        }
    } catch (ex) {
        showError("An error occured while converting the images!", { timeout: -1 });
        console.error("ImageConverter: Error: " + ex);
        return returnArray;
    }

}