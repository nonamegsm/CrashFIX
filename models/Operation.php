<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_operation".
 *
 * @property int $id
 * @property int $status
 * @property int $timestamp
 * @property int $optype
 * @property int $srcid
 * @property string $cmdid
 * @property string|null $operand1
 * @property string|null $operand2
 * @property string|null $operand3
 */
class Operation extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_operation';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['operand1', 'operand2', 'operand3'], 'default', 'value' => null],
            [['status', 'timestamp', 'optype', 'srcid', 'cmdid'], 'required'],
            [['status', 'timestamp', 'optype', 'srcid'], 'integer'],
            [['operand1', 'operand2', 'operand3'], 'string'],
            [['cmdid'], 'string', 'max' => 32],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'status' => 'Status',
            'timestamp' => 'Timestamp',
            'optype' => 'Optype',
            'srcid' => 'Srcid',
            'cmdid' => 'Cmdid',
            'operand1' => 'Operand1',
            'operand2' => 'Operand2',
            'operand3' => 'Operand3',
        ];
    }

}
