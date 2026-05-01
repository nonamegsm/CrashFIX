<?php

/**
 * This is the model class for table "{{stackframe}}".
 *
 * The followings are the available columns in table '{{stackframe}}':
 */
#[\AllowDynamicProperties]
class StackFrame extends CActiveRecord
{
	/**
	 * Returns the static model of the specified AR class.
	 * @param string $className active record class name.
	 * @return StackFrame the static model class
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
		return '{{stackframe}}';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules()
	{
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			//array('thread_id, title', 'required'),			
			// The following rule is used by search().
			// Please remove those attributes that should not be searched.
			array('id', 'safe', 'on'=>'search'),
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
			'module'=>array(self::BELONGS_TO, 'Module', 'module_id'),		
		);
	}

	private static $_liveDwarfTitleCache = array();
	
	/**
	 * Formats the title of this stack frame.
	 */
	public function getTitle()
	{
		$title = "";
		
		// Check if this is a special frame [UnwindInfoNotAvail]
		if($this->addr_pc==0 && $this->module_id==0)
		{
			$title = '[WARNING: Stack unwind information not available. Frames below may be wrong.]';
			return $title;
		}
		
		// Check if symbol name available
		if(isset($this->symbol_name) || isset($this->und_symbol_name))
		{
			if(isset($this->module_id) && isset($this->module))
			{
				$moduleName = $this->module->name;
				$title = $title.$moduleName.'! ';			
			}
			
			if(isset($this->und_symbol_name))
				$title = $title.$this->und_symbol_name.' ';
			else if(isset($this->symbol_name))
				$title = $title.$this->symbol_name.' ';
			$title.='+0x'.dechex($this->offs_in_symbol).' ';

			if(isset($this->src_file_name) && isset($this->src_line))
			{			
				$title .= '['.basename($this->src_file_name).': '.$this->src_line.']';
			}
            else if(isset($this->src_line) && $this->src_line>=0)
			{			
				$title .= '[line '.$this->src_line.']';
			}
		}
		else // Symbol name not available
		{		
			if(isset($this->module_id) && isset($this->module))
			{
                $moduleName = $this->module->name;
				$liveTitle = $this->getLiveDwarfTitle($moduleName);
				if($liveTitle !== null)
					$title = $liveTitle;
				else
					$title = $title.$moduleName.'!+0x'.dechex($this->offs_in_module);			                                				
			}
			else
			{
				$title = '0x'.dechex($this->addr_pc);			
			}
		}		
		
		return $title;
	}

	private function getLiveDwarfTitle($moduleName)
	{
		if(!isset($this->offs_in_module) || $this->offs_in_module === null)
			return null;

		$cacheKey = (string)$this->module_id.':'.(string)$this->offs_in_module;
		if(array_key_exists($cacheKey, self::$_liveDwarfTitleCache))
			return self::$_liveDwarfTitleCache[$cacheKey];

		$result = null;
		$debugInfo = $this->findDwarfDebugInfo($moduleName);
		if($debugInfo !== null)
		{
			$error = '';
			$rows = $debugInfo->testSymbolAddressResolution('0x'.dechex($this->offs_in_module), $error);
			foreach($rows as $row)
			{
				foreach($row['candidates'] as $candidate)
				{
					if(!empty($candidate['resolved']) && $candidate['label'] === 'image base + input')
					{
						$result = $this->formatLiveDwarfTitle($moduleName, $candidate);
						break 2;
					}
				}
			}
		}

		self::$_liveDwarfTitleCache[$cacheKey] = $result;
		return $result;
	}

	private function findDwarfDebugInfo($moduleName)
	{
		if(isset($this->module->debuginfo) && $this->module->debuginfo !== null)
			return $this->module->debuginfo;

		if(!isset($this->module->timestamp) || $this->module->timestamp === null || $moduleName === '')
			return null;

		$timestampHex = sprintf('%08x', (int)$this->module->timestamp);
		return DebugInfo::model()->find(
			'filename=:filename AND guid LIKE :guid AND status=:status',
			array(
				':filename'=>$moduleName,
				':guid'=>'pe-'.$timestampHex.'-%',
				':status'=>DebugInfo::STATUS_PROCESSED,
			)
		);
	}

	private function formatLiveDwarfTitle($moduleName, $candidate)
	{
		$title = $moduleName.'! '.$candidate['symbol'].' ';
		if(!empty($candidate['fileLine']))
			$title .= '['.$candidate['fileLine'].'] ';
		$title .= '(live DWARF)';
		return $title;
	}
}