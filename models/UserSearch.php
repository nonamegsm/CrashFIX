<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * Search model for the Users admin grid.
 */
class UserSearch extends User
{
    public function rules()
    {
        return [
            [['id', 'usergroup', 'status', 'flags'], 'integer'],
            [['username', 'email'], 'safe'],
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
        $query = User::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort'  => [
                'defaultOrder' => ['username' => SORT_ASC],
                'attributes'   => ['id', 'username', 'email', 'status', 'usergroup'],
            ],
            'pagination' => ['pageSize' => 30],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            $query->andWhere('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id'        => $this->id,
            'usergroup' => $this->usergroup,
            'status'    => $this->status,
        ]);

        $query->andFilterWhere(['like', 'username', $this->username])
              ->andFilterWhere(['like', 'email',    $this->email]);

        return $dataProvider;
    }
}
