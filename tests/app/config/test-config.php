<?php

return [
    'id' => 'test-api',
    'basePath' => dirname(__DIR__),
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'vendorPath' => dirname(__DIR__) . '/../../vendor',
    'controllerNamespace' => 'conquer\services\tests\app\controllers',
    'components' => [
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
        ],
    ],
];