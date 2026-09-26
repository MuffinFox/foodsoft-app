# Foodsoft Pickup Apps

Intended to be installed at https://pickup.foodcoops.at/

requires an additional foodsoft API controller: https://github.com/foodcoopsat/foodsoft/pull/17/

## Activation for Foodcoops

For each foodcoop, a copy of the `template-foodcoop` directory has to be generated and named with the foodcoop's name identically like in the foodsoft url https://app.foodcoops.at/(fc-name). The permissions of the directory have to be `0777` te enable the php-daemon to make directories and write data.
The app can then be called via https://pickup.foodcoops.at/(fc-name)

The index.php file in this folder has to contain the oauth access credentials for the foodsoft of the foodcoop and optional configuration parameters.

## Dependencies (Composer)

PHP libraries are managed with [Composer](https://getcomposer.org/). `composer.json` and `composer.lock` live in this `pickup` directory; the libraries are installed into `vendor/`, which are not tracked in git.

Install the dependencies:
```
cd pickup
composer install
```

Add a new library:
```
composer require vendor/package
```
Commit the changed `composer.json` and `composer.lock`. Load libraries in PHP via the autoloader instead of including files directly:
```php
require_once __DIR__ . "/vendor/autoload.php";
```

Update libraries within the version constraints of `composer.json`:
```
composer update
```

## Local Test
The app can be tested in combination with a local foodsoft installation.
