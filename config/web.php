<?php
$secrets = require __DIR__ . '/secrets.php';
$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';
// $urlmanager = isset($_ENV['DOCKERCONTAINER']) ? require __DIR__ . '/urlmanager.php' : [];
$urlmanager = require __DIR__ . '/urlmanager.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    // 'defaultRoute' => 'api/index',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'Erc20' => ['class' => 'app\components\Erc20'],
        // 'WebApp' => ['class' => 'app\components\WebApp'],
        // 'Settings' => ['class' => 'app\components\Settings'],
        // 'Messages' => ['class' => 'app\components\Messages'],
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => $secrets['cookieValidationKey'],
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ]
        ],

        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\MPUsers',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'v1/error',
        ],

        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            // send all mails to a file by default. You have to set
            // 'useFileTransport' to false and configure a transport
            // for the mailer to send real emails.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,

        'defaultRoute' => 'v1/index',

        // in docker container enable the urlMAnager
        'urlManager' => $urlmanager,

    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
