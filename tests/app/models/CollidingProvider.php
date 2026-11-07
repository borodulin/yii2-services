<?php
/**
 * @link https://github.com/borodulin/yii2-services
 * @license https://github.com/borodulin/yii2-services/blob/master/LICENSE.md
 */

namespace conquer\services\tests\app\models;

class CollidingProvider
{
    /**
     * @param conquer\services\tests\app\models\Node $a
     * @param conquer\services\tests\app\models\other\Node $b
     * @return string
     * @soap
     */
    public function both($a, $b)
    {
        return '';
    }
}
