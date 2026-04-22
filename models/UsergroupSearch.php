<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * Search model for the User Groups admin grid.
 */
class UsergroupSearch extends Usergroup
{
    public function rules()
    {
        return [
            [['id', 'status', 'flags'], 'integer'],
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
        $query = Usergroup::find();

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
