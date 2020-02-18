<?php
namespace OCA\Test\AppInfo;

use OCP\AppFramework\App;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Util;
use OCA\Test\Storage\ConvertStorage;

class Application extends App {

    public function __construct() {
        parent::__construct('test');

        $container = $this->getContainer();

        /**
         * Storage Layer
         */
        $container->registerService('ConvertStorage', function($c) {
            return new ConvertStorage($c->query('RootStorage'));
        });

        $container->registerService('RootStorage', function($c) {
            return $c->query('ServerContainer')->getUserFolder();
        });


    }

    public function registerListeners () {
        /* @var IEventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->query(IEventDispatcher::class);
        $dispatcher->addListener( 'OCA\Files::loadAdditionalScripts', function() {
            Util::addScript('test', 'script' );
            Util::addStyle('test', 'style' );
        });
    }

    
}