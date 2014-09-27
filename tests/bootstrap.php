<?php

require_once __DIR__ . '/../vendor/autoload.php';

Tester\Environment::setup();

function isWindows() {
	return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}

$loader = new Nette\Loaders\RobotLoader;
$loader->setCacheStorage(new Nette\Caching\Storages\DevNullStorage);

$loader->addDirectory(__DIR__);
$loader->addDirectory(__DIR__ . '/../src');

$loader->register();

$configurator = new Nette\Configurator;
$configurator->setTempDirectory(__DIR__ . '/temp');
$configurator->addConfig(__DIR__ . '/config/config.neon');
return $configurator->createContainer();