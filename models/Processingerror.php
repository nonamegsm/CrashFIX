<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_processingerror".
 *
 * @property int $id
 * @property int $type
 * @property int $srcid
 * @property string|null $message
 */
class Processingerror extends \yii\db\ActiveRecord
{
    // Type discriminator. Matches the constants in the Yii1 legacy
    // model (protected/models/ProcessingError.php) so the same
    // tbl_processingerror table can be read consistently from both
    // frontends.
    const TYPE_CRASH_REPORT_ERROR = 1;
    const TYPE_DEBUG_INFO_ERROR   = 2;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_processingerror';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['message'], 'default', 'value' => null],
            [['type', 'srcid'], 'required'],
            [['type', 'srcid'], 'integer'],
            [['message'], 'string'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'type' => 'Type',
            'srcid' => 'Srcid',
            'message' => 'Message',
        ];
    }

    public static function record(int $type, int $srcid, string $message): bool
    {
        $row = new static();
        $row->type = $type;
        $row->srcid = $srcid;
        $row->message = $message;
        if (!$row->save()) {
            Yii::error('Could not save processing error record', 'poll');
            return false;
        }
        return true;
    }

}
