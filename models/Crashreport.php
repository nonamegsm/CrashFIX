<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "tbl_crashreport".
 *
 * @property int $id
 * @property string $srcfilename
 * @property int $filesize
 * @property int|null $date_created
 * @property int $received
 * @property int $status
 * @property string|null $ipaddress
 * @property string $md5
 * @property int $groupid
 * @property string|null $crashguid
 * @property int $project_id
 * @property int $appversion_id
 * @property string|null $emailfrom
 * @property string|null $description
 * @property string|null $crashrptver
 * @property string|null $exception_type
 * @property int|null $exception_code
 * @property int|null $exception_thread_id
 * @property string|null $exceptionmodule
 * @property int|null $exceptionmodulebase
 * @property int|null $exceptionaddress
 * @property string|null $exe_image
 * @property string|null $os_name_reg
 * @property string|null $os_ver_mdmp
 * @property int|null $os_is_64bit
 * @property string|null $geo_location
 * @property string|null $product_type
 * @property string|null $cpu_architecture
 * @property int|null $cpu_count
 * @property int|null $gui_resource_count
 * @property int|null $open_handle_count
 * @property int|null $memory_usage_kbytes
 *
 * @property \yii\web\UploadedFile $fileAttachment
 * @property string $appversionStr
 */
class Crashreport extends \yii\db\ActiveRecord
{
    use CrashreportPollTrait;

    public $fileAttachment;
    public $appversionStr;


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_crashreport';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['ipaddress', 'crashguid', 'emailfrom', 'description', 'crashrptver', 'exception_type', 'exception_code', 'exception_thread_id', 'exceptionmodule', 'exceptionmodulebase', 'exceptionaddress', 'exe_image', 'os_name_reg', 'os_ver_mdmp', 'os_is_64bit', 'geo_location', 'product_type', 'cpu_architecture', 'cpu_count', 'gui_resource_count', 'open_handle_count', 'memory_usage_kbytes'], 'default', 'value' => null],
            [['md5', 'project_id'], 'required'],
            [['filesize', 'date_created', 'received', 'status', 'groupid', 'project_id', 'appversion_id', 'exception_code', 'exception_thread_id', 'exceptionmodulebase', 'exceptionaddress', 'os_is_64bit', 'cpu_count', 'gui_resource_count', 'open_handle_count', 'memory_usage_kbytes'], 'integer'],
            [['description'], 'string'],
            [['srcfilename', 'exceptionmodule', 'os_name_reg'], 'string', 'max' => 512],
            [['ipaddress', 'md5', 'emailfrom'], 'string', 'max' => 32],
            [['crashguid'], 'string', 'max' => 36],
            [['crashrptver', 'geo_location'], 'string', 'max' => 16],
            [['exception_type', 'cpu_architecture'], 'string', 'max' => 64],
            [['exe_image'], 'string', 'max' => 1024],
            [['os_ver_mdmp', 'product_type'], 'string', 'max' => 128],
            [['appversionStr'], 'safe'],
            [['fileAttachment'], 'file', 'skipOnEmpty' => true],
        ];
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($insert) {
            $this->received = time();
            $this->date_created = $this->received;
            $this->status = 1; // Waiting (lookup CrashReportStatus code 1)
            $this->groupid = 0; // Not grouped yet
            $this->appversion_id = 0; // Unknown yet

            if ($this->fileAttachment) {
                $this->srcfilename = $this->fileAttachment->baseName . '.' . $this->fileAttachment->extension;
                $this->filesize    = (int) $this->fileAttachment->size;

                // Compute MD5 from the temp file before it is moved.
                if (empty($this->md5) && is_readable($this->fileAttachment->tempName)) {
                    $this->md5 = md5_file($this->fileAttachment->tempName) ?: '';
                }
            } else {
                $this->srcfilename = $this->srcfilename ?: 'unknown';
                $this->filesize    = (int) ($this->filesize ?: 0);
                if (empty($this->md5)) {
                    $this->md5 = str_repeat('0', 32);
                }
            }

            if (empty($this->ipaddress)) {
                $this->ipaddress = Yii::$app->request !== null ? (string) Yii::$app->request->userIP : '';
            }
        }

        return true;
    }

    /**
     * Persists the uploaded crash-report archive to disk after the model
     * row is saved. Call from the controller AFTER `$model->save()`
     * returns true so we know `$model->id` is assigned.
     */
    public function persistAttachment(): void
    {
        if (!$this->fileAttachment) {
            return;
        }
        $storage = Yii::$app->storage;
        $dest = $storage->crashReportPath((int) $this->project_id, (int) $this->id);
        $storage->writeUploadedFile($this->fileAttachment->tempName, $dest);
    }

    public function afterDelete()
    {
        parent::afterDelete();

        if (!Yii::$app->has('storage')) {
            return;
        }
        $storage = Yii::$app->storage;
        @unlink($storage->crashReportPath((int) $this->project_id, (int) $this->id));
        $storage->rmdirRecursive($storage->crashReportExtractDir((int) $this->project_id, (int) $this->id));
        $storage->rmdirRecursive($storage->crashReportThumbDir((int) $this->project_id, (int) $this->id));
    }

    /**
     * Send the entire crash-report ZIP to the browser.
     */
    public function dumpFileAttachmentContent(): void
    {
        $storage = Yii::$app->storage;
        $path = $storage->crashReportPath((int) $this->project_id, (int) $this->id);
        $storage->streamDownload($path, $this->srcfilename ?: ('crashreport-' . $this->id . '.zip'), true);
    }

    /**
     * Send a single file from inside the crash-report ZIP to the browser.
     * Files are extracted to a temp file on demand; cached extractions
     * under crashreports_extracted/{id}/ are preferred when present.
     *
     * @param string $name      File name as listed in the ZIP archive
     * @param bool   $forceDownload  Inline-render images/videos when false
     */
    public function dumpFileItemContent(string $name, bool $forceDownload = true): void
    {
        $storage = Yii::$app->storage;
        $cached = $storage->crashReportExtractDir((int) $this->project_id, (int) $this->id)
                . DIRECTORY_SEPARATOR . basename($name);

        if (is_file($cached)) {
            $storage->streamDownload($cached, basename($name), $forceDownload);
            return;
        }

        $zip  = $storage->crashReportPath((int) $this->project_id, (int) $this->id);
        $tmp  = $storage->extractZipEntry($zip, $name);
        if ($tmp === null) {
            throw new \yii\web\NotFoundHttpException('File not found in crash report archive.');
        }

        try {
            $storage->streamDownload($tmp, basename($name), $forceDownload);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Build (or serve a cached) thumbnail for a screenshot inside the
     * crash report ZIP.
     */
    public function dumpScreenshotThumbnail(string $name): void
    {
        $storage  = Yii::$app->storage;
        $thumbDir = $storage->crashReportThumbDir((int) $this->project_id, (int) $this->id);
        $thumb    = $thumbDir . DIRECTORY_SEPARATOR . basename($name);

        if (!is_file($thumb)) {
            // Same on-disk cache as {@see dumpFileItemContent} so thumbnails work when
            // the ZIP entry name differs only by case or the file was already extracted.
            $cached = $storage->crashReportExtractDir((int) $this->project_id, (int) $this->id)
                . DIRECTORY_SEPARATOR . basename($name);
            $tmp = null;
            if (is_file($cached)) {
                $srcPath = $cached;
            } else {
                $zip = $storage->crashReportPath((int) $this->project_id, (int) $this->id);
                $tmp = $storage->extractZipEntry($zip, $name);
                if ($tmp === null) {
                    throw new \yii\web\NotFoundHttpException('Screenshot not found in archive.');
                }
                $srcPath = $tmp;
            }
            try {
                $storage->makeThumbnail($srcPath, $thumb);
            } finally {
                if ($tmp !== null) {
                    @unlink($tmp);
                }
            }
        }

        if (!is_file($thumb)) {
            throw new \yii\web\NotFoundHttpException('Could not generate thumbnail.');
        }
        $storage->streamDownload($thumb, basename($name), false);
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'srcfilename' => 'Srcfilename',
            'filesize' => 'Filesize',
            'date_created' => 'Date Created',
            'received' => 'Received',
            'status' => 'Status',
            'ipaddress' => 'Ipaddress',
            'md5' => 'Md5',
            'groupid' => 'Groupid',
            'crashguid' => 'Crashguid',
            'project_id' => 'Project ID',
            'appversion_id' => 'Appversion ID',
            'emailfrom' => 'Emailfrom',
            'description' => 'Description',
            'crashrptver' => 'Crashrptver',
            'exception_type' => 'Exception Type',
            'exception_code' => 'Exception Code',
            'exception_thread_id' => 'Exception Thread ID',
            'exceptionmodule' => 'Exceptionmodule',
            'exceptionmodulebase' => 'Exceptionmodulebase',
            'exceptionaddress' => 'Exceptionaddress',
            'exe_image' => 'Exe Image',
            'os_name_reg' => 'Os Name Reg',
            'os_ver_mdmp' => 'Os Ver Mdmp',
            'os_is_64bit' => 'Os Is 64bit',
            'geo_location' => 'Geo Location',
            'product_type' => 'Product Type',
            'cpu_architecture' => 'Cpu Architecture',
            'cpu_count' => 'Cpu Count',
            'gui_resource_count' => 'Gui Resource Count',
            'open_handle_count' => 'Open Handle Count',
            'memory_usage_kbytes' => 'Memory Usage Kbytes',
        ];
    }

    public function getProject()
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    public function getAppversion()
    {
        return $this->hasOne(Appversion::class, ['id' => 'appversion_id']);
    }

    public function getCrashGroup()
    {
        return $this->hasOne(Crashgroup::class, ['id' => 'groupid']);
    }

    public function getThreads()
    {
        return $this->hasMany(Thread::class, ['crashreport_id' => 'id']);
    }

    public function getModules()
    {
        return $this->hasMany(Module::class, ['crashreport_id' => 'id']);
    }

    public function getFileItems()
    {
        return $this->hasMany(Fileitem::class, ['crashreport_id' => 'id']);
    }

    public function getCustomProps()
    {
        return $this->hasMany(Customprop::class, ['crashreport_id' => 'id']);
    }

    public function getBugs()
    {
        return $this->hasMany(BugCrashreport::class, ['crashreport_id' => 'id']);
    }

    public function getProcessingErrors()
    {
        // processingerror.srcid points to crashreport.id when type==1 (CrashReport).
        return $this->hasMany(Processingerror::class, ['srcid' => 'id'])
            ->andOnCondition(['type' => 1]);
    }

    public function getExceptionThread()
    {
        if ($this->exception_thread_id === null) {
            return null;
        }
        return Thread::findOne([
            'crashreport_id' => $this->id,
            'thread_id'      => (int) $this->exception_thread_id,
        ]);
    }

    public function getIp_address()
    {
        return $this->ipaddress;
    }

    /**
     * Returns the OS bittness as a printable string ("32-bit" / "64-bit").
     */
    public function getOsBittnessStr(): string
    {
        if ($this->os_is_64bit === null) {
            return '';
        }
        return ((int) $this->os_is_64bit === 1) ? '64-bit' : '32-bit';
    }

    /**
     * Tab-specific data providers for the View page.
     *
     * Each method returns an ActiveDataProvider so the view can render a
     * GridView, and so the tab labels can show counts via totalCount.
     */
    public function searchThreads(): \yii\data\ActiveDataProvider
    {
        return new \yii\data\ActiveDataProvider([
            'query' => Thread::find()->where(['crashreport_id' => $this->id]),
            'pagination' => ['pageSize' => 50],
        ]);
    }

    public function searchModules(): \yii\data\ActiveDataProvider
    {
        return new \yii\data\ActiveDataProvider([
            'query' => Module::find()->where(['crashreport_id' => $this->id])->orderBy('name'),
            'pagination' => ['pageSize' => 100],
        ]);
    }

    public function searchFileItems(): \yii\data\ActiveDataProvider
    {
        return new \yii\data\ActiveDataProvider([
            'query' => Fileitem::find()->where(['crashreport_id' => $this->id]),
            'pagination' => ['pageSize' => 100],
        ]);
    }

    public function searchCustomProps(): \yii\data\ActiveDataProvider
    {
        return new \yii\data\ActiveDataProvider([
            'query' => Customprop::find()->where(['crashreport_id' => $this->id])->orderBy('name'),
            'pagination' => ['pageSize' => 100],
        ]);
    }

    public function searchScreenshots(): \yii\data\ActiveDataProvider
    {
        return new \yii\data\ActiveDataProvider([
            'query' => Fileitem::find()
                ->where(['crashreport_id' => $this->id])
                ->andWhere(['or',
                    ['like', 'filename', '.png'],
                    ['like', 'filename', '.jpg'],
                ])
                ->andWhere(['like', 'filename', 'screenshot']),
            'pagination' => false,
        ]);
    }

    public function searchVideos(): \yii\data\ActiveDataProvider
    {
        return new \yii\data\ActiveDataProvider([
            'query' => Fileitem::find()
                ->where(['crashreport_id' => $this->id])
                ->andWhere(['like', 'filename', 'video.ogg']),
            'pagination' => false,
        ]);
    }

    /**
     * Returns true if this report is in a state where it can be re-processed.
     * Mirrors the legacy CrashReport::canResetStatus().
     */
    public function canResetStatus(): bool
    {
        // Only reports that finished processing (Ready/Invalid) can be re-queued.
        // Status codes are seeded in lookup table:
        //   1 Waiting, 2 Processing, 3 Ready, 4 Invalid
        return in_array((int) $this->status, [3, 4], true);
    }

    public function resetStatus(): void
    {
        $this->status = 1; // Waiting
        $this->save(false, ['status']);
    }

    /**
     * True when the model can be linked to a new bug. Conservative default
     * mirrors legacy: a non-grouped report cannot open a bug, but a grouped
     * report always can.
     */
    public function canOpenNewBug(): bool
    {
        return !empty($this->groupid);
    }

    /**
     * Decodes the `crashrptver` integer (e.g. 1402) into "1.4.2".
     */
    public static function generatorVersionToStr($v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        $v = (string) $v;
        // Pad to 4 digits so 102 reads as 0.1.02 -> "0.1.2"
        $padded = str_pad($v, 4, '0', STR_PAD_LEFT);
        $major = (int) substr($padded, 0, -3);
        $minor = (int) substr($padded, -3, 1);
        $build = (int) substr($padded, -2);
        return sprintf('%d.%d.%d', $major, $minor, $build);
    }

    /**
     * Maps a 2-letter ISO country code (geo_location) to a printable name.
     * Returns the raw code if unknown so the UI degrades gracefully.
     */
    public static function geoIdToCountryName($code): string
    {
        if ($code === null || $code === '') {
            return '';
        }
        // Minimal table; extend as needed. Falls through to raw code.
        static $map = [
            'US' => 'United States', 'GB' => 'United Kingdom', 'DE' => 'Germany',
            'FR' => 'France', 'JP' => 'Japan', 'CN' => 'China', 'IN' => 'India',
            'BR' => 'Brazil', 'CA' => 'Canada', 'AU' => 'Australia', 'RU' => 'Russia',
            'IT' => 'Italy', 'ES' => 'Spain', 'NL' => 'Netherlands', 'SE' => 'Sweden',
            'PL' => 'Poland', 'KR' => 'South Korea', 'MX' => 'Mexico', 'TR' => 'Turkey',
            'ID' => 'Indonesia', 'AR' => 'Argentina', 'ZA' => 'South Africa',
        ];
        $u = strtoupper($code);
        return $map[$u] ?? $u;
    }
}
