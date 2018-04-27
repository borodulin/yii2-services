<?php
/**
 * @link https://github.com/borodulin/yii2-services
 * @license https://github.com/borodulin/yii2-services/blob/master/LICENSE.md
 */

namespace conquer\services;

/**
 * SoapObjectWrapper is a wrapper class internally used when SoapServer::setObject() is not defined.
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
     * Constructor.
     * @param object $object the service provider
     */
    public function __construct($object)
    {
        $this->object = $object;
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
