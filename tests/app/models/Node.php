<?php
/**
 * @link https://github.com/borodulin/yii2-services
 * @license https://github.com/borodulin/yii2-services/blob/master/LICENSE.md
 */

namespace conquer\services\tests\app\models;

/**
 * Tree node: a type that references itself.
 */
class Node
{
    /**
     * @var string
     * @soap
     */
    public $name;
    /**
     * @var conquer\services\tests\app\models\Node
     * @soap
     */
    public $parent;
    /**
     * @var \conquer\services\tests\app\models\Node[]
     * @soap
     */
    public $children;
}
