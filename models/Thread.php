<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_thread".
 *
 * @property int $id
 * @property int $thread_id
 * @property int $crashreport_id
 * @property string|null $stack_trace_md5
 */
class Thread extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_thread';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['stack_trace_md5'], 'default', 'value' => null],
            [['thread_id', 'crashreport_id'], 'required'],
            [['thread_id', 'crashreport_id'], 'integer'],
            [['stack_trace_md5'], 'string', 'max' => 32],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'thread_id' => 'Thread ID',
            'crashreport_id' => 'Crashreport ID',
            'stack_trace_md5' => 'Stack Trace Md5',
        ];
    }

    public function getCrashreport()
    {
        return $this->hasOne(Crashreport::class, ['id' => 'crashreport_id']);
    }

    public function getStackframes()
    {
        return $this->hasMany(Stackframe::class, ['thread_id' => 'id'])->orderBy('id ASC');
    }

    /**
     * Returns the human-readable name of this thread's entry point function.
     * Looks at the bottom-most stack frame (the first one created), which
     * tends to be the thread procedure / WinMain / main.
     */
    public function getThreadFuncName(): string
    {
        $bottom = Stackframe::find()
            ->where(['thread_id' => $this->id])
            ->orderBy('id DESC')
            ->one();

        if ($bottom === null) {
            return '';
        }
        return $bottom->und_symbol_name ?: ($bottom->symbol_name ?: '');
    }

    /**
     * Uppermost frame with an undecorated symbol, skipping CrashRpt modules.
     * Port of Yii1 Thread::getExceptionStackFrameTitle.
     */
    public function getExceptionStackFrameTitle(): string
    {
        $frames = Stackframe::find()
            ->where(['thread_id' => $this->id])
            ->andWhere(['is not', 'und_symbol_name', null])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $title = '';
        foreach ($frames as $stackFrame) {
            $title = $stackFrame->getTitle();
            $module = $stackFrame->module;
            if ($module !== null && preg_match('/^CrashRpt([0-9]{4})(d{0,1}){0,1}\.dll$/i', $module->name)) {
                continue;
            }
            break;
        }
        return $title;
    }
}
