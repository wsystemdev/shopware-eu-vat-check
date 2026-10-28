<?php declare(strict_types=1);
// Use the plugin's own vendor (dev), else a Shopware project autoloader via SHOPWARE_AUTOLOAD.
$autoload = getenv('SHOPWARE_AUTOLOAD') ?: __DIR__ . '/../vendor/autoload.php';
$loader = require $autoload;
$loader->addPsr4('WSystem\\EuVatCheck\\', __DIR__ . '/../src/');
$loader->addPsr4('WSystem\\EuVatCheck\\Tests\\', __DIR__ . '/');
