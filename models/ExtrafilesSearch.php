<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class ExtrafilesSearch extends Extrafiles
{
    public function rules(): array
    {
        return [
            [['id', 'project_id', 'date_from', 'date_to', 'status'], 'integer'],
            [['name', 'path'], 'safe'],
        ];
    }

    public function scenarios(): array
    {
        return Model::scenarios();
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = Extrafiles::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 40],
            'sort' => ['defaultOrder' => ['id' => SORT_DESC]],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            $query->where('0=1');
            return $dataProvider;
        }

        $projectId = Yii::$app->user->getCurProjectId();
        if ($projectId === false || $projectId === null) {
            $query->where('0=1');
        } else {
            $query->andWhere(['project_id' => $projectId]);
        }

        $query->andFilterWhere(['id' => $this->id]);
        $query->andFilterWhere(['like', 'name', $this->name]);
        $query->andFilterWhere(['date_from' => $this->date_from]);
        $query->andFilterWhere(['date_to' => $this->date_to]);
        $query->andFilterWhere(['status' => $this->status]);
        $query->andFilterWhere(['like', 'path', $this->path]);

        return $dataProvider;
    }
}
