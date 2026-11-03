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

## License

**conquer/services** is released under the BSD License. See the bundled [`LICENSE.md`](LICENSE.md) for details.
