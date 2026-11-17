<?php

use yii\db\Migration;

class m261117_100200_create_category_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%category}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'name' => $this->string(100)->notNull(),
            'type' => $this->string(10)->notNull(),
            'color' => $this->string(7),
            'icon' => $this->string(50),
            'created_at' => $this->integer()->notNull(),
        ]);

        $this->addCheck('ck_category_type', '{{%category}}', "type IN ('expense', 'income')");
        $this->createIndex('ux_category_user_name_type', '{{%category}}', ['user_id', 'name', 'type'], true);
        $this->addForeignKey(
            'fk_category_user',
            '{{%category}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropTable('{{%category}}');
    }
}
