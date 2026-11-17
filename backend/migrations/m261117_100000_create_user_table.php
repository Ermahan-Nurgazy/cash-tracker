<?php

use yii\db\Migration;

class m261117_100000_create_user_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%user}}', [
            'id' => $this->primaryKey(),
            'email' => $this->string(255)->notNull(),
            'password_hash' => $this->string(255)->notNull(),
            'auth_key' => $this->string(32)->notNull(),
            'access_token' => $this->string(64),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->createIndex('ux_user_email', '{{%user}}', 'email', true);
        $this->createIndex('ux_user_access_token', '{{%user}}', 'access_token', true);
    }

    public function safeDown()
    {
        $this->dropTable('{{%user}}');
    }
}
