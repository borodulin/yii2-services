<?php
/**
 * @link https://github.com/borodulin/yii2-services
 * @license https://github.com/borodulin/yii2-services/blob/master/LICENSE.md
 */

namespace conquer\services;

use Yii;
use yii\helpers\FileHelper;
use yii\base\Application;
use yii\base\Component;
use yii\base\Event;
use yii\base\UserException;
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

    /**
     * @var bool whether SoapServer should load the WSDL from a locally generated file.
     * When false, the WSDL is fetched over HTTP from {@link $wsdlUrl}, which is built from the request
     * Host header by default and therefore lets clients make the server fetch arbitrary URLs (SSRF).
     */
    public $useLocalWsdl = true;

    /**
     * @var string directory (path alias allowed) where generated WSDL files for SoapServer are stored.
     */
    public $wsdlPath = '@runtime/wsdl';

    /**
     * @var bool whether the WSDL URL with the "makedoc" GET parameter shows HTML documentation of the complex types.
     */
    public $enableHtmlDocs = true;

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
        $response = Yii::$app->response;
        if ($this->enableHtmlDocs && Yii::$app->request->get('makedoc') !== null) {
            $generator = Yii::createObject($this->generatorConfig);
            $generator->generateWsdl($this->getProviderClass(), $this->serviceUrl, $this->encoding);
            $response->format = Response::FORMAT_HTML;
            return $generator->buildHtmlDocs(true);
        }
        $wsdl = $this->generateWsdl();
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
        return $this->buildWsdl($this->serviceUrl);
    }

    /**
     * @param string $serviceUrl service location written into the WSDL
     * @return string the WSDL, from cache when {@link wsdlCacheDuration} is set
     * @throws \yii\base\InvalidConfigException
     */
    protected function buildWsdl($serviceUrl)
    {
        $providerClass = $this->getProviderClass();
        if ($this->wsdlCacheDuration > 0 && $this->cacheID !== false && ($cache = Yii::$app->get($this->cacheID, false)) !== null) {
            $key = 'Yii.WebService.' . $providerClass . $serviceUrl . $this->encoding;
            if (($wsdl = $cache->get($key)) !== false) {
                return $wsdl;
            }
        }
        $generator = Yii::createObject($this->generatorConfig);
        $wsdl = $generator->generateWsdl($providerClass, $serviceUrl, $this->encoding);
        if (isset($key, $cache)) {
            $cache->set($key, $wsdl, $this->wsdlCacheDuration);
        }
        return $wsdl;
    }

    /**
     * @return string class name of the provider
     */
    protected function getProviderClass()
    {
        return is_object($this->provider) ? get_class($this->provider) : $this->provider;
    }

    /**
     * Generates the WSDL into a local file used by SoapServer.
     * The service location inside this file is irrelevant for SoapServer, so a fixed one is used:
     * the file does not depend on request headers and only one file per service is created.
     * @return string path to the WSDL file
     * @throws \yii\base\Exception
     */
    public function getLocalWsdlFile()
    {
        $wsdl = $this->buildWsdl('http://localhost/');
        $dir = Yii::getAlias($this->wsdlPath);
        FileHelper::createDirectory($dir);
        $file = $dir . DIRECTORY_SEPARATOR . md5($wsdl) . '.wsdl';
        if (!is_file($file)) {
            $tmp = $file . '.' . uniqid('', true);
            file_put_contents($tmp, $wsdl);
            rename($tmp, $file);
        }
        return $file;
    }

    /**
     * Handles the web service request.
     *
     * The SOAP response written by SoapServer is returned as the application response
     * (with the status code and content type set by SoapServer).
     * @return Response
     */
    public function run()
    {
        if (YII_DEBUG) {
            ini_set('soap.wsdl_cache_enabled', 0);
        }
        $level = ob_get_level();
        ob_start();
        try {
            $server = new \SoapServer($this->useLocalWsdl ? $this->getLocalWsdlFile() : $this->wsdlUrl, $this->getOptions());
            if ($this->persistence !== null) {
                $server->setPersistence($this->persistence);
            }
            $provider = is_string($this->provider) ? Yii::createObject($this->provider) : $this->provider;
            $server->setObject($this->createProviderWrapper($provider));
            if ($provider instanceof IWebServiceProvider) {
                if ($provider->beforeWebMethod($this)) {
                    $server->handle();
                    $provider->afterWebMethod($this);
                }
            } else {
                $server->handle();
            }
            $statusCode = http_response_code();
            $contentType = $this->getSentContentType();
        } catch (\Throwable $e) {
            // outside the web method: SoapServer setup, beforeWebMethod(), afterWebMethod()
            while (ob_get_level() > $level + 1) {
                ob_end_clean();
            }
            ob_clean();
            echo $this->renderFault($this->createFault($e));
            $statusCode = 500;
            $contentType = ($this->soapVersion === '1.2' ? 'application/soap+xml' : 'text/xml') . '; charset=' . $this->encoding;
        }
        while (ob_get_level() > $level + 1) {
            ob_end_flush();
        }
        $content = ob_get_clean();
        if (!headers_sent()) {
            header_remove('Content-Type');
            header_remove('Content-Length');
        }

        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->charset = $this->encoding;
        $response->headers->set('Content-Type', $contentType ?: 'text/xml; charset=' . $this->encoding);
        if (is_int($statusCode) && $statusCode >= 100) {
            $response->setStatusCode($statusCode);
        }
        $response->content = $content;
        return $response;
    }

    /**
     * Calls a web method of the provider.
     * Exceptions other than SoapFault are converted by {@link createFault()}.
     * @param callable $callback
     * @param array $arguments
     * @return mixed
     * @throws \SoapFault
     */
    public function invokeProvider($callback, $arguments)
    {
        try {
            return call_user_func_array($callback, $arguments);
        } catch (\SoapFault $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw $this->createFault($e);
        }
    }

    /**
     * Converts an exception thrown by the provider into a SOAP fault sent to the client.
     *
     * SoapFault is sent as is; the message of {@link UserException} is shown to the client.
     * Other exceptions are logged and reported as "Internal server error",
     * unless YII_DEBUG is on, in which case the message and the trace are sent.
     * @param \Throwable $e
     * @return \SoapFault
     */
    protected function createFault($e)
    {
        if ($e instanceof \SoapFault) {
            return $e;
        }
        if (!$e instanceof UserException) {
            Yii::error((string)$e, __METHOD__);
        }
        $code = (new \ReflectionClass($e))->getShortName();
        if (YII_DEBUG) {
            $message = $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ")\n" . $e->getTraceAsString();
        } elseif ($e instanceof UserException) {
            $message = $e->getMessage();
        } else {
            $code = 'Server';
            $message = 'Internal server error';
        }
        return new \SoapFault($code, $message);
    }

    /**
     * @param \SoapFault $fault
     * @return string SOAP envelope with the fault
     */
    protected function renderFault($fault)
    {
        $code = htmlspecialchars((string)$fault->faultcode, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars((string)$fault->getMessage(), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        if ($this->soapVersion === '1.2') {
            return '<?xml version="1.0" encoding="UTF-8"?>'
                . '<env:Envelope xmlns:env="http://www.w3.org/2003/05/soap-envelope"><env:Body><env:Fault>'
                . '<env:Code><env:Value>env:Receiver</env:Value></env:Code>'
                . '<env:Reason><env:Text xml:lang="en">' . $message . '</env:Text></env:Reason>'
                . '</env:Fault></env:Body></env:Envelope>';
        }
        if (strpos($code, ':') === false && in_array($code, ['Server', 'Client', 'VersionMismatch', 'MustUnderstand'], true)) {
            $code = 'SOAP-ENV:' . $code;
        }
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><SOAP-ENV:Body><SOAP-ENV:Fault>'
            . '<faultcode>' . $code . '</faultcode><faultstring>' . $message . '</faultstring>'
            . '</SOAP-ENV:Fault></SOAP-ENV:Body></SOAP-ENV:Envelope>';
    }

    /**
     * @param object $provider
     * @return object the object passed to SoapServer::setObject()
     */
    protected function createProviderWrapper($provider)
    {
        if (is_array($this->generatorConfig) && isset($this->generatorConfig['bindingStyle'])
            && $this->generatorConfig['bindingStyle'] === 'document'
        ) {
            return new DocumentSoapObjectWrapper($provider, ['service' => $this]);
        }
        return new SoapObjectWrapper($provider, $this);
    }

    /**
     * @return string|null the Content-Type header set by SoapServer
     */
    private function getSentContentType()
    {
        foreach (array_reverse(headers_list()) as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                return trim(substr($header, 13));
            }
        }
        return null;
    }

    /**
     * @return string the currently requested method name. Empty if no method is being requested.
     */
    public function getMethodName()
    {
        if ($this->_method === null) {
            $request = file_get_contents('php://input');
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
