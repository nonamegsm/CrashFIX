<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_crashgroup".
 *
 * @property int $id
 * @property int $created
 * @property int $status
 * @property int $project_id
 * @property int $appversion_id
 * @property string $title
 * @property string $md5
 */
class Crashgroup extends \yii\db\ActiveRecord
{
    const STATUS_NEW = 1;
    public $crashReportCount;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_crashgroup';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['created', 'status', 'project_id', 'appversion_id', 'title', 'md5'], 'required'],
            [['created', 'status', 'project_id', 'appversion_id'], 'integer'],
            [['title'], 'string', 'max' => 256],
            [['md5'], 'string', 'max' => 32],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'created' => 'Created',
            'status' => 'Status',
            'project_id' => 'Project ID',
            'appversion_id' => 'Appversion ID',
            'title' => 'Title',
            'md5' => 'Md5',
        ];
    }

    /**
     * Finds a symbolized function title for offset-only collection names such
     * as "EasyJtag.exe!+0x91da1" using already imported debug stack frames.
     */
    public function getDebugInfoFunctionTitle(): string
    {
        $moduleName = '';
        $offset = null;
        if (preg_match('/^(.+)!\\+0x([0-9a-f]+)(?:\\s|$)/i', (string) $this->title, $matches)) {
            $moduleName = strtolower($this->shortModuleName($matches[1]));
            $offset = hexdec($matches[2]);
        }

        if ($offset === null) {
            return '';
        }

        $frame = $this->findSymbolizedFrameByModuleOffset((int) $offset, $moduleName);
        if ($frame !== null) {
            return $frame->getTitle();
        }

        return $this->findSymbolizedExceptionFrameTitle();
    }

    private function findSymbolizedFrameByModuleOffset(int $offset, string $moduleName): ?Stackframe
    {
        $frames = Stackframe::find()
            ->alias('sf')
            ->innerJoin(Thread::tableName() . ' th', 'th.id = sf.thread_id')
            ->innerJoin(Crashreport::tableName() . ' cr', 'cr.id = th.crashreport_id')
            ->leftJoin(Module::tableName() . ' m', 'm.id = sf.module_id')
            ->where(['cr.groupid' => (int) $this->id, 'sf.offs_in_module' => $offset])
            ->andWhere(['or',
                ['is not', 'sf.und_symbol_name', null],
                ['is not', 'sf.symbol_name', null],
            ])
            ->orderBy(['sf.id' => SORT_ASC])
            ->limit(25)
            ->all();

        foreach ($frames as $frame) {
            if ($moduleName === '') {
                return $frame;
            }
            $frameModule = $frame->module;
            if ($frameModule !== null && strtolower($this->shortModuleName((string) $frameModule->name)) === $moduleName) {
                return $frame;
            }
        }

        return null;
    }

    private function findSymbolizedExceptionFrameTitle(): string
    {
        $reports = Crashreport::find()
            ->where(['groupid' => (int) $this->id])
            ->andWhere(['is not', 'exception_thread_id', null])
            ->orderBy(['id' => SORT_ASC])
            ->limit(25)
            ->all();

        foreach ($reports as $report) {
            $thread = $report->exceptionThread;
            if ($thread === null) {
                continue;
            }
            $title = $thread->getExceptionStackFrameTitle();
            if ($title !== '' && $title !== (string) $this->title) {
                return $title;
            }
        }

        return '';
    }

    private function shortModuleName(string $moduleName): string
    {
        $moduleName = str_replace('\\', '/', $moduleName);
        return basename($moduleName);
    }

}
