<?php

namespace app\controllers;

use app\components\PartTool;
use app\models\Material;
use app\services\MaterialFileTool;
use app\services\ProjectPlanFileTool;
use Yii;
use yii\web\HttpException;
use app\models\Part;
use app\models\Project;
use app\models\PartPart;
use app\models\PartType;
use app\models\ProjectPlan;
use app\models\PartMaterial;
use app\models\PartMeasureUnit;
use app\models\PartStandardDesignation;
use app\services\PartEditorTool;
use app\components\ProjectPlanTool;
use app\components\ProjectPlanRealizationTypeTool;
use yii\web\UploadedFile;

class ProjectPlanController extends ApiController
{
    public $modelClass = 'app\models\ProjectPlan';

    /**
     * @throws HttpException
     */
    public function actionReadManually($id)
    {
        $projectPlan = ProjectPlan::find()->where("id = $id")->one();

        if (!$projectPlan) throw new HttpException(400, 'ProjectPlan not found.');
        $part = $projectPlan->part;
        $main = PartPart::find()->where(['parent_id' => $part->id])->exists();
        if(is_null($projectPlan->material) || empty($projectPlan->material)){
            $partMaterial = PartMaterial::find()
                ->where("part_id = $part->id")
                ->one();

            if ($partMaterial) $material = $partMaterial->material;
        } else {
            $materialPp = ProjectPlanTool::getMaterial($projectPlan);
            $material = Material::find()->where("id = $materialPp->id")->one();
            $partMaterial = PartMaterial::find()
                ->where("part_id = $part->id")
                ->one();
        }

        $data = [
            'project_plan' => $projectPlan->toArray(['project_id', 'realization_type_id', 'status_id', 'item_count', 'parent_id', 'order_required_at', 'description', 'material_size','priority']),
            'part' => array_merge(
                [
                    'is_new' => false,
                    'id' => $part->id,
                    'fields' => $part->toArray(self::PART_COMMON_FIELDS),
                ],
                [
                    'measure_unit' => PartEditorTool::objectState($part, 'measureUnit', ['name']),
                    'standard_designation' => PartEditorTool::objectState($part, 'standardDesignation', ['name']),
                    'type' => PartEditorTool::objectState($part, 'type', ['name']),
                    'order_plan_id' => PartTool::getOrderPlans($part->id, $projectPlan->project_id)
                ]
            ),
            'material' => isset($material) ? [
                'is_new' => false,
                'id' => $materialPp->id ?? $material->id,
                'amount' => $materialPp->amount ?? $partMaterial->amount,
                'width_sheet' => $materialPp->width_sheet ?? null,
                'height_sheet' => $materialPp->height_sheet ?? null,
                'width_part' => $materialPp->width_part ?? null,
                'height_part' => $materialPp->height_part ?? null,
                'indent' => $materialPp->indent?? null,
                'fields' => [
                    'name' => $material->name,
                    'measure_unit' => PartEditorTool::objectState($material, 'measureUnit', ['name']),
                ],
            ] : null,
            'children' => $part->children,
            'main' => $main,
            'vendor' => ProjectPlanTool::getVendor($projectPlan),
            'delivery_date' => ProjectPlanTool::getDeliveryDate($projectPlan),
            'schemeFileName' => ($item = $part->item) && ($file = $item->file)? $file->name : null,
            'item_id' => ($item = $part->item) ? $item->id : null,
            'order_id' => ($order = ($orderPlan = $projectPlan->orderPlan) ? $orderPlan->order : null) ? $order->id : null,
            'files' => isset($projectPlan->files) ? ProjectPlanTool::expandFiles($projectPlan->files) : null,
            'filesMaterial' => isset($projectPlan->material) ? ProjectPlanTool::expandFilesMaterials($material->id) : null,
        ];

        $tag = ['tag' => null];
        if(!empty($projectPlan->orderPlan)){
            if(!empty($projectPlan->orderPlan->tag->name)) {
                $tag =['tag' => $projectPlan->orderPlan->tag->name];
            }
        }
        return array_merge($data, $tag);
    }
    

    /**
     * @throws HttpException
     */
    private static function updatePartMaterialFields(array $materialData, PartMaterial $partMaterial)
    {
        $amount = $materialData['amount'];

        if (isset($amount) && $partMaterial->amount != $amount) {
            $partMaterial->amount = $amount;
            PartEditorTool::saveAndValidate($partMaterial);
        }
    }

    public function actionTree()
    {
        $request = Yii::$app->request;
        $project_id = $request->get('project_id');

        if (is_null($project_id) || !Project::findById($project_id)) throw new HttpException(400, 'Project not found.');

        $projectPlans = ProjectPlan::find()->where("project_id = $project_id")->all();

        $roots = [];

        $c = [1];
        foreach ($projectPlans as $planUnit) {
            if ($planUnit->parent_id == null) {
                $root = ProjectPlanTool::yetAnotherTree($planUnit, $c);
                if ($root) {
                    $roots[] = $root;
                }
                $c[array_key_last($c)] += 1;
            }
        }

        return $roots;
    }

    /**
     * @throws HttpException
     */
    public function actionChangeStatusMany()
    {
        $request = Yii::$app->request;
        $status_id = $request->post('status_id');
        $project_plan_ids = $request->post('project_plan_ids');

        foreach ($project_plan_ids as $id) {
            $pp = ProjectPlan::find()->where("id = $id")->one();
            $pp->status_id = $status_id;
            PartEditorTool::saveAndValidate($pp);
        }
    }

    public function actionDeleteMany()
    {
        $request = Yii::$app->request;
        $project_plan_ids = $request->post('project_plan_ids');

        foreach ($project_plan_ids as $id) {
            $pp = ProjectPlan::find()->where("id = $id")->one();
            if ($pp) $pp->delete();
        }
    }

    public function actionRemoveFromOrderPlan()
    {

        /**
         * @var ProjectPlan|null $projectPlan
         */

        $id = Yii::$app->request->get('id');

        $projectPlan = ProjectPlan::find()->where("id = $id")->one();

        if (!is_null($projectPlan->orderPlan->order_id)) {
            $this->Response(400, 'Действие запрещено - деталь находится в заказе.');
            return;
        }

        ProjectPlanTool::removeFromOrderPlan($projectPlan);
    }

    public function actionUploadFile($id)
    {
        /**
         * @var ProjectPlan|null $pp
         */

        $pp = ProjectPlan::find()->where("id = $id")->one();
        if (!$pp) return $this->Response(400, "Project Plan doesn't exists.");

        $uploads = UploadedFile::getInstancesByName("file");
        if (empty($uploads)) throw new HttpException(400, 'Multipart/form-data field `file` is empty.', 1);
        $file = &$uploads[0];

        $fileTool = new ProjectPlanFileTool($pp);
        $fileTool->saveFile($file);

        return true;
    }

    public function actionUploadDxf($id)
    {
        /**
         * @var Material|null $material
         */
        $request = Yii::$app->request;
        $materialId = $request->post('material_id');

        $material = Material::find()->where("id = $materialId")->one();
        if (!$material) return $this->Response(400, "$material doesn't exists.");

        $uploads = UploadedFile::getInstancesByName("file");
        if (empty($uploads)) throw new HttpException(400, 'Multipart/form-data field `file` is empty.', 1);
        $file = &$uploads[0];

        $fileTool = new MaterialFileTool($material);
        $fileTool->saveDxf($file, $id, $materialId);

        return true;
    }
}
