#!/usr/bin/env php
<?php

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require_once(__DIR__ . '/vendor/autoload.php');

$config = require __DIR__ . '/config/main.php';
$application = new yii\console\Application($config);
$exitCode = $application->run();
exit($exitCode);
