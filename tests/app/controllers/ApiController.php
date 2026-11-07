<?php
/**
 * @link https://github.com/borodulin/yii2-services
 * @license https://github.com/borodulin/yii2-services/blob/master/LICENSE.md
 */

namespace conquer\services\tests\app\controllers;

use conquer\services\tests\app\models\Author;
use conquer\services\tests\app\models\Book;
use conquer\services\tests\app\models\Node;
use conquer\services\tests\app\models\SoapModel;
use yii\base\UserException;
use yii\web\Controller;

/**
 * Class SoapController
 * @package conquer\services\tests\app\controllers
 */
class ApiController extends Controller
{
    public $enableCsrfValidation = false;

    public function actions()
    {
        return [
            'soap' => [
                'class' => 'conquer\services\WebServiceAction',
                'classMap' => [
                    'SoapModel' => SoapModel::className(),
                    'Node' => Node::class,
                    'Author' => Author::class,
                    'Book' => Book::class,
                ],
            ],
            'doc' => [
                'class' => 'conquer\services\WebServiceAction',
                'serviceOptions' => [
                    'generatorConfig' => ['class' => 'conquer\services\WsdlGenerator', 'bindingStyle' => 'document'],
                ],
            ],
        ];
    }
    /**
     * @param conquer\services\tests\app\models\SoapModel $myClass
     * @return string
     * @soap
     */
    public function soapTest($myClass)
    {
        return get_class($myClass);
    }

    /**
     * @param int $a
     * @param int $b
     * @return int
     * @soap
     */
    public function add($a, $b)
    {
        return $a + $b;
    }

    /**
     * Tree whose child references the root.
     * @return conquer\services\tests\app\models\Node
     * @soap
     */
    public function tree()
    {
        $root = new Node();
        $root->name = 'root';
        $child = new Node();
        $child->name = 'child';
        $child->parent = $root;
        $root->children = [$child];
        return $root;
    }

    /**
     * @param conquer\services\tests\app\models\Author $author
     * @return int
     * @soap
     */
    public function countBooks($author)
    {
        return count((array)$author->books);
    }

    /**
     * @return string
     * @soap
     */
    public function internalError()
    {
        throw new \RuntimeException('SQLSTATE[28000] Access denied for user app@10.0.0.5');
    }

    /**
     * @return string
     * @soap
     */
    public function phpError()
    {
        return intdiv(1, 0);
    }

    /**
     * @return string
     * @soap
     */
    public function userError()
    {
        throw new UserException('Order not found');
    }

    /**
     * @return string
     * @soap
     */
    public function soapFault()
    {
        throw new \SoapFault('Client', 'Invalid order number');
    }

    public function actionIndex()
    {
        return 'ok';
    }
}
