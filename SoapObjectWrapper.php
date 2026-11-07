<?php
/**
 * @link https://github.com/borodulin/yii2-services
 * @license https://github.com/borodulin/yii2-services/blob/master/LICENSE.md
 */

namespace conquer\services;

/**
 * SoapObjectWrapper is the object passed to SoapServer: it calls the service provider
 * and converts its exceptions into SOAP faults (see {@link WebService::createFault()}).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @package system.web.services
 */
class SoapObjectWrapper
{
    /**
     * @var object the service provider
     */
    public $object = null;

    /**
     * @var WebService|null the web service; null calls the provider directly
     */
    public $service;

    /**
     * Constructor.
     * @param object $object the service provider
     * @param WebService|null $service
     */
    public function __construct($object, $service = null)
    {
        $this->object = $object;
        $this->service = $service;
    }

    /**
     * PHP __call magic method.
     * This method calls the service provider to execute the actual logic.
     * @param string $name method name
     * @param array $arguments method arguments
     * @return mixed method return value
     */
    public function __call($name, $arguments)
    {
        if ($this->service !== null) {
            return $this->service->invokeProvider([$this->object, $name], $arguments);
        }
        return call_user_func_array([$this->object, $name], $arguments);
    }

    /**
     * Returns the fully qualified name of this class.
     * @return string the fully qualified name of this class.
     */
    public static function className()
    {
        return get_called_class();
    }
}
