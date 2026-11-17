<?php

use yii\db\Migration;

class m261117_100100_create_account_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%account}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'name' => $this->string(100)->notNull(),
            'currency' => $this->char(3)->notNull(),
            'initial_balance' => $this->decimal(14, 2)->notNull()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('ux_account_user_name', '{{%account}}', ['user_id', 'name'], true);
        $this->addForeignKey(
            'fk_account_user',
            '{{%account}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropTable('{{%account}}');
    }
}
