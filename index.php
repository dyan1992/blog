<?php

use Yaf\Application;

define('APP_PATH',__DIR__.'/application');

$application = new Application(__DIR__ . '/conf/application.ini');

$application->bootstrap()->run();