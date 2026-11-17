<?php

use yii\db\Migration;

class m261117_100300_create_transaction_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%transaction}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'account_id' => $this->integer()->notNull(),
            'category_id' => $this->integer()->notNull(),
            'amount' => $this->decimal(14, 2)->notNull(),
            'type' => $this->string(10)->notNull(),
            'occurred_at' => $this->date()->notNull(),
            'note' => $this->text(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->addCheck('ck_transaction_type', '{{%transaction}}', "type IN ('expense', 'income')");
        $this->addCheck('ck_transaction_amount', '{{%transaction}}', 'amount > 0');

        $this->createIndex('ix_transaction_user_occurred', '{{%transaction}}', ['user_id', 'occurred_at']);
        $this->createIndex('ix_transaction_account', '{{%transaction}}', 'account_id');
        $this->createIndex('ix_transaction_category', '{{%transaction}}', 'category_id');

        // Пользователя удаляем вместе с данными, а счёт и категорию с операциями удалить нельзя
        $this->addForeignKey(
            'fk_transaction_user',
            '{{%transaction}}',
            'user_id',
            '{{%user}}',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_transaction_account',
            '{{%transaction}}',
            'account_id',
            '{{%account}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk_transaction_category',
            '{{%transaction}}',
            'category_id',
            '{{%category}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropTable('{{%transaction}}');
    }
}
