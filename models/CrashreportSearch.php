<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * Search model for the Crash Reports index page.
 *
 * Always scopes results to the user's current project (and optionally
 * current app version) so the GridView never leaks reports across
 * project boundaries.
 */
class CrashreportSearch extends Crashreport
{
    public function rules()
    {
        return [
            [['id', 'status', 'groupid', 'project_id', 'appversion_id', 'filesize'], 'integer'],
            [['srcfilename', 'crashguid', 'ipaddress', 'md5', 'emailfrom', 'description',
              'exception_type', 'exceptionmodule', 'exe_image', 'os_name_reg', 'os_ver_mdmp',
              'product_type', 'cpu_architecture', 'geo_location'], 'safe'],
        ];
    }

    /**
     * Search models live outside the standard validation pipeline.
     */
    public function scenarios()
    {
        return Model::scenarios();
    }

    /**
     * @param array<string,mixed> $params
     * @param int                 $projectId   filter to this project (required)
     * @param int                 $appversionId  -1 means "all versions"
     */
    public function search(array $params, int $projectId, int $appversionId = -1): ActiveDataProvider
    {
        $query = Crashreport::find()->where(['project_id' => $projectId]);
        if ($appversionId !== -1) {
            $query->andWhere(['appversion_id' => $appversionId]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort'  => [
                'defaultOrder' => ['received' => SORT_DESC],
                'attributes'   => ['id', 'date_created', 'received', 'filesize', 'status',
                                   'exception_type', 'ipaddress'],
            ],
            'pagination' => ['pageSize' => 50],
        ]);

        $this->load($params);
        if (!$this->validate()) {
            $query->andWhere('0=1');
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id'             => $this->id,
            'status'         => $this->status,
            'groupid'        => $this->groupid,
            'filesize'       => $this->filesize,
        ]);

        $query->andFilterWhere(['like', 'crashguid',         $this->crashguid])
              ->andFilterWhere(['like', 'srcfilename',       $this->srcfilename])
              ->andFilterWhere(['like', 'ipaddress',         $this->ipaddress])
              ->andFilterWhere(['like', 'md5',               $this->md5])
              ->andFilterWhere(['like', 'emailfrom',         $this->emailfrom])
              ->andFilterWhere(['like', 'description',       $this->description])
              ->andFilterWhere(['like', 'exception_type',    $this->exception_type])
              ->andFilterWhere(['like', 'exceptionmodule',   $this->exceptionmodule])
              ->andFilterWhere(['like', 'exe_image',         $this->exe_image])
              ->andFilterWhere(['like', 'os_name_reg',       $this->os_name_reg])
              ->andFilterWhere(['like', 'os_ver_mdmp',       $this->os_ver_mdmp])
              ->andFilterWhere(['like', 'product_type',      $this->product_type])
              ->andFilterWhere(['like', 'cpu_architecture',  $this->cpu_architecture])
              ->andFilterWhere(['like', 'geo_location',      $this->geo_location]);

        return $dataProvider;
    }
}
