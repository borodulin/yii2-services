<?php
// Entry script for the PHP built-in server used by SoapEndToEndCest.
defined('YII_DEBUG') or define('YII_DEBUG', getenv('YII_DEBUG') !== '0');
defined('YII_ENV') or define('YII_ENV', 'test');

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/test-config.php';
$config['components']['request']['cookieValidationKey'] = 'test';
$config['components']['request']['scriptFile'] = __FILE__;
$config['components']['request']['scriptUrl'] = '/index.php';
$config['runtimePath'] = sys_get_temp_dir() . '/yii2-services-test-runtime';

(new yii\web\Application($config))->run();
