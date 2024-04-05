<?php

namespace OCA\ImageConverter\AppInfo;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\ImageConverter\Listener\LoadAdditionalListener;
use OCP\Util;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;

use OCA\ImageConverter\Storage\ConvertStorage;


class Application extends App implements IBootstrap
{
    public const APP_ID = 'imageconverter';

    public function __construct(array $urlParams = [])
    {
        parent::__construct(self::APP_ID, $urlParams);
    }


    public function register(IRegistrationContext $context): void
    {
        // ... registration logic goes here ...

        $context->registerService('ConvertStorage', function ($c) {
            return new ConvertStorage($c->query('RootStorage'));
        });

        $context->registerService('RootStorage', function ($c) {
            return $c->query('ServerContainer')->getUserFolder();
        });

        $context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalListener::class);
    }
}
