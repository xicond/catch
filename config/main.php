<?php

use yii\helpers\ArrayHelper;

Yii::setAlias('@app', __DIR__ . '/../app');

$config = [
    'id' => 'Catch',
    'basePath' => '@app',
    'controllerNamespace' => 'app\controllers',

    'aliases' => [
        '@runtime' => '@app/runtime',
    ],

    'controllerMap' => [
    ],

    'components' => [

        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        's3' => [
            'class' => 'creocoder\flysystem\AwsS3Filesystem',
            'credentials' => false,
            'bucket' => 'catch-code-challenge',
            'region' => 'ap-southeast-2',
            // 'version' => 'latest',
            // 'baseUrl' => 'your-base-url',
            // 'prefix' => 'your-prefix',
            // 'options' => [],
             'endpoint' => 'https://s3.ap-southeast-2.amazonaws.com'
        ],
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            'viewPath' => '@app/mail',
            // send all mails to a file by default. You have to set
            // 'useFileTransport' to false and configure a transport
            // for the mailer to send real emails.
            'useFileTransport' => true,
        ],
        /*,
        'db' => $db,*/




    ],


    /*
    'controllerMap' => [
        'fixture' => [ // Fixture generation command line.
            'class' => 'yii\faker\FixtureController',
        ],
    ],
    */
];

return $config;
