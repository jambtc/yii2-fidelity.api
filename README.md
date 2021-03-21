<p align="center">
    <a href="https://github.com/jambtc/api.fidelity" target="_blank">
        <img src="https://avatars0.githubusercontent.com/u/993323" height="100px">
    </a>
    <h1 align="center">Yii 2 Fidelity Api Project</h1>
    <br>
</p>

Yii 2 Api Project is the Fidelity REST API manager [Yii 2](http://www.yiiframework.com/) application project.

There are two components:
1. the API that receives Rules Engine commands
2. the webhook manager that manages the shopping cart events


## Api
The rules engine can trigger the api  sending his payload to https://api.example.com/api/v1

## Webhook
The webhook can receive events from:
1. WooCommerce
2. ...
3. ...


#### WooCommerce
In the Fidelity dashboard the merchant have to set the Api keys for woocommerce plugin. Then, in WooCommerce add a new webhook and set these informations:
1. **Topic**: select `Order updated`
2. **Delivery URL**: is a url containing:
    1. **storeid**: the store id
    2. **pkey**: the public api key

    Then the url will be like this:
    `https://api.example.com/webhook/woocommerce?storeid=ZjdlTHl4N0Rxdkd0ZmlrUS81&pkey=g3WfwBQGpVzie4XnsY`
3. **Secret**: the secret api key generated from Fidelity dashboard Api keys Manager

#### other e-commerce integration
</br>
</br>
</br>






[![Latest Stable Version](https://img.shields.io/packagist/v/yiisoft/yii2-app-basic.svg)](https://packagist.org/packages/yiisoft/yii2-app-basic)
[![Total Downloads](https://img.shields.io/packagist/dt/yiisoft/yii2-app-basic.svg)](https://packagist.org/packages/yiisoft/yii2-app-basic)
[![build](https://github.com/yiisoft/yii2-app-basic/workflows/build/badge.svg)](https://github.com/yiisoft/yii2-app-basic/actions?query=workflow%3Abuild)




REQUIREMENTS
------------

The minimum requirement by this project template that your Web server supports PHP 5.6.0.


INSTALLATION
------------


### Install with Docker

    docker login

    docker run --rm -p 8000:80 jambtc/apifidelity-yii2


### Install with docker-compose

Clone the package from github

    git clone https://github.com/jambtc/yii2-api.fidelity.git

Update your vendor packages

    docker-compose run --rm php composer update --prefer-dist

Run the installation triggers (creating cookie validation code)

    docker-compose run --rm php composer install    

Start the container

    docker-compose up -d

You can then access the application through the following URL:

    http://127.0.0.1:8000

**NOTES:**
- You can use any ports you want by changing the value in the file `docker-compose.yml`
- Minimum required Docker engine version `17.04` for development (see [Performance tuning for volume mounts](https://docs.docker.com/docker-for-mac/osxfs-caching/))
- The default configuration uses a host-volume in your home directory `.docker-composer` for composer caches


CONFIGURATION
-------------

### Database

Rename the file `config/db.example.php` in `db-docker.php` and edit with real data, for example:

```php
return [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host=localhost;dbname=database_name',
    'username' => 'root',
    'password' => 'mystrongpassword',
    'charset' => 'utf8',
];
```

**NOTES:**
- Yii won't create the database for you, this has to be done manually before you can access it.
- Check and edit the other files in the `config/` directory to customize your application as required.
- Refer to the README in the `tests` directory for information specific to basic application tests.
