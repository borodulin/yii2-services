<?php
/**
 * @link https://github.com/borodulin/yii2-services
 * @license https://github.com/borodulin/yii2-services/blob/master/LICENSE.md
 */

namespace conquer\services;

use Yii;
use yii\base\Action;
use yii\helpers\Url;

/**
 * WebServiceAction implements an action that provides Web services.
 *
 * WebServiceAction serves for two purposes. On the one hand, it displays
 * the WSDL content specifying the Web service APIs. On the other hand, it
 * invokes the requested Web service API. A GET parameter named <code>ws</code>
 * is used to differentiate these two aspects: the existence of the GET parameter
 * indicates performing the latter action.
 *
 * By default, WebServiceAction will use the current controller as
 * the Web service provider. See {@link WsdlGenerator} on how to declare
 * methods that can be remotely invoked.
 *
 * Note, PHP SOAP extension is required for this action.
 *
 * @property WebService $service The Web service instance.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @package system.web.services
 * @since 1.0
 */
class WebServiceAction extends Action
{
    /**
     * @var mixed the Web service provider object or class name.
     * If specified as a class name, it can be a path alias.
     * Defaults to null, meaning the current controller is used as the service provider.
     * If the provider implements the interface {@link IWebServiceProvider},
     * it will be able to intercept the remote method invocation and perform
     * additional tasks (e.g. authentication, logging).
     */
    public $provider;
    /**
     * @var string the URL for the Web service. Defaults to null, meaning
     * the URL for this action is used to provide Web services.
     * In this case, a GET parameter named {@link serviceVar} will be used to
     * deteremine whether the current request is for WSDL or Web service.
     */
    public $serviceUrl;
    /**
     * @var string the URL for WSDL. Defaults to null, meaning
     * the URL for this action is used to serve WSDL document.
     */
    public $wsdlUrl;
    /**
     * @var string the name of the GET parameter that differentiates a WSDL request
     * from a Web service request. If this GET parameter exists, the request is considered
     * as a Web service request; otherwise, it is a WSDL request.  Defaults to 'ws'.
     */
    public $serviceVar = 'ws';
    /**
     * @var array a list of PHP classes that are declared as complex types in WSDL.
     * This should be an array with WSDL types as keys and names of PHP classes as values.
     * A PHP class can also be specified as a path alias.
     * @see http://www.php.net/manual/en/soapclient.soapclient.php
     */
    public $classMap;
    /**
     * @var array the initial property values for the {@link WebService} object.
     * The array keys are property names of {@link WebService} and the array values
     * are the corresponding property initial values.
     */
    public $serviceOptions = [];

    /**
     * @var WebService
     */
    private $_service;

    public function init()
    {
        $this->controller->enableCsrfValidation = false;
    }


    /**
     * Runs the action.
     * If the GET parameter {@link serviceVar} exists, the action handle the remote method invocation.
     * If not, the action will serve WSDL content;
     * @throws \ReflectionException
     * @throws \yii\base\InvalidConfigException
     */
    public function run()
    {
        $hostInfo = Yii::$app->getRequest()->getHostInfo();
        $controller = $this->controller;
        if (($serviceUrl = $this->serviceUrl) === null) {
            $serviceUrl = $hostInfo . Url::toRoute([$this->getUniqueId(), $this->serviceVar => 1]);
        }
        if (($wsdlUrl = $this->wsdlUrl) === null) {
            $wsdlUrl = $hostInfo . Url::toRoute([$this->getUniqueId()]);
        }
        if (($provider = $this->provider) === null) {
            $provider = $controller;
        }
        $this->_service = $this->createWebService($provider, $wsdlUrl, $serviceUrl);

        if (is_array($this->classMap)) {
            $this->_service->classMap = $this->classMap;
        }
        foreach ($this->serviceOptions as $name => $value) {
            $this->_service->$name = $value;
        }
        if (isset($_GET[$this->serviceVar])) {
            $this->_service->run();
        } else {
            return $this->_service->renderWsdl();
        }
    }

    /**
     * Returns the Web service instance currently being used.
     * @return WebService the Web service instance
     */
    public function getService()
    {
        return $this->_service;
    }

    /**
     * Creates a {@link WebService} instance.
     * You may override this method to customize the created instance.
     * @param mixed $provider the web service provider class name or object
     * @param string $wsdlUrl the URL for WSDL.
     * @param string $serviceUrl the URL for the Web service.
     * @return WebService the Web service instance
     */
    protected function createWebService($provider, $wsdlUrl, $serviceUrl)
    {
        return new WebService($provider, $wsdlUrl, $serviceUrl);
    }
}
