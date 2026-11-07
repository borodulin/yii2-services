<?php
/**
 * @link https://github.com/borodulin/yii2-services
 * @license https://github.com/borodulin/yii2-services/blob/master/LICENSE.md
 */

namespace conquer\services\tests\app\models;

/**
 * Author and Book reference each other.
 */
class Author
{
    /**
     * @var string
     * @soap
     */
    public $name;
    /**
     * @var conquer\services\tests\app\models\Book[]
     * @soap
     */
    public $books;
}
