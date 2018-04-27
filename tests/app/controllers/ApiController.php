<?php
/**
 * @link https://github.com/borodulin/yii2-services
 * @license https://github.com/borodulin/yii2-services/blob/master/LICENSE.md
 */

namespace conquer\services\tests\app\controllers;

use conquer\services\tests\app\models\SoapModel;
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

    public function actionIndex()
    {
        return 'ok';
    }
}
