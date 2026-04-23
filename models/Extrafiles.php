<?php

namespace app\models;

use Yii;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;

/**
 * "Extra files" collection — aggregates attachments from crash reports
 * between two received timestamps (legacy {{extra_files}}).
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property int|null $date_from
 * @property int|null $date_to
 * @property int $status
 * @property string|null $path
 */
class Extrafiles extends \yii\db\ActiveRecord
{
    public const STATUS_WAITING    = 1;
    public const STATUS_PROCESSING = 2;
    public const STATUS_PROCESSED  = 3;
    public const STATUS_INVALID    = 4;

    public static function tableName(): string
    {
        return 'tbl_extra_files';
    }

    public function rules(): array
    {
        return [
            [['project_id', 'name', 'status'], 'required'],
            [['project_id', 'date_from', 'date_to', 'status'], 'integer'],
            [['name'], 'string', 'max' => 128],
            [['path'], 'string', 'max' => 1024],
            [['id', 'project_id', 'name', 'date_from', 'date_to', 'status', 'path'], 'safe', 'on' => 'search'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'project_id' => 'Project',
            'name' => 'Name',
            'date_from' => 'Date From',
            'date_to' => 'Date To',
            'status' => 'Status',
            'path' => 'Path',
        ];
    }

    public function getProject(): ActiveQuery
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    /**
     * Attachments in range excluding dumps, screenshots, xml, txt, log.
     */
    public function fileItemsQuery(): ActiveQuery
    {
        return Fileitem::find()->alias('t')
            ->innerJoin(
                ['a' => Crashreport::tableName()],
                '[[t.crashreport_id]] = [[a.id]] AND [[a.project_id]] = :pid AND [[a.received]] >= :dfrom AND [[a.received]] <= :dto',
                [
                    ':pid' => $this->project_id,
                    ':dfrom' => $this->date_from,
                    ':dto' => $this->date_to,
                ]
            )
            ->andWhere(['and',
                ['<>', 't.filename', 'crashdump.dmp'],
                ['not like', 't.filename', 'screenshot%', false],
                ['<>', 't.filename', 'crashrpt.xml'],
                ['not like', 't.filename', '%.txt', false],
                ['not like', 't.filename', '%.log', false],
            ]);
    }

    public function fileItemsDataProvider(): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => $this->fileItemsQuery(),
            'sort' => ['defaultOrder' => ['filename' => SORT_ASC]],
            'pagination' => ['pageSize' => 50],
        ]);
    }
}
