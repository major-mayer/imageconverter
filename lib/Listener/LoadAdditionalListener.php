<?php

namespace OCA\ImageConverter\Listener;

use OCA\ImageConverter\AppInfo\Application;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

class LoadAdditionalListener implements IEventListener
{

    public function handle(Event $event): void
    {
        if (!($event instanceof LoadAdditionalScriptsEvent)) {
            return;
        }

        // Load the imageConverterScript after the 'files' app has loaded
        Util::addScript(Application::APP_ID, 'imageconverter-main', "files");
    }
}
