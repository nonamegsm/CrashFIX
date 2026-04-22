<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * Search model for the Bugs index page.
 *
 * Encapsulates the project-scoping, status-quick-filter (open/closed/owned/
 * reported/verify/all) and free-text search across summary/reporter/owner
 * that previously lived inline in BugController::actionIndex.
 */
class BugSearch extends Bug
{
    public function rules()
    {
        return [
            [['id', 'project_id', 'appversion_id', 'status', 'priority',
              'reproducability', 'reported_by', 'assigned_to'], 'integer'],
            [['summary', 'description'], 'safe'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    /**
     * @param array<string,mixed> $params
     * @param int                 $projectId      filter to this project (required)
     * @param int                 $appversionId   -1 means "all versions"
     * @param string|null         $q              free text search
     * @param string|null         $status         all|open|closed|owned|reported|verify
     */
    public function search(
        array $params,
        int $projectId,
        int $appversionId = -1,
        ?string $q = null,
        ?string $status = 'open'
    ): ActiveDataProvider {
        $bug  = Bug::tableName();
        $user = User::tableName();

        $query = Bug::find()
            ->alias('b')
            ->where(['b.project_id' => $projectId]);

        if ($appversionId !== -1) {
            $query->andWhere(['b.appversion_id' => $appversionId]);
        }

        // Free text search across summary + reporter + owner usernames.
        if ($q !== null && $q !== '') {
            $query->leftJoin("{$user} reporter", 'reporter.id = b.reported_by')
                  ->leftJoin("{$user} owner",    'owner.id    = b.assigned_to')
                  ->andWhere(['or',
                      ['like', 'b.summary',         $q],
                      ['like', 'reporter.username', $q],
                      ['like', 'owner.username',    $q],
                  ]);
        }

        // Status quick filter.
        switch ($status) {
            case 'all':
                break;
            case 'closed':
                $query->andWhere(['>', 'b.status', Bug::STATUS_OPEN_MAX]);
                break;
            case 'reported':
                $query->andWhere(['<', 'b.status', Bug::STATUS_OPEN_MAX])
                      ->andWhere(['b.reported_by' => Yii::$app->user->id]);
                break;
            case 'owned':
                $query->andWhere(['<', 'b.status', Bug::STATUS_OPEN_MAX])
                      ->andWhere(['b.assigned_to' => Yii::$app->user->id]);
                break;
            case 'verify':
                $query->andWhere(['b.status' => Bug::STATUS_FIXED]);
                break;
            case 'open':
            default:
                $query->andWhere(['<', 'b.status', Bug::STATUS_OPEN_MAX]);
                break;
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort'  => [
                'defaultOrder' => ['id' => SORT_DESC],
                'attributes'   => [
                    'id'                 => ['asc' => ['b.id' => SORT_ASC], 'desc' => ['b.id' => SORT_DESC]],
                    'date_created'       => ['asc' => ['b.date_created' => SORT_ASC], 'desc' => ['b.date_created' => SORT_DESC]],
                    'date_last_modified' => ['asc' => ['b.date_last_modified' => SORT_ASC], 'desc' => ['b.date_last_modified' => SORT_DESC]],
                    'status'             => ['asc' => ['b.status' => SORT_ASC], 'desc' => ['b.status' => SORT_DESC]],
                    'priority'           => ['asc' => ['b.priority' => SORT_ASC], 'desc' => ['b.priority' => SORT_DESC]],
                    'summary'            => ['asc' => ['b.summary' => SORT_ASC], 'desc' => ['b.summary' => SORT_DESC]],
                ],
            ],
            'pagination' => ['pageSize' => 50],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            $query->andWhere('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'b.id'              => $this->id,
            'b.status'          => $this->status,
            'b.priority'        => $this->priority,
            'b.reproducability' => $this->reproducability,
            'b.reported_by'     => $this->reported_by,
            'b.assigned_to'     => $this->assigned_to,
        ]);

        $query->andFilterWhere(['like', 'b.summary',     $this->summary])
              ->andFilterWhere(['like', 'b.description', $this->description]);

        return $dataProvider;
    }
}
