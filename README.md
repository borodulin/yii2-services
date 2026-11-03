# Web Service for Yii2 framework

[![Latest Version](https://img.shields.io/packagist/v/conquer/services.svg)](https://packagist.org/packages/conquer/services)
[![Total Downloads](https://img.shields.io/packagist/dt/conquer/services.svg)](https://packagist.org/packages/conquer/services)
[![CI](https://github.com/borodulin/yii2-services/actions/workflows/ci.yml/badge.svg)](https://github.com/borodulin/yii2-services/actions/workflows/ci.yml)
[![License](https://img.shields.io/packagist/l/conquer/services.svg)](LICENSE.md)

## Description

WebService encapsulates SoapServer and provides a WSDL-based web service.

Adaptation of Yii1 Web Services

## Requirements

- PHP 7.4 or 8.x
- Yii 2.0.6 or newer (2.0.x)

## Installation

Install the extension via [Composer](https://getcomposer.org):

```bash
composer require conquer/services:^1.5
```

## Usage

```php
namespace app\controllers;

class SiteController extends \yii\web\Controller
{
    public function actions()
    {
        return [
            'soap' => [
                'class' => 'conquer\services\WebServiceAction',
                'classMap' => [
                    'MyClass' => 'app\controllers\MyClass'
                ],
            ],
        ];
    }
    /**
     * @param app\controllers\MyClass $myClass
     * @return string
     * @soap
     */
    public function soapTest($myClass)
    {
        return get_class($myClass);
    }
}

class MyClass
{
    /**
     * @var string
     * @soap
     */
    public $name;
}
```

## Security notes

- Since 1.6.0 `SoapServer` loads the WSDL from a file generated into `@runtime/wsdl` (`WebService::$useLocalWsdl`).
  Earlier versions fetched it over HTTP from a URL built from the request `Host` header, which allowed
  server-side request forgery.
- The WSDL served to clients contains the service URL, built from the request host when
  `WebServiceAction::$serviceUrl` is not set. Configure `serviceUrl`/`wsdlUrl` explicitly or set
  `Request::$hostInfo` (or `trustedHosts`) in production.

## License

**conquer/services** is released under the BSD License. See the bundled [`LICENSE.md`](LICENSE.md) for details.
