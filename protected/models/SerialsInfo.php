<?php



/**
 * This is the model class for the view "view_serials_report_count".
 */
class SerialsInfo extends CActiveRecord
{

    public $isAdvancedSearch = false; // Is advanced search enabled?
    /**
     * Returns the static model of the specified AR class.
     * @return SerialsInfo the static model class
     */
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return 'view_serials_report_count';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return array(
            array('box_serial, card_serial', 'length', 'max'=>128),
            array('report_count', 'numerical', 'integerOnly'=>true),
        );
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        // NOTE: you may need to adjust the relation name and the related
        // class name for the relations automatically generated below.
        return array(
        );
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return array(
            'box_serial' => 'Box Serial',
            'card_serial' => 'Card Serial',
            'report_count' => 'Report Count',
        );
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {
        $criteria = new CDbCriteria;

        $criteria->compare('box_serial', $this->box_serial, true);
        $criteria->compare('card_serial', $this->card_serial, true);
        $criteria->compare('report_count', $this->report_count);

        return new CActiveDataProvider($this, array(
            'criteria' => $criteria,
            'pagination' => array(
                'pageSize' => 100, // Set the number of items per page to 100
            ),
        ));
    }

}
