<?php

namespace app\models;

use app\services\ProjectPlanFileTool;
use Yii;

/**
 * This is the model class for table "project_plan".
 *
 * @property int $id
 * @property int|null $parent_id
 * @property int $project_id
 * @property int|null $realization_type_id
 * @property int|null $status_id
 * @property int $part_id
 * @property int $order_parent_id
 * @property float $material_size
 * @property int|null $order_plan_id
 * @property int|null $item_count
 * @property string|null $order_required_at
 * @property string|null $description
 * @property bool $priority
 * @property string|null $created_at
 * @property string|null $material_dimension
 * @property string|null $material
 *
 * @property ProjectPlan $parent
 * @property ProjectPlan $orderParent
 * @property ProjectPlan[] $children
 * @property Project $project
 * @property OrderPlan $orderPlan
 * @property Part $part
 * @property ProjectPlanRealizationType $realizationType
 * @property ProjectPlanStatus $status
 * @property File[] $files
 */
class ProjectPlan extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public function extraFields()
    {
        return [
            'realizationType' => function (self $model) {
                $rType = $model->realizationType;
                return $rType ? $rType->name : null;
            },
            'status' => function (self $model) {
                $status = $model->status;
                return $status ? $status->name : null;
            },
            'part',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'project_plan';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['project_id', 'part_id'], 'required'],
            [['project_id', 'part_id'], 'number'],
            [['order_required_at'], 'safe'],
            [['description','material_dimension'], 'string'],
            [['parent_id', 'project_id', 'realization_type_id', 'status_id', 'part_id', 'order_plan_id', 'item_count'], 'integer'],
            [['parent_id'], 'exist', 'skipOnError' => true, 'targetClass' => ProjectPlan::className(), 'targetAttribute' => ['parent_id' => 'id']],
            [['order_plan_id'], 'exist', 'skipOnError' => true, 'targetClass' => OrderPlan::className(), 'targetAttribute' => ['order_plan_id' => 'id']],
            [['part_id'], 'exist', 'skipOnError' => true, 'targetClass' => Part::className(), 'targetAttribute' => ['part_id' => 'id']],
            [['project_id'], 'exist', 'skipOnError' => true, 'targetClass' => Project::className(), 'targetAttribute' => ['project_id' => 'id']],
            [['realization_type_id'], 'exist', 'skipOnError' => true, 'targetClass' => ProjectPlanRealizationType::className(), 'targetAttribute' => ['realization_type_id' => 'id']],
            [['status_id'], 'exist', 'skipOnError' => true, 'targetClass' => ProjectPlanStatus::className(), 'targetAttribute' => ['status_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'parent_id' => 'Родительская запись',
            'project_id' => 'Проект',
            'realization_type_id' => 'Тип закупки',
            'status_id' => 'Статус',
            'part_id' => 'Деталь',
            'order_plan_id' => 'План закупки',
            'item_count' => 'Количество',
            'order_required_at' => 'Требуемая дата закупки',
            'description' => 'Описание',
        ];
    }

    public function recalculateAmountRecursively($multiplier, $excludeThis = false)
    {
        if (!$excludeThis) {
            $this->item_count = $this->item_count * $multiplier;
            $this->save();
        }

        foreach ($this->children as $children) {
            $children->recalculateAmountRecursively($multiplier);
        }
    }

    public function afterSave($insert, $changedAttributes)
    {
        (new ProjectPlanFileTool($this))->mkdir();

        parent::afterSave($insert, $changedAttributes);
    }

    public function beforeDelete()
    {
        (new ProjectPlanFileTool($this))->rmdir(true);

        return parent::beforeDelete();
    }

    /**
     * Gets query for [[Parent]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getParent()
    {
        return $this->hasOne(ProjectPlan::className(), ['id' => 'parent_id']);
    }

    /**
     * Gets query for [[orderParent]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getOrderParent()
    {
        return $this->hasOne(ProjectPlan::className(), ['id' => 'order_parent_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getChildren()
    {
        return $this->hasMany(ProjectPlan::className(), ['parent_id' => 'id']);
    }

    /**
     * Gets query for [[Project]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getProject()
    {
        return $this->hasOne(Project::className(), ['id' => 'project_id']);
    }

    /**
     * Gets query for [[OrderPlan]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getOrderPlan()
    {
        return $this->hasOne(OrderPlan::className(), ['id' => 'order_plan_id']);
    }

    /**
     * Gets query for [[Part]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPart()
    {
        return $this->hasOne(Part::className(), ['id' => 'part_id']);
    }

    /**
     * Gets query for [[RealizationType]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRealizationType()
    {
        return $this->hasOne(ProjectPlanRealizationType::className(), ['id' => 'realization_type_id']);
    }

    /**
     * Gets query for [[Status]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getStatus()
    {
        return $this->hasOne(ProjectPlanStatus::className(), ['id' => 'status_id']);
    }

    public function beforeSave($insert): bool
    {
        if (is_null($this->getAttribute('order_required_at'))) {
            $this->order_required_at = $this->project->order_required_at;
        }

        return parent::beforeSave($insert);
    }

    /**
     * Gets query for [[File]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getFiles()
    {
        return $this->hasMany(File::className(), ['project_plan_id' => 'id']);
    }
}
