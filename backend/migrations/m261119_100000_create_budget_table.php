<?php

use yii\db\Migration;

/**
 * Бюджеты: лимит расходов по категории на календарный месяц в одной валюте.
 *
 * period_month всегда первое число месяца. Валюта хранится в бюджете, потому что
 * суммы в разных валютах не складываются: тратить можно со счетов в разных валютах.
 */
class m261119_100000_create_budget_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%budget}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'category_id' => $this->integer()->notNull(),
            'period_month' => $this->date()->notNull(),
            'currency' => $this->string(3)->notNull(),
            'limit' => $this->decimal(14, 2)->notNull(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ]);

        $this->addCheck('ck_budget_limit', '{{%budget}}', '"limit" > 0');
        $this->addCheck('ck_budget_month', '{{%budget}}', "EXTRACT(DAY FROM period_month) = 1");

        $this->createIndex(
            'ux_budget_user_category_month_currency',
            '{{%budget}}',
            ['user_id', 'category_id', 'period_month', 'currency'],
            true
        );
        $this->createIndex('ix_budget_user_month', '{{%budget}}', ['user_id', 'period_month']);

        $this->addForeignKey('fk_budget_user', '{{%budget}}', 'user_id', '{{%user}}', 'id', 'CASCADE', 'CASCADE');
        // Бюджет не имеет смысла без категории: удаляется вместе с ней
        $this->addForeignKey('fk_budget_category', '{{%budget}}', 'category_id', '{{%category}}', 'id', 'CASCADE', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('{{%budget}}');
    }
}
