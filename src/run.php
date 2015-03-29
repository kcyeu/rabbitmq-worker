#!/usr/bin/php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Assistive helpers, not necessary
//require_once 'config.php';
//require_once 'error_handlers.php';
require_once 'Daemon.php';

use Uitox\PHPDaemon;

// The run() method will start the daemon event loop.
PHPDaemon\Daemon::getInstance()->run();
