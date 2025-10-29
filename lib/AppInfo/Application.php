<?php

namespace OCA\ImageConverter\AppInfo;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\ImageConverter\Listener\LoadAdditionalListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCA\ImageConverter\Storage\ConvertStorage;
use OCP\Files\IRootFolder;
use Psr\Container\ContainerInterface;

class Application extends App implements IBootstrap
{
    public const APP_ID = 'imageconverter';

    public function __construct(array $urlParams = [])
    {
        parent::__construct(self::APP_ID, $urlParams);
    }


    public function register(IRegistrationContext $context): void
    {
        // ... registration logic (DI) goes here ...

        // Register a ConvertStorage service, which needs an instance of IRootFolder
        // Reference: https://docs.nextcloud.com/server/latest/developer_manual/basics/dependency_injection.html#predefined-core-services
        $context->registerService(ConvertStorage::class, function (ContainerInterface $c) {
            return new ConvertStorage($c->get(IRootFolder::class));
        });

        $context->registerEventListener(LoadAdditionalScriptsEvent::class, LoadAdditionalListener::class);
    }

    public function boot(IBootContext $context): void
    {
    }
}
