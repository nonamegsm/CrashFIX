<?php

/**
 * This is the model class for table "{{extra_files}}".
 *
 * The followings are the available columns in table '{{extra_files}}':
 * @property integer $id
 * @property integer $project_id
 * @property string $name
 * @property integer $date_from
 * @property integer $date_to
 * @property integer $status
 * @property string $path
 */
class ExtraFiles extends CActiveRecord
{
	/**
	 * @return string the associated database table name
	 */
	public function tableName()
	{
		return '{{extra_files}}';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('project_id, name, status', 'required'),
			array('project_id, date_from, date_to, status', 'numerical', 'integerOnly'=>true),
			array('name, path', 'length', 'max'=>128),
			// The following rule is used by search().
			// @todo Please remove those attributes that should not be searched.
			array('id, project_id, name, date_from, date_to, status, path', 'safe', 'on'=>'search'),
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
			'id' => 'ID',
			'project_id' => 'Project',
			'name' => 'Name',
			'date_from' => 'Date From',
			'date_to' => 'Date To',
			'status' => 'Status',
			'path' => 'Path',
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 *
	 * Typical usecase:
	 * - Initialize the model fields with values from filter form.
	 * - Execute this method to get CActiveDataProvider instance which will filter
	 * models according to data in model fields.
	 * - Pass data provider to CGridView, CListView or any similar widget.
	 *
	 * @return CActiveDataProvider the data provider that can return the models
	 * based on the search/filter conditions.
	 */
	public function search()
	{
		// @todo Please modify the following code to remove attributes that should not be searched.

		$criteria=new CDbCriteria;
		
		// Get current project ID and version
		$curProjectId = Yii::app()->user->getCurProjectId();
		$criteria->compare('project_id', $curProjectId, false, 'AND');

		$criteria->compare('id',$this->id);
		$criteria->compare('project_id',$this->project_id);
		$criteria->compare('name',$this->name,true);
		$criteria->compare('date_from',$this->date_from);
		$criteria->compare('date_to',$this->date_to);
		$criteria->compare('status',$this->status);
		$criteria->compare('path',$this->path,true);

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
			
			'pagination'=>array(
				'pageSize'=>40,
			),
		));
	}

	
	public function creteriaExtraFilesItems()
	{
		$criteria=new CDbCriteria;
//		$criteria->compare('crashreport_id', $this->id);	
		$criteria->join='INNER JOIN {{crashreport}} a ON t.crashreport_id=a.id and a.project_id='.$this->project_id.' and a.received >= '.$this->date_from.' and a.received <= '.$this->date_to;
		$criteria->condition='filename <> "crashdump.dmp" AND filename NOT LIKE "screenshot%" AND filename <> "crashrpt.xml" AND filename NOT LIKE "%.txt" AND filename NOT LIKE "%.log";';
		                
        return $criteria;
	}
	
	public function searchExtraFilesItems()
	{
		$criteria=$this->creteriaExtraFilesItems();
		
		$dataProvider = new CActiveDataProvider('FileItem', array(
			'criteria'=>$criteria,
            'sort'=>array(
                'defaultOrder'=>'filename ASC'
            ),
			'pagination'=>array(
				'pageSize'=>50,
			),
		));
                
        return $dataProvider;
	}
	
	/**
	 *  This method dumps the content of attachment file to stdout.
	 *  This method is used when downloading the debug info file.
	 */
	public function dumpFileAttachmentContent()
	{
		// Try to open file
		if ($fd = fopen ($this->path, "r")) 
		{			
			$fsize = filesize($this->path);			
			
			// Write HTTP headers
			header("Content-type: application/octet-stream");
			header("Content-Disposition: filename=\"".$this->name."_".$this->id.".zip\"");
    		header("Content-length: $fsize");
			header("Cache-control: private"); //use this to open files directly
			
			// Write file content
			while(!feof($fd)) 
			{
				$buffer = fread($fd, 2048);
				echo $buffer;
			}			
		}	
		
		fclose($fd);
	}
	
	/**
	 * Returns the static model of the specified AR class.
	 * Please note that you should have this exact method in all your CActiveRecord descendants!
	 * @param string $className active record class name.
	 * @return ExtraFiles the static model class
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}
}
