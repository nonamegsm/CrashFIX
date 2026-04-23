<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_module".
 *
 * @property int $id
 * @property int $crashreport_id
 * @property string $name
 * @property int $sym_load_status
 * @property int|null $loaded_debug_info_id
 * @property string $file_version
 * @property int|null $timestamp
 */
class Module extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_module';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['loaded_debug_info_id', 'timestamp'], 'default', 'value' => null],
            [['crashreport_id', 'name', 'sym_load_status', 'file_version'], 'required'],
            [['crashreport_id', 'sym_load_status', 'loaded_debug_info_id', 'timestamp'], 'integer'],
            [['name'], 'string', 'max' => 512],
            [['matching_pdb_guid'], 'string', 'max' => 256],
            [['file_version'], 'string', 'max' => 32],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'crashreport_id' => 'Crashreport ID',
            'name' => 'Name',
            'sym_load_status' => 'Sym Load Status',
            'loaded_debug_info_id' => 'Loaded Debug Info ID',
            'file_version' => 'File Version',
            'timestamp' => 'Timestamp',
        ];
    }

    public function getCrashreport()
    {
        return $this->hasOne(Crashreport::class, ['id' => 'crashreport_id']);
    }

    public function getDebuginfo()
    {
        return $this->hasOne(Debuginfo::class, ['id' => 'loaded_debug_info_id']);
    }
}
