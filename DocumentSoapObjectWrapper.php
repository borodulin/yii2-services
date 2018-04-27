<?php
/**
 * @link https://github.com/borodulin/yii2-services
 * @license https://github.com/borodulin/yii2-services/blob/master/LICENSE.md
 */

namespace conquer\services;

use yii\base\Component;

/**
 * DocumentSoapObjectWrapper is a wrapper class internally used
 * when generatorConfig contains bindingStyle key set to document value.
 *
 * @author Jan Was <jwas@nets.com.pl>
 * @package system.web.services
 */
class DocumentSoapObjectWrapper extends Component
{
    /**
     * @var object the service provider
     */
    public $object = null;

    /**
     * Constructor.
     * @param object $object the service provider
     * @param array $config
     */
    public function __construct($object, $config = [])
    {
        $this->object = $object;
        parent::__construct($config);
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
        if (is_array($arguments) && isset($arguments[0])) {
            $result = call_user_func_array([$this->object, $name], (array)$arguments[0]);
        } else {
            $result = call_user_func_array([$this->object, $name], $arguments);
        }
        return $result === null ? $result : [$name . 'Result' => $result];
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
