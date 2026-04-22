<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * Search model for the Projects admin grid.
 */
class ProjectSearch extends Project
{
    public function rules()
    {
        return [
            [['id', 'status'], 'integer'],
            [['name', 'description'], 'safe'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    /**
     * @param array<string,mixed> $params
     */
    public function search(array $params): ActiveDataProvider
    {
        $query = Project::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort'  => [
                'defaultOrder' => ['name' => SORT_ASC],
                'attributes'   => ['id', 'name', 'status'],
            ],
            'pagination' => ['pageSize' => 30],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            $query->andWhere('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere(['id' => $this->id, 'status' => $this->status])
              ->andFilterWhere(['like', 'name',        $this->name])
              ->andFilterWhere(['like', 'description', $this->description]);

        return $dataProvider;
    }
}
