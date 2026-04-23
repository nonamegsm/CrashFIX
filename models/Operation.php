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
    public const STATUS_STARTED   = 1;
    public const STATUS_SUCCEEDED = 2;
    public const STATUS_FAILED    = 3;

    public const OPTYPE_IMPORTPDB            = 1;
    public const OPTYPE_PROCESS_CRASH_REPORT = 2;
    public const OPTYPE_DELETE_DEBUG_INFO    = 3;

    /**
     * Trim the operations log to roughly $topCount newest rows (legacy
     * PollCommand::deleteOldOperations semantics).
     */
    public static function deleteOldOperations(int $topCount = 1000): void
    {
        $totalCount = (int) static::find()->count();
        $limitCount = $totalCount - $topCount;
        if ($limitCount <= 0) {
            return;
        }
        $ops = static::find()
            ->where(['<>', 'status', self::STATUS_STARTED])
            ->orderBy(['timestamp' => SORT_DESC])
            ->limit($limitCount)
            ->all();
        foreach ($ops as $op) {
            $op->delete();
        }
    }

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
