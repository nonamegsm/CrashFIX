<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_stackframe".
 *
 * @property int $id
 * @property int $thread_id
 * @property int $addr_pc
 * @property int|null $module_id
 * @property int|null $offs_in_module
 * @property string|null $symbol_name
 * @property string|null $und_symbol_name
 * @property int|null $offs_in_symbol
 * @property string|null $src_file_name
 * @property int|null $src_line
 * @property int|null $offs_in_line
 */
class Stackframe extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_stackframe';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['module_id', 'offs_in_module', 'symbol_name', 'und_symbol_name', 'offs_in_symbol', 'src_file_name', 'src_line', 'offs_in_line'], 'default', 'value' => null],
            [['thread_id', 'addr_pc'], 'required'],
            [['thread_id', 'addr_pc', 'module_id', 'offs_in_module', 'offs_in_symbol', 'src_line', 'offs_in_line'], 'integer'],
            [['symbol_name', 'und_symbol_name'], 'string', 'max' => 2048],
            [['src_file_name'], 'string', 'max' => 512],
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
            'addr_pc' => 'Addr Pc',
            'module_id' => 'Module ID',
            'offs_in_module' => 'Offs In Module',
            'symbol_name' => 'Symbol Name',
            'und_symbol_name' => 'Und Symbol Name',
            'offs_in_symbol' => 'Offs In Symbol',
            'src_file_name' => 'Src File Name',
            'src_line' => 'Src Line',
            'offs_in_line' => 'Offs In Line',
        ];
    }

    public function getThread()
    {
        return $this->hasOne(Thread::class, ['id' => 'thread_id']);
    }

    public function getModule()
    {
        return $this->hasOne(Module::class, ['id' => 'module_id']);
    }

    /**
     * Builds a printable one-line title for this stack frame, in the form
     *   ModuleName!Symbol+0x10  (file.cpp:42)
     * Falls back gracefully when symbol/source information is missing.
     */
    public function getTitle(): string
    {
        $parts = [];

        if ($this->module_id) {
            $module = $this->module;
            if ($module) {
                $parts[] = $module->name;
            }
        }

        $sym = $this->und_symbol_name ?: $this->symbol_name;
        if ($sym !== null && $sym !== '') {
            $parts[] = '!' . $sym;
            if ($this->offs_in_symbol) {
                $parts[] = sprintf('+0x%X', (int) $this->offs_in_symbol);
            }
        } else {
            $parts[] = sprintf('+0x%X', (int) $this->addr_pc);
        }

        $title = implode('', $parts);

        if ($this->src_file_name) {
            $line = $this->src_line ? (':' . (int) $this->src_line) : '';
            $title .= "  ({$this->src_file_name}{$line})";
        }

        return $title ?: sprintf('0x%X', (int) $this->addr_pc);
    }
}
