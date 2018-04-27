<?php
/**
 * @link https://github.com/borodulin/yii2-services
 * @license https://github.com/borodulin/yii2-services/blob/master/LICENSE.md
 */

namespace conquer\services;

use Yii;
use yii\base\Application;
use yii\base\Component;
use yii\base\Event;
use yii\web\Response;

/**
 * WebService encapsulates SoapServer and provides a WSDL-based web service.
 *
 * PHP SOAP extension is required.
 *
 * WebService makes use of {@link WsdlGenerator} and can generate the WSDL
 * on-the-fly without requiring you to write complex WSDL. However WSDL generator
 * could be customized through {@link generatorConfig} property.
 *
 * To generate the WSDL based on doc comment blocks in the service provider class,
 * call {@link generateWsdl} or {@link renderWsdl}. To process the web service
 * requests, call {@link run}.
 *
 * @property string $methodName The currently requested method name. Empty if no method is being requested.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @package system.web.services
 * @since 1.0
 */
class WebService extends Component
{
    const SOAP_ERROR = 1001;
    /**
     * @var string|object the web service provider class or object.
     * If specified as a class name, it can be a path alias.
     */
    public $provider;
    /**
     * @var string the URL for WSDL. This is required by {@link run()}.
     */
    public $wsdlUrl;
    /**
     * @var string the URL for the Web service. This is required by {@link generateWsdl()} and {@link renderWsdl()}.
     */
    public $serviceUrl;
    /**
     * @var integer number of seconds that the generated WSDL can remain valid in cache. Defaults to 0, meaning no caching.
     */
    public $wsdlCacheDuration = 0;
    /**
     * @var string the ID of the cache application component that is used to cache the generated WSDL.
     * Defaults to 'cache' which refers to the primary cache application component.
     * Set this property to false if you want to disable caching WSDL.
     */
    public $cacheID = 'cache';
    /**
     * @var string encoding of the Web service. Defaults to 'UTF-8'.
     */
    public $encoding = 'UTF-8';
    /**
     * A list of classes that are declared as complex types in WSDL.
     * This should be an array with WSDL types as keys and names of PHP classes as values.
     * A PHP class can also be specified as a path alias.
     * @var array
     * @see http://www.php.net/manual/en/soapserver.soapserver.php
     */
    public $classMap = [];
    /**
     * @var string actor of the SOAP service. Defaults to null, meaning not set.
     */
    public $actor;
    /**
     * @var string SOAP version (e.g. '1.1' or '1.2'). Defaults to null, meaning not set.
     */
    public $soapVersion;
    /**
     * @var integer the persistence mode of the SOAP server.
     * @see http://www.php.net/manual/en/soapserver.setpersistence.php
     */
    public $persistence;
    /**
     * WSDL generator configuration. This property may be useful in purpose of enhancing features
     * of the standard {@link WsdlGenerator} class by extending it. For example, some developers may need support
     * of the <code>xsd:xsd:base64Binary</code> elements. Another use case is to change initial values
     * at instantiation of the default {@link WsdlGenerator}. The value of this property will be passed
     * to {@link \Yii::createObject()} to create the generator object. Default value is 'WsdlGenerator'.
     * @var string|array
     * @since 1.1.12
     */
    public $generatorConfig;

    private $_method;


    /**
     * Constructor.
     * @param mixed $provider the web service provider class name or object
     * @param string $wsdlUrl the URL for WSDL. This is required by {@link run()}.
     * @param string $serviceUrl the URL for the Web service. This is required by {@link generateWsdl()} and {@link renderWsdl()}.
     * @param array $config
     */
    public function __construct($provider, $wsdlUrl, $serviceUrl, $config = [])
    {
        $this->provider = $provider;
        $this->wsdlUrl = $wsdlUrl;
        $this->serviceUrl = $serviceUrl;
        $this->generatorConfig = WsdlGenerator::className();
        parent::__construct($config);
    }

    /**
     * The PHP error handler.
     * @param Event $event the PHP error event
     * @throws \Exception
     */
    public function handleError($event)
    {
        $event->handled = true;
        $message = $event->message;
        if (YII_DEBUG) {
            $trace = debug_backtrace();
            if (isset($trace[2]) && isset($trace[2]['file']) && isset($trace[2]['line'])) {
                $message .= ' (' . $trace[2]['file'] . ':' . $trace[2]['line'] . ')';
            }
        }
        throw new \Exception($message, self::SOAP_ERROR);
    }

    /**
     * Generates and displays the WSDL as defined by the provider.
     * @see generateWsdl
     * @throws \yii\base\InvalidConfigException
     */
    public function renderWsdl()
    {
        $wsdl = $this->generateWsdl();
        $response = Yii::$app->response;
        $response->charset = $this->encoding;
        $response->format = Response::FORMAT_RAW;
        $response->headers->add('Content-Type', 'text/xml');
        //    header('Content-Length: '.(function_exists('mb_strlen') ? mb_strlen($wsdl,'8bit') : strlen($wsdl)));
        return $wsdl;
    }

    /**
     * Generates the WSDL as defined by the provider.
     * The cached version may be used if the WSDL is found valid in cache.
     * @return string the generated WSDL
     * @throws \yii\base\InvalidConfigException
     * @see wsdlCacheDuration
     */
    public function generateWsdl()
    {
        if (is_object($this->provider)) {
            $providerClass = get_class($this->provider);
        } else {
            $providerClass = $this->provider;
        }
        if ($this->wsdlCacheDuration > 0 && $this->cacheID !== false && ($cache = Yii::$app->get($this->cacheID, false)) !== null) {
            $key = 'Yii.WebService.' . $providerClass . $this->serviceUrl . $this->encoding;
            if (($wsdl = $cache->get($key)) !== false) {
                return $wsdl;
            }
        }
        $generator = Yii::createObject($this->generatorConfig);
        $wsdl = $generator->generateWsdl($providerClass, $this->serviceUrl, $this->encoding);
        if (isset($key, $cache)) {
            $cache->set($key, $wsdl, $this->wsdlCacheDuration);
        }
        return $wsdl;
    }

    /**
     * Handles the web service request.
     * @throws \ReflectionException
     */
    public function run()
    {
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->charset = $this->encoding;
        $response->headers->add('Content-Type', 'text/xml');
        if (YII_DEBUG) {
            ini_set('soap.wsdl_cache_enabled', 0);
        }
        $server = new \SoapServer($this->wsdlUrl, $this->getOptions());
        //    \Yii::$app->on($name, $behavior)EventHandler('onError',array($this,'handleError'));
        try {
            if ($this->persistence !== null) {
                $server->setPersistence($this->persistence);
            }
            if (is_string($this->provider)) {
                $provider = Yii::createObject($this->provider);
            } else {
                $provider = $this->provider;
            }
            if (method_exists($server, 'setObject')) {
                if (is_array($this->generatorConfig) && isset($this->generatorConfig['bindingStyle'])
                    && $this->generatorConfig['bindingStyle'] === 'document'
                ) {
                    $server->setObject(new DocumentSoapObjectWrapper($provider));
                } else {
                    $server->setObject($provider);
                }
            } else {
                if (is_array($this->generatorConfig) && isset($this->generatorConfig['bindingStyle'])
                    && $this->generatorConfig['bindingStyle'] === 'document'
                ) {
                    $server->setClass(DocumentSoapObjectWrapper::className(), $provider);
                } else {
                    $server->setClass(SoapObjectWrapper::className(), $provider);
                }
            }

            if ($provider instanceof IWebServiceProvider) {
                if ($provider->beforeWebMethod($this)) {
                    $server->handle();
                    $provider->afterWebMethod($this);
                }
            } else {
                $server->handle();
            }
        } catch (\Exception $e) {
            // non-PHP error
            if ($e->getCode() !== self::SOAP_ERROR) {
                // only log for non-PHP-error case because application's error handler already logs it
                // php <5.2 doesn't support string conversion auto-magically
                Yii::error($e->__toString());
            }

            $message = $e->getMessage();
            if (YII_DEBUG) {
                $message .= ' (' . $e->getFile() . ':' . $e->getLine() . ")\n" . $e->getTraceAsString();
            }
            // We need to end application explicitly because of
            // http://bugs.php.net/bug.php?id=49513
            Yii::$app->state = Application::STATE_AFTER_REQUEST;
            Yii::$app->trigger(Application::EVENT_AFTER_REQUEST);
            $reflect = new \ReflectionClass($e);
            $server->fault($reflect->getShortName(), $message);
            exit(1);
        }
    }

    /**
     * @return string the currently requested method name. Empty if no method is being requested.
     */
    public function getMethodName()
    {
        if ($this->_method === null) {
            if (isset($HTTP_RAW_POST_DATA)) {
                $request = $HTTP_RAW_POST_DATA;
            } else {
                $request = file_get_contents('php://input');
            }
            if (preg_match('/<.*?:Body[^>]*>\s*<.*?:(\w+)/mi', $request, $matches)) {
                $this->_method = $matches[1];
            } else {
                $this->_method = '';
            }
        }
        return $this->_method;
    }

    /**
     * @return array options for creating SoapServer instance
     * @see http://www.php.net/manual/en/soapserver.soapserver.php
     */
    protected function getOptions()
    {
        $options = [];
        if ($this->soapVersion === '1.1') {
            $options['soap_version'] = SOAP_1_1;
        } elseif ($this->soapVersion === '1.2') {
            $options['soap_version'] = SOAP_1_2;
        }
        if ($this->actor !== null) {
            $options['actor'] = $this->actor;
        }
        $options['encoding'] = $this->encoding;
        foreach ($this->classMap as $type => $className) {
            if (is_int($type)) {
                $type = $className;
            }
            $options['classmap'][$type] = $className;
        }
        return $options;
    }
}
