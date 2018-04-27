Web Service for Yii2 framework
=================
[![Build Status](https://travis-ci.org/borodulin/yii2-services.svg?branch=master)](https://travis-ci.org/borodulin/yii2-services)

## Description

WebService encapsulates SoapServer and provides a WSDL-based web service.

Adaptation of Yii1 Web Services

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

To install, either run

```
$ php composer.phar require conquer/services "*"
```
or add

```
"conquer/services": "*"
```

to the ```require``` section of your `composer.json` file.

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

**conquer/services** is released under the BSD License. See the bundled `LICENSE.md` for details.
