<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * Search model for the Serials Info admin grid. Filters on
 * box_serial / card_serial (LIKE) and report_count (=).
 */
class SerialsinfoSearch extends Serialsinfo
{
    public function rules()
    {
        return [
            [['box_serial', 'card_serial'], 'safe'],
            [['report_count'], 'integer'],
        ];
    }

    public function scenarios()
    {
        // Bypass Serialsinfo::scenarios() so the parent's safe-attrs
        // list doesn't shadow ours.
        return Model::scenarios();
    }

    /**
     * @param array<string,mixed> $params
     */
    public function search(array $params): ActiveDataProvider
    {
        $query = Serialsinfo::find();

        $dataProvider = new ActiveDataProvider([
            'query'      => $query,
            'pagination' => ['pageSize' => 100],
            'sort'       => [
                'defaultOrder' => ['report_count' => SORT_DESC],
                'attributes'   => ['box_serial', 'card_serial', 'report_count'],
            ],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            $query->andWhere('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere(['report_count' => $this->report_count]);
        $query->andFilterWhere(['like', 'box_serial',  $this->box_serial])
              ->andFilterWhere(['like', 'card_serial', $this->card_serial]);

        return $dataProvider;
    }
}
