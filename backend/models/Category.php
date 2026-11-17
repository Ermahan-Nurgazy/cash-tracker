<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Категория расходов или доходов.
 *
 * user_id задаётся на сервере из текущего пользователя, а не из тела запроса:
 * в контроллере присваивать его нужно после load().
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $type expense|income
 * @property string|null $color Цвет в формате #RRGGBB
 * @property string|null $icon
 * @property int $created_at
 *
 * @property User $user
 * @property Transaction[] $transactions
 */
class Category extends ActiveRecord
{
    use OwnedByUserTrait;

    public const TYPE_EXPENSE = 'expense';
    public const TYPE_INCOME = 'income';

    public const TYPES = [self::TYPE_EXPENSE, self::TYPE_INCOME];

    public static function tableName(): string
    {
        return '{{%category}}';
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'updatedAtAttribute' => false,
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['name', 'type', 'color', 'icon'], 'trim'],
            [['color', 'icon'], 'default', 'value' => null],
            [['user_id', 'name', 'type'], 'required'],
            ['user_id', 'integer'],
            ['user_id', 'exist', 'targetClass' => User::class, 'targetAttribute' => 'id'],
            ['name', 'string', 'max' => 100],
            ['type', 'in', 'range' => self::TYPES],
            ['type', 'validateTypeChange'],
            [
                'name',
                'unique',
                'targetAttribute' => ['user_id', 'name', 'type'],
                'message' => 'Такая категория уже есть.',
            ],
            ['color', 'match', 'pattern' => '/^#[0-9a-fA-F]{6}$/', 'message' => 'Цвет в формате #RRGGBB.'],
            ['icon', 'string', 'max' => 50],
        ];
    }

    /**
     * Тип нельзя менять, если по категории уже есть операции: их тип должен совпадать с типом категории.
     */
    public function validateTypeChange(string $attribute): void
    {
        if (!$this->isNewRecord && $this->isAttributeChanged($attribute) && $this->getTransactions()->exists()) {
            $this->addError($attribute, 'Нельзя изменить тип категории, по которой есть операции.');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'name' => 'Название',
            'type' => 'Тип',
            'color' => 'Цвет',
            'icon' => 'Иконка',
        ];
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getTransactions(): ActiveQuery
    {
        return $this->hasMany(Transaction::class, ['category_id' => 'id']);
    }
}
