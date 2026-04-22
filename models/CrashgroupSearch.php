<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\Expression;

/**
 * Search model for the Crash Groups index.
 *
 * In addition to the configured filters this also computes a per-group
 * "crash report count" in the underlying query so the GridView can sort
 * on it.
 */
class CrashgroupSearch extends Crashgroup
{
    public $crashReportCount;

    public function rules()
    {
        return [
            [['id', 'status', 'project_id', 'appversion_id', 'crashReportCount'], 'integer'],
            [['title', 'md5'], 'safe'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    /**
     * @param array<string,mixed> $params
     */
    public function search(array $params, int $projectId, int $appversionId = -1, ?string $q = null, ?string $status = null): ActiveDataProvider
    {
        $crashReportTable = Crashreport::tableName();
        $groupTable = Crashgroup::tableName();

        $query = Crashgroup::find()
            ->select([
                "{$groupTable}.*",
                'crashReportCount' => new Expression("COUNT({$crashReportTable}.id)"),
            ])
            ->leftJoin($crashReportTable, "{$crashReportTable}.groupid = {$groupTable}.id")
            ->where(["{$groupTable}.project_id" => $projectId])
            ->groupBy("{$groupTable}.id");

        if ($appversionId !== -1) {
            $query->andWhere(["{$groupTable}.appversion_id" => $appversionId]);
        }

        // Quick text search across title.
        if ($q !== null && $q !== '') {
            $query->andWhere(['like', "{$groupTable}.title", $q]);
        }

        // Status quick filter: open | closed | all (default open).
        if ($status === null || $status === 'open') {
            $query->andWhere(['<', "{$groupTable}.status", 100]);
        } elseif ($status === 'closed') {
            $query->andWhere(['>=', "{$groupTable}.status", 100]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort'  => [
                'defaultOrder' => ['created' => SORT_DESC],
                'attributes'   => [
                    'id', 'title', 'created', 'status',
                    'crashReportCount' => [
                        'asc'  => ['crashReportCount' => SORT_ASC],
                        'desc' => ['crashReportCount' => SORT_DESC],
                    ],
                ],
            ],
            'pagination' => ['pageSize' => 30],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            $query->andWhere('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere(['id' => $this->id])
              ->andFilterWhere(['like', "{$groupTable}.title", $this->title])
              ->andFilterWhere(['like', "{$groupTable}.md5",   $this->md5]);

        return $dataProvider;
    }
}
