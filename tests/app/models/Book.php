<?php
/**
 * @link https://github.com/borodulin/yii2-services
 * @license https://github.com/borodulin/yii2-services/blob/master/LICENSE.md
 */

namespace conquer\services\tests\app\models;

class Book
{
    /**
     * @var string
     * @soap
     */
    public $title;
    /**
     * @var conquer\services\tests\app\models\Author
     * @soap
     */
    public $author;
}
