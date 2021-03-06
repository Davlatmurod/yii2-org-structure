<?php

namespace andahrm\structure\controllers;

use Yii;
use andahrm\structure\models\Position;
use andahrm\structure\models\PositionSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\Json;
use yii\helpers\ArrayHelper;
use andahrm\structure\models\PositionLine;
use andahrm\structure\models\PositionType;
use andahrm\structure\models\PositionLevel;
use andahrm\positionSalary\models\PersonPositionSalary;
use yii\web\Response;
use yii\widgets\ActiveForm;
use yii\data\ActiveDataProvider;

/**
 * PositionController implements the CRUD actions for Position model.
 */
class PositionController extends Controller {

    /**
     * @inheritdoc
     */
    public function behaviors() {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all Position models.
     * @return mixed
     */
    public function actions() {
        $this->layout = 'position';
    }

    public function actionIndex($code = null) {
        if ($code) {
            $models = Position::find()->all();
            foreach ($models as $model) {
                $model->code = $model->generatCode;
                $model->save(false);
            }
        }

        $searchModel = new PositionSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->sort->defaultOrder = [
            'person_type_id' => SORT_ASC,
            'section_id' => SORT_ASC,
            'position_line_id' => SORT_ASC,
            'number' => SORT_ASC,
        ];

        return $this->render('index', [
                    'searchModel' => $searchModel,
                    'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Position model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id) {
        $model = $this->findModel($id);
        $dataProvider = new ActiveDataProvider([
            'query' => $model->getPersonPositionSalaries(),
            'pagination' => [
                'pageSize' => 10,
            ],
            'sort' => [
                'defaultOrder' => [
                    'adjust_date' => SORT_DESC,
                // 'title' => SORT_ASC, 
                ]
            ],
        ]);


        return $this->render('view', [
                    'model' => $model,
                    'dataProvider' => $dataProvider
        ]);
    }

    /**
     * Creates a new Position model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate() {
        $model = new Position(['scenario' => 'insert']);

        if ($model->load(Yii::$app->request->post())) {
            //echo $model->person_type_id.'-'.$model->section_id.'-'.$model->position_line_id.'-'.$model->number;
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                $valid = ActiveForm::validate($model);
                if ($model->getExists())
                    $valid['position-number'] = [Yii::t('andahrm/structure', 'This sequence already exists.')];
                return $valid;
                Yii::$app->end();
            }

            //print_r(Yii::$app->request->post());
            //exit();
            if ($model->save()) {
                Yii::$app->getSession()->setFlash('saved', [
                    'type' => 'success',
                    'msg' => Yii::t('andahrm', 'Save operation completed.')
                ]);
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }
        return $this->render('create', [
                    'model' => $model,
        ]);
    }

    public function actionCreateAjax($formAction = null) {
        $model = new Position(['scenario' => 'insert']);

        if (Yii::$app->request->isPost) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;


            $success = false;
            $result = null;

            $request = Yii::$app->request;
            $post = Yii::$app->request->post();
            //print_r($post);
            if (Yii::$app->request->isAjax && $model->load($post) && $request->post('ajax')) {
                return ActiveForm::validate($model);
            } elseif ($request->post('save') && $model->load($post)) {

                if ($model->save()) {
                    $success = true;
                    $result = $model->attributes;
                } else {
                    $result = $model->getErrors();
                }
                return ['success' => $success, 'result' => $result];
            }
        }

        return $this->renderPartial('_form', [
                    'model' => $model,
                    'formAction' => $formAction
        ]);
    }

    /**
     * Updates an existing Position model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id) {
        $model = $this->findModel($id);
        $model->scenario = 'update';

        if ($model->load(Yii::$app->request->post())) {
            //print_r(Yii::$app->request->post());

            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                $valid = ActiveForm::validate($model);
                return $valid;
            }

            if ($model->save()) {
                Yii::$app->getSession()->setFlash('saved', [
                    'type' => 'success',
                    'msg' => Yii::t('andahrm', 'Save operation completed.')
                ]);
                return $this->redirect(['view', 'id' => $model->id]);
            } else {
                print_r($model->getErrors());
                exit();
            }
        }
        return $this->render('update', [
                    'model' => $model,
        ]);
    }

    /**
     * Deletes an existing Position model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id) {
        if (!Yii::$app->user->can('position-delete')) {
            throw new ForbiddenHttpException(Yii::t('hrm', 'You cannot permission delete.'));
        }

        $model = $this->findModel($id);
        $model->status = Position::STASUS_CLOSE;
        $model->save();
        return $this->redirect(['index']);
    }

    /**
     * Finds the Position model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Position the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id) {
        if (($model = Position::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

    protected function MapData($datas, $fieldId, $fieldName) {
        $obj = [];
        foreach ($datas as $key => $value) {
            array_push($obj, ['id' => $value->{$fieldId}, 'name' => $value->{$fieldName}]);
        }
        return $obj;
    }

    public function actionGetPositionLine() {
        $out = [];
        $post = Yii::$app->request->post();
        if ($post['depdrop_parents']) {
            $parents = $post['depdrop_parents'];
            if ($parents != null) {
                $person_type_id = $parents[0];
                $out = $this->getPositionLine($person_type_id);
                echo Json::encode(['output' => $out, 'selected' => '']);
                return;
            }
        }
        echo Json::encode(['output' => '', 'selected' => '']);
    }

    protected function getPositionLine($id) {
        $datas = PositionLine::find()->where(['person_type_id' => $id])->all();
        return $this->MapData($datas, 'id', 'titleCode');
    }

    public function actionGetPositionType() {
        $out = [];
        $post = Yii::$app->request->post();
        if ($post['depdrop_parents']) {
            $parents = $post['depdrop_parents'];
            if ($parents != null) {
                $person_type_id = $parents[0];
                $out = $this->getPositionType($person_type_id);
                echo Json::encode(['output' => $out, 'selected' => '']);
                return;
            }
        }
        echo Json::encode(['output' => '', 'selected' => '']);
    }

    protected function getPositionType($id) {
        $datas = PositionType::find()->where(['person_type_id' => $id])->all();
        return $this->MapData($datas, 'id', 'title');
    }

    public function actionGetPositionLevel() {
        $out = [];
        $post = Yii::$app->request->post();
        if ($post['depdrop_parents']) {
            $parents = $post['depdrop_parents'];
            if ($parents != null) {
                $positon_type_id = $parents[0];
                $out = $this->getPositionLevel($positon_type_id);
                echo Json::encode(['output' => $out, 'selected' => '']);
                return;
            }
        }
        echo Json::encode(['output' => '', 'selected' => '']);
    }

    protected function getPositionLevel($position_type_id = null) {
        $datas = PositionLevel::find()
                ->where(['position_type_id' => $position_type_id])
                ->all();
        return $this->MapData($datas, 'id', 'title');
    }

    public $code;

    public function actionPositionList($q = null, $id = null) {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON; //????????????????????????????????????????????????????????????????????? json
        $out = ['results' => ['id' => '', 'text' => '']];
        if (!is_null($q)) {
            //$this->code = $q;
            $model = Position::find();
            $model->andFilterWhere(['like', 'code', $q]);
            $model->orFilterWhere(['like', 'title', $q]);
            $out['results'] = ArrayHelper::getColumn($model->all(), function($model) {
                        return ['id' => $model->id, 'text' => $model->codeTitle];
                    });
        }
        return $out;
    }

    public function actionGetTitle($q = null) {
        $data = Position::find()
                        ->select('title')->distinct()
                        ->where('title LIKE "%' . $q . '%"')
                        ->orderBy('title')->all();

        $data1 = PersonPositionSalary::find()
                        ->select('title')->distinct()
                        ->where('title LIKE "%' . $q . '%"')
                        ->orderBy('title')->all();

        $out = [];
        foreach ($data as $d) {
            $out[] = ['value' => $d->title];
        }
        foreach ($data1 as $d) {
            $out[] = ['value' => $d->title];
        }
        echo Json::encode($out);
    }

}
