import {
    ActionContext,
    ActionContextSingle,
    NodeStatus,
    registerFileAction,
} from '@nextcloud/files'

import {
    getClient,
    getDefaultPropfind,
    resultToNode,
    getRootPath
} from '@nextcloud/files/dav'
import { emit } from '@nextcloud/event-bus'
import type { ResponseDataDetailed, FileStat } from "webdav"
import { showSuccess, showError } from '@nextcloud/dialogs'
import { getRequestToken } from '@nextcloud/auth'
import '@nextcloud/dialogs/style.css'
import { generateUrl } from "@nextcloud/router";
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import ConversionOptionsDialog from './ConversionOptionsDialog.vue'
import type { ConversionOptions } from './types'
import imageIcon from "@mdi/svg/svg/image-edit-outline.svg?raw";

// Base URL for all requests to the backend
const baseUrl = generateUrl('/apps/imageconverter');

// Main entry point: File action is registered here based on what image types Imagick supports
async function registerConversionAction(): Promise<void> {
    try {
        const requestToken = getRequestToken()
        if (!requestToken) {
            throw Error('Unable to get a request token!')
        }

        const response = await fetch(baseUrl + '/mimetypes', {
            headers: { requesttoken: requestToken },
        })
        if (!response.ok) {
            throw Error('Unable to retrieve supported input MIME types.')
        }

        const result = await response.json() as { mimeTypes: string[] }
        if (!Array.isArray(result.mimeTypes) || result.mimeTypes.length === 0) {
            throw Error('No supported input MIME types are available.')
        }

        const supportedMimeTypes = new Set(result.mimeTypes)
        registerFileAction({
            id: "convertImage",
            displayName: () => 'Convert image',
            enabled: ({ nodes }) =>
                nodes.every(file => { console.log("SOOOS", file.mime); return supportedMimeTypes.has(file.mime) }),
            exec: startSingleConversion,
            iconSvgInline: () => imageIcon,
            execBatch: startMultiConversion,
        })
    } catch (ex) {
        console.error('ImageConverter: Could not register conversion action:', ex)
    }
}

void registerConversionAction()


async function startSingleConversion({ nodes, folder }: ActionContextSingle) {
    const file = nodes[0];
    const options = await requestConversionOptions(file.basename, true)
    if (!options) {
        return false
    }

    try {
        console.log("Started converting " + file.path);
        file.status = NodeStatus.LOADING;

        // Add a trailing slash to dir
        let dirname = folder.dirname;
        if (!dirname.endsWith("/")) {
            dirname = dirname + "/"
        }


        // Prepare everything for request to backend
        let data = {
            filename: `${options.filename}.${getExtension(options.format)}`,
            id: file.id,
            targetFormat: options.format,
            compressionQuality: options.quality,
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
        const davClient = getClient();
        const result = await davClient.stat(getRootPath() + dirname + json.newFilename, { details: true, data: getDefaultPropfind() })

        // FileStat type guard
        function isResponseDataDetailed(result: FileStat | ResponseDataDetailed<FileStat>): result is ResponseDataDetailed<FileStat> {
            return (result as ResponseDataDetailed<FileStat>).status !== undefined;
        }

        if (isResponseDataDetailed(result)) {
            emit("files:node:created", resultToNode(result.data))
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


async function startMultiConversion({ nodes: files, folder }: ActionContext): Promise<(boolean | null)[]> {
    console.log(`Started conversion of multiple ${files.length} files`);

    const options = await requestConversionOptions(files[0].basename, false)
    if (!options) {
        return files.map(() => false)
    }

    let executionError = false;

    // Add a trailing slash to dir
    let dirname = folder.dirname;
    if (!dirname.endsWith("/")) {
        dirname = dirname + "/"
    }

    // Check for unsupported MIME types
    const returnArray = files.map(file => {
        if (!file.mime.startsWith("image/")) {
            showError("Selection contains a file that is not an image", { timeout: -1 })
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
                filename: `${getBasename(file.basename)}.${getExtension(options.format)}`,
                id: file.id,
                targetFormat: options.format,
                compressionQuality: options.quality,
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
            const davClient = getClient();
            const result = await davClient.stat(getRootPath() + dirname + json.newFilename, { details: true, data: getDefaultPropfind() })

            if (isResponseDataDetailed(result)) {
                emit("files:node:created", resultToNode(result.data))
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


// Dialog loading 
function getExtension(format: string): string {
    return format.toLowerCase()
}

function getBasename(filename: string): string {
    const extensionIndex = filename.lastIndexOf('.')
    // If no extension was found, returns the filename as whole 
    return extensionIndex > 0 ? filename.slice(0, extensionIndex) : filename
}

async function requestConversionOptions(filename: string, allowFilename: boolean): Promise<ConversionOptions | null> {
    try {
        const requestToken = getRequestToken()
        if (!requestToken) {
            throw Error('Unable to get a request token!')
        }

        const response = await fetch(baseUrl + '/formats', {
            headers: { requesttoken: requestToken },
        })
        if (!response.ok) {
            throw Error('Unable to retrieve supported output formats.')
        }

        const result = await response.json() as { formats: string[] }
        if (!Array.isArray(result.formats) || result.formats.length === 0) {
            throw Error('No supported output formats are available.')
        }

        // Spawn dialog for conversion option selection
        const selection = await spawnDialog(ConversionOptionsDialog, {
            formats: result.formats,
            initialBasename: getBasename(filename),
            allowFilename,
        }) as unknown as ConversionOptions | undefined

        if (!selection || !selection.format || (allowFilename && !selection.filename)) {
            return null
        }
        return selection
    } catch (ex) {
        showError('Unable to open image conversion options.', { timeout: -1 })
        console.error('ImageConverter: Could not load conversion options:', ex)
        return null
    }
}