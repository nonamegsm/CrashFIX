<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * Search model for the Debug Info index page. Always project-scoped.
 */
class DebuginfoSearch extends Debuginfo
{
    public function rules()
    {
        return [
            [['id', 'project_id', 'status', 'filesize'], 'integer'],
            [['filename', 'guid', 'md5', 'format'], 'safe'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    /**
     * @param array<string,mixed> $params
     */
    public function search(array $params, int $projectId): ActiveDataProvider
    {
        $query = Debuginfo::find()->where(['project_id' => $projectId]);

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort'  => [
                'defaultOrder' => ['dateuploaded' => SORT_DESC],
                'attributes'   => ['id', 'dateuploaded', 'filename', 'filesize', 'status'],
            ],
            'pagination' => ['pageSize' => 50],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            $query->andWhere('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id'       => $this->id,
            'status'   => $this->status,
            'filesize' => $this->filesize,
            'format'   => $this->format,
        ]);

        $query->andFilterWhere(['like', 'filename', $this->filename])
              ->andFilterWhere(['like', 'guid',     $this->guid])
              ->andFilterWhere(['like', 'md5',      $this->md5]);

        return $dataProvider;
    }
}
