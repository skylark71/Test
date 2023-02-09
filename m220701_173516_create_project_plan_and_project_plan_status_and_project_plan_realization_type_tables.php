<?php

use yii\db\Migration;

/**
 * Class m220701_173516_create_project_plan_and_project_plan_status_and_project_plan_realization_type_tables
 */
class m220701_173516_create_project_plan_and_project_plan_status_and_project_plan_realization_type_tables extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {

        $this->createTable('{{%project_plan}}', [
            'id' => $this->primaryKey(),
            'number' => $this->string(64),
            'amount' => $this->float()->notNull(),
            'project_id' => $this->integer()->notNull(),
            'item_id' => $this->integer(),
            'realization_type_id' => $this->integer(),
            'status_id' => $this->integer(),
        ]);

        $this->createTable('{{%project_plan_status}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(64)->notNull(),
        ]);

        $this->createTable('{{%project_plan_realization_type}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(64)->notNull(),
        ]);

        $this->addForeignKey(
            'project_plan_project_plan_status_fkey',
            '{{%project_plan}}',
            'status_id', '{{%project_plan_status}}',
            'id',
            'SET NULL');

        $this->addForeignKey(
            'project_plan_project_plan_realization_type_fkey',
            '{{%project_plan}}',
            'realization_type_id', '{{%project_plan_realization_type}}',
            'id',
            'SET NULL');

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('project_plan_project_plan_status_fkey', '{{%project_plan}}');
        $this->dropForeignKey('project_plan_project_plan_realization_type_fkey', '{{%project_plan}}');

        $this->dropTable('{{%project_plan_status}}');
        $this->dropTable('{{%project_plan}}');
    }
}
