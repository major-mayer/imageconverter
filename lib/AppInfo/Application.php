<?php

namespace OCA\ImageConverter\AppInfo;

use OCP\Util;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;

use OCA\ImageConverter\Storage\ConvertStorage;


class Application extends App implements IBootstrap
{

    public function __construct()
    {
        parent::__construct("imageconverter");
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


        // Register the composer autoloader for packages shipped by this app, if applicable
        include_once __DIR__ . '/../../vendor/autoload.php';
    }

    public function boot(IBootContext $context): void
    {
        // ... boot logic goes here ...

        /** @var IEventDispatcher $dispatcher */
        $dispatcher = $context->getAppContainer()->get(IEventDispatcher::class);
        $dispatcher->addListener('OCA\Files::loadAdditionalScripts', function () {
            Util::addScript('imageconverter', 'imageConverterScript');
        });
    }
}
