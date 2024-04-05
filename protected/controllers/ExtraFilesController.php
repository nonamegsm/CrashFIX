<?php

Yii::import('application.vendors.ezcomponents.*');
require_once "Base/src/base.php"; 
Yii::registerAutoloader(array('ezcBase', 'autoload'), true);

class ExtraFilesController extends Controller
{
	/**
	 * @var string the default layout for the views. Defaults to '//layouts/column2', meaning
	 * using two-column layout. See 'protected/views/layouts/column2.php'.
	 */
	public $layout='//layouts/column2';
	
	/**
	 * @var string the active item for sidebar menu.
	 */
	public $sidebarActiveItem = 'ExtraFiles';
	

	/**
	 * @return array action filters
	 */
	public function filters()
	{
		return array(
			'accessControl', // perform access control for CRUD operations
		);
	}

	/**
	 * Specifies the access control rules.
	 * This method is used by the 'accessControl' filter.
	 * @return array access control rules
	 */
	public function accessRules()
	{
		return array(
			array('allow',  // allow all users to perform 'index' and 'view' actions
				'actions'=>array('index','view'),
				'users'=>array('*'),
			),
			array('allow', // allow admin user to perform 'admin' and 'delete' actions
				'actions'=>array('delete','create','process','download'),
				'users'=>array('@'),
			),
			array('deny',  // deny all users
				'users'=>array('*'),
			),
		);
	}

	/**
	 * Displays a particular model.
	 * @param integer $id the ID of the model to be displayed
	 */
	public function actionView($id)
	{
		$this->render('view',array(
			'model'=>$this->loadModel($id),
		));
	}

	/**
	 * Deletes a particular model.
	 * If deletion is successful, the browser will be redirected to the 'admin' page.
	 * @param integer $id the ID of the model to be deleted
	 */
	public function actionDelete($id)
	{
		$model = $this->loadModel($id);
		if (strlen($model->path))
			@unlink($model->path);	
		$model->delete();

		// if AJAX request (triggered by deletion via admin grid view), we should not redirect the browser
		if(!isset($_GET['ajax']))
			$this->redirect(array('extraFiles/index'));
	}

	/**
	 * Lists all models.
	 */
	public function actionIndex()
	{
		// Check if user is authorized to perform the action
		$this->checkAuthorization(null);
		
		$model = new ExtraFiles('search');
				
		if(isset($_GET['q']))
			$model->filter = $_GET['q'];
		else if(isset($_POST['CrashReport']))
		{			
			// Fill form with data
			$model->attributes = $_POST['CrashReport'];			
		}
		
		$dataProvider=$model->search();
				
		$this->render('index',array(
			'dataProvider'=>$dataProvider,
			'model'=>$model
		));
	}
		
	public function actionProcess($id)
	{
		$extraFiles = ExtraFiles::model()->findByPk($id);
		
		if ($extraFiles->status == CrashReport::STATUS_PROCESSING_IN_PROGRESS)
		{
			echo 'Already in progress</br>';	
			exit;		
		}
		
		$extraFiles->status = CrashReport::STATUS_PROCESSING_IN_PROGRESS;	
		$extraFiles->save();		
		
		$criteria = $extraFiles->creteriaExtraFilesItems();
		$files = FileItem::model()->FindAll($criteria);
		$errors = false;
		
		if (!count($files))
		{
			$extraFiles->status = CrashReport::STATUS_PROCESSED;
			$extraFiles->save();
			$this->redirect(array('extraFiles/view/'.$extraFiles->id));			
		}
		
		$outDir = Yii::app()->getBasePath()."/data/extraFiles/".$extraFiles->name."_".$extraFiles->id;
		if (!file_exists($outDir))
			if (!mkdir($outDir, 0777, true))
			{
				echo 'Can\'t create temp directory</br>';
				$extraFiles->status = CrashReport::STATUS_INVALID;	
				$extraFiles->save();
				exit;
			}
						
		// Initialize archive object
		$zip = new ZipArchive();
		if (!$zip->open($outDir.'.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE))
		{
			echo 'Can\'t create zip</br>';
			$extraFiles->status = CrashReport::STATUS_INVALID;	
			$extraFiles->save();
			exit;
		}
			
		
		foreach($files as $file)
		{
			$crashReport = CrashReport::model()->findByPk($file->crashreport_id);
			// Determine path to local crash report file
			$crashReportFileName = $crashReport->getLocalFilePath();
			
			// Create temp file for output
			$fileName = $file->crashreport_id."___".$file->filename;
			$outFile = $outDir."/".$fileName;				
				
			// Format daemon command 
			$command = 'dumper --extract-file "'.$crashReportFileName.'" "'.$file->filename.'" "'.$outFile.'"';
			
			// Execute the command
			$responce = "";
			$retCode = Yii::app()->daemon->execCommand($command, $responce);
					
			if($retCode!=0)
			{
				//$errors = true;				
				//Yii::log('Error executing command '.$command.', responce = '.$responce, 'error');
				echo 'Error executing command '.$command.', responce = '.$responce, 'error </br>';				
				
			}			
			else
			{
				// Add current file to archive
				$zip->addFile($outFile, $fileName);				
			}
		}

		// Zip archive will be created only after closing object
		if (!$zip->close())		
		{
			echo 'Can\'t close zip</br>';
			$errors = true;
		}
		
		$extraFiles->path = $outDir.'.zip';
		
		// Remove temp files
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($outDir),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ($files as $name => $file)
		{
			@unlink($file->getRealPath());	
		}
		
		// Remove temp dir
		if (!rmdir($outDir))		
		{
			echo 'Can\'t remove temp directory</br>';
		}
				
		if ($errors)
		{
			$extraFiles->status = CrashReport::STATUS_INVALID;
			$extraFiles->save();
			exit;
		}
						
		$extraFiles->status = CrashReport::STATUS_PROCESSED;
		$extraFiles->save();
		$this->redirect(array('extraFiles/view/'.$extraFiles->id));
	}
	
	public function actionCreate($date_from, $date_to)
	{
		// Check if user is authorized to perform the action
		$this->checkAuthorization(null);				
		
		$date_from = trim($date_from);
		$date_to = trim($date_to);
		
		$dtime = 0;
		if (strlen($date_from) == 10)
			$dtime = DateTime::createFromFormat("d/m/Y", $date_from);
		if (strlen($date_from) == 8)
			$dtime = DateTime::createFromFormat("d/m/y", $date_from);
		
		if ($dtime)
			$date_from = $dtime->getTimestamp();
		else
			$date_from = 0;
				
		$dtime = 0;
		if (strlen($date_to) == 10)
			$dtime = DateTime::createFromFormat("d/m/Y", $date_to);
		if (strlen($date_to) == 8)
			$dtime = DateTime::createFromFormat("d/m/y", $date_to);
		
		if ($dtime)
			$date_to = $dtime->getTimestamp();
		else
			$date_to = 0;
		
		if ($date_to==0 || $date_from > $date_to)
		{
			throw new CHttpException(500, 'Bed dates selected.');
		}
		
		$project = Project::model()->findByPk(Yii::app()->user->getCurProjectId());
		
		$extraFiles = new ExtraFiles();
		$extraFiles->project_id = $project->id;
		$extraFiles->name = $project->name."_".date("Ymd", $date_from)."_".date("Ymd", $date_to);
		$extraFiles->date_from = $date_from;
		$extraFiles->date_to = $date_to;
		$extraFiles->status = 1;
		if (!$extraFiles->save())
		{
			print_r("ERROR :");
			print_r($extraFiles->getErrors());
			exit;
		}
		
		
		$this->redirect(array('extraFiles/view/'.$extraFiles->id));
	}
		
	public function actionDownload($id)
	{
		// Load requested crash report
		$model = $this->loadModel($id);
		
		// Check if user is authorized to perform the action
		$this->checkAuthorization($model);
		
		// Dump crash report file to stdout
		$model->dumpFileAttachmentContent();		
	}

	/**
	 * Returns the data model based on the primary key given in the GET variable.
	 * If the data model is not found, an HTTP exception will be raised.
	 * @param integer $id the ID of the model to be loaded
	 * @return ExtraFiles the loaded model
	 * @throws CHttpException
	 */
	public function loadModel($id)
	{
		$model=ExtraFiles::model()->findByPk($id);
		if($model===null)
			throw new CHttpException(404,'The requested page does not exist.');
		return $model;
	}

	/**
	 * Checks if user is authorized to perform the action.
	 * @param CrashReport $model Authorization object. Can be null.
	 * @param string $permission Permission name.
	 * @throws CHttpException
	 * @return void
	 */
	protected function checkAuthorization($model, $permission = 'pperm_browse_crash_reports')
	{		
		if($model==null)
		{
			$projectId = Yii::app()->user->getCurProjectId();
			if($projectId==false)
				return;
		}
		else
			$projectId = $model->project_id;	
		
		// Check if user can perform this action
		if(!Yii::app()->user->checkAccess($permission, 
				array('project_id'=>$projectId)) )
		{
			throw new CHttpException(403, 'You are not authorized to perform this action.');
		}
	}
}
