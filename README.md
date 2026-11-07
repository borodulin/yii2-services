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
- Yii 2.0.14 or newer (2.0.x)
- ext-soap

## Installation

Install the extension via [Composer](https://getcomposer.org):

```bash
composer require conquer/services:^1.7
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

### Complex types

Public properties marked with `@soap` become elements of the complex type. Types may reference themselves
or each other (trees, parent/child links), and object graphs with cycles are serialized with SOAP references:

```php
class Node
{
    /**
     * @var string
     * @soap
     */
    public $name;
    /**
     * @var app\models\Node
     * @soap
     */
    public $parent;
    /**
     * @var app\models\Node[]
     * @soap
     */
    public $children;
}
```

The WSDL type name is the short class name, so two complex types must not share it
(an `InvalidConfigException` names both classes).

### Errors

Exceptions thrown by a web method are returned as SOAP faults:

- `SoapFault` is sent as is, e.g. `throw new \SoapFault('Client', 'Invalid order number')`;
- the message of `yii\base\UserException` is sent to the client;
- any other exception or PHP error is logged and reported as `Internal server error`
  (with `YII_DEBUG` on, the message and the trace are sent).

### WSDL documentation

Open the WSDL URL with the `makedoc` parameter (`/site/soap?makedoc`) to see an HTML overview of the complex types.
Set `'serviceOptions' => ['enableHtmlDocs' => false]` to disable it.

## Security notes

- Since 1.6.0 `SoapServer` loads the WSDL from a file generated into `@runtime/wsdl` (`WebService::$useLocalWsdl`).
  Earlier versions fetched it over HTTP from a URL built from the request `Host` header, which allowed
  server-side request forgery.
- The WSDL served to clients contains the service URL, built from the request host when
  `WebServiceAction::$serviceUrl` is not set. Configure `serviceUrl`/`wsdlUrl` explicitly or set
  `Request::$hostInfo` (or `trustedHosts`) in production.
- Since 1.7.0 messages of internal exceptions are not sent to clients unless `YII_DEBUG` is on.

## Upgrading to 1.7

- In production, exceptions other than `SoapFault` and `yii\base\UserException` are reported to clients as
  `Internal server error` instead of their message. Throw one of these for errors the client should see.
- `WebService::run()` returns the response instead of writing it and calling `exit()`; the application now
  finishes the request normally (`afterRequest`, logging, response events).
- `WsdlGenerator::generateWsdl()` no longer reacts to `?makedoc`; `WebService::renderWsdl()` does (WSDL requests only).
- Set `WebService::$wsdlCacheDuration` to cache the generated WSDL; it is also used for the WSDL loaded by `SoapServer`.

## License

**conquer/services** is released under the BSD License. See the bundled [`LICENSE.md`](LICENSE.md) for details.
