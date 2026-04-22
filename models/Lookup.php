<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "tbl_lookup".
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property int $code
 * @property int $position
 */
class Lookup extends ActiveRecord
{
    private static $_items = [];

    public static function tableName()
    {
        return '{{%lookup}}';
    }

    public static function items($type, $maxCode = null)
    {
        if (!isset(self::$_items[$type])) {
            self::loadItems($type, $maxCode);
        }
        return self::$_items[$type];
    }

    public static function item($type, $code)
    {
        if (!isset(self::$_items[$type])) {
            self::loadItems($type);
        }
        return isset(self::$_items[$type][$code]) ? self::$_items[$type][$code] : false;
    }

    private static function loadItems($type, $maxCode = null)
    {
        self::$_items[$type] = [];
        $query = static::find()->where(['type' => $type])->orderBy('position');
        if ($maxCode !== null) {
            $query->andWhere(['<=', 'code', $maxCode]);
        }
        $models = $query->all();
        foreach ($models as $model) {
            self::$_items[$type][$model->code] = $model->name;
        }
    }

    public static function reset()
    {
        self::$_items = [];
    }
}
