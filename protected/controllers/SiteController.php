<?php

#[\AllowDynamicProperties]
class SiteController extends Controller
{	
	public $layout='//layouts/column2';
		
	/**
	 * Declares class-based actions.
	 */
	public function actions()
	{
		return array(
			// captcha action renders the CAPTCHA image displayed on the contact page
			'captcha'=>array(
				'class'=>'CCaptchaAction',
				'backColor'=>0xFFFFFF,
			),
			// page action renders "static" pages stored under 'protected/views/site/pages'
			// They can be accessed via: index.php?r=site/page&view=FileName
			'page'=>array(
				'class'=>'CViewAction',
			),
		);
	}
	
	/**
	 * @return array action filters
	 */
	public function filters()
	{
		return array(
			'accessControl', // perform access control 
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
			array('allow', // Allow anybody see an error page
				'actions'=>array('error'),
				'users'=>array('*'),
			),
			array('allow', // Allow not authenticated users to login and recover password
				'actions'=>array('login', 'recoverPassword', 'captcha'),
				'users'=>array('?'),
			),
			array('allow', // Allow authenticated users to index, logout, reset password
				'actions'=>array('index', 'logout', 'resetPassword', 'setCurProject', 'checkDaemon'),
				'users'=>array('@'),
			),			
			array('allow', // Allow admin to access admin panel
				'actions'=>array('admin', 'daemon', 'daemonStatus', 'daemonRuntimeStats'),
				'roles'=>array('gperm_access_admin_panel'),
			),			
			array('deny',  // deny all users
				'users'=>array('*'),
			),
		);
	}
	
	/**
	 * This is the default 'index' action that is invoked
	 * when an action is not explicitly requested by users.
	 */
	public function actionIndex()
	{
		// renders the view file 'protected/views/site/index.php'
		// using the default layout 'protected/views/layouts/main.php'
		$this->render('index');
	}

	/**
	 * This is the action to handle external exceptions.
	 */
	public function actionError()
	{
		$this->layout='//layouts/column1';
	    if($error=Yii::app()->errorHandler->error)
	    {
	    	if(Yii::app()->request->isAjaxRequest)
	    		echo $error['message'];
	    	else
	        	$this->render('error', $error);
	    }
	}

	/**
	 * Displays the login page
	 */
	public function actionLogin()
	{
		$model=new LoginForm('RegularLogin');
		
		// Check if user tries to login using one-time access link		
		if(isset($_GET['prt']))
		{			
			$oneTimeAccessToken = $_GET['prt'];
			$model->scenario = 'OneTimeLogin';
			$model->oneTimeAccessToken = $oneTimeAccessToken;
			
			// Validate user input and redirect to the user profile if valid
			if($model->validate() && $model->login())
				$this->redirect(array('site/resetPassword'));
			
			// If there was an error, throw an exception
			throw new CHttpException(403, 'Invalid request');			
			return;
		}
		
		// if it is ajax validation request
		if(isset($_POST['ajax']) && $_POST['ajax']==='login-form')
		{
			echo CActiveForm::validate($model);
			Yii::app()->end();
		}

		// collect user input data
		if(isset($_POST['LoginForm']))
		{
			$model->attributes=$_POST['LoginForm'];
			// validate user input and redirect to the previous page if valid
			if($model->validate() && $model->login())
			{
				$check = Yii::app()->user->checkAccess('gperm_access_admin_panel');
				Yii::log('check='.$check, 'info');
		
				// Check if user needs to reset password
				$user = User::model()->findByPk(Yii::app()->user->getId());
				if($user->isPasswordResetted())
				{
					// Redirect to Reset Password page
					$this->layout = '//layouts/column1';
					$this->redirect(array('site/resetPassword'));
				}
				else					
				{											
					// Change return url
					$user =	Yii::app()->user->loadModel();
					$defaultTab = $user->group->default_sidebar_tab;
					$returnUrl = array('site/index');
					if($defaultTab=='CrashReports')
						$returnUrl = array('crashReport/index');
					else if($defaultTab=='CrashGroups')
						$returnUrl = array('crashGroup/index');
					else if($defaultTab=='Bugs')
						$returnUrl = array('bug/index');
					else if($defaultTab=='DebugInfo')
						$returnUrl = array('debugInfo/index');
					if($defaultTab=='ExtraFiles')
						$returnUrl = array('extraFiles/index');
					else if($defaultTab=='Administer')
						$returnUrl = array('site/admin');						
					
					if(Yii::app()->user->returnUrl==Yii::app()->baseUrl);
						Yii::app()->user->setReturnUrl($returnUrl);					
					
					// Redirect to return URL
					$this->redirect(Yii::app()->user->returnUrl);
				}
			}
		}
		// display the login form
		$this->layout = '//layouts/column1';
		$this->render('login',array('model'=>$model));
	}
	
	/**
	 * Displays the Reset Password page.
	 */
	public function actionResetPassword()
	{
		$model=new ResetPasswordForm;

		// Collect user input data
		if(isset($_POST['ResetPasswordForm']))
		{
			$model->attributes=$_POST['ResetPasswordForm'];
			
			// Validate user input 
			if($model->validate())
			{				
				// Search {{user}} table for current user
				$user = User::model()->find('id='.Yii::app()->user->getId());
				if($user==Null)
					throw new CHttpException(403, "Invalid request.");
				
				// Update user's password and salt
				$user->password = $model->password;				
				// Store password as a hashed string with salt
				$user->protectPassword();
				// Reset the flag to avoid resetting the second time
				$user->flags &= ~User::FLAG_PASSWORD_RESETTED;
				// Update user record
				if(!$user->save())
					throw new CHttpException(403, "Couldn't update user info.");				
				
				// Redirect to user's profile page
				$this->redirect(array('user/view', 'id'=>Yii::app()->user->getId()));
			}
		}
		
		// Display the Recover Password form
		$this->layout = '//layouts/column1';
		$this->render('resetPassword', array('model'=>$model));
	}
	
	/**
	 * Displays the Recover Password page.
	 */
	public function actionRecoverPassword()
	{
		$model=new RecoverPasswordForm;

		// Collect user input data
		if(isset($_POST['RecoverPasswordForm']))
		{
			$model->attributes=$_POST['RecoverPasswordForm'];
			
			// Validate user input 
			if($model->validate())
			{
				// Generate a temporary password reset token
				$pwdResetToken = md5(uniqid(rand(), 1));
				
				// Search {{user}} table for existing user with such a name and email
				$user = User::model()->find(
						'username=:username AND email=:email', 
						array(':username'=>$model->username, ':email'=>$model->email));
				if($user==Null)
					throw new CHttpException(403, "Invalid request.");
				
				$user->pwd_reset_token = $pwdResetToken;
				if(!$user->save())
					throw new CHttpException(403, "Couldn't update user info.");				
				
				// Send an E-mail with password reset link
				$emailFrom = "no-reply@".Yii::app()->request->serverName;
				$emailSubject = "CrashFix Account Password Recovery";
				$emailText = "This message has been sent to you, because someone requested\r\n";
				$emailText .= "to recover lost password of your CrashFix account.\r\n\r\n";
				$emailText .= "IMPORTANT: If you did not request to recover your lost password,\r\n";
				$emailText .= "tell your administrator about this letter.\r\n\r";
				$emailText .= "If you did request to recover your lost password, then please follow\r\n";
				$emailText .= "this link to login into your CrashFix account and reenter your password:\r\n";
				$emailText .= $this->createAbsoluteUrl('site/login', array('prt'=>$pwdResetToken));
				$emailText .= "\r\n";				
				$headers="From: {$emailFrom}\r\nReply-To: {$emailFrom}";				
				//if(@mail($model->email, $emailSubject, $emailText, $headers))
				if(MailQueue::mailSend($model->email, $emailFrom, $emailSubject, $emailText))
				{
					Yii::app()->user->setFlash('recoverPassword', 'An E-mail has been sent to your E-mail address with password recovery information. Please visit your mailbox.');
				}
				else
				{
					Yii::app()->user->setFlash('recoverPassword', 'There was an error when trying to send an E-mail to user\'s mailbox. Please contact your CrashFix administrator for further assistance.');
				}
					
				$this->refresh();
			}
		}
		
		// Display the Recover Password form
		$this->layout = '//layouts/column1';
		$this->render('recoverPassword',array('model'=>$model));
	}


	/**
	 * Logs out the current user and redirect to homepage.
	 */
	public function actionLogout()
	{
		Yii::app()->user->logout();
		$this->redirect(Yii::app()->homeUrl);
	}
	
	public function actionAdmin()
	{	
		if(!Yii::app()->request->isAjaxRequest)
		{
			$this->sidebarActiveItem = 'Administer';
			$this->adminMenuItem='General';
		
			$this->render('admin');
		}
		else
		{
			// Retrieve license info
			$licenseInfo = Yii::app()->daemon->getLicenseInfo();	
				
			// Render partial view
			$this->renderPartial('_licenseInfo', 
				array('licenseInfo'=>$licenseInfo));					
			
			// Done
			Yii::app()->end();
		}
	}
	
	/**
	 *  This action is used to display daemon status and recent operations. 
	 */
	public function actionDaemon()
	{		
		$this->sidebarActiveItem = 'Administer';
		$this->adminMenuItem='Daemon';
						
		$dataProvider = new CActiveDataProvider('Operation', array(
                'pagination'=>array(
                    'pageSize'=>30
                ),
                'sort'=>array(
                    'defaultOrder'=>'timestamp DESC'
                )
            )
        );
		
        $this->render('daemon', array('dataProvider'=>$dataProvider));		
	}
	
	/**
	 *   Ajax only. Retrieves daemon status as a string.
	 */
	public function actionDaemonStatus()
	{
		if(Yii::app()->request->isAjaxRequest)
		{			
			// Check daemon status
			$daemonResponce = "";
			$daemonRetCode = Yii::app()->daemon->getDaemonStatus($daemonResponce);
			
			// Split string
			$list = preg_split('#;#', $daemonResponce);	
            
			$this->renderPartial('_daemonStatus', 
					array('daemonRetCode'=>$daemonRetCode, 'list'=>$list));
			            
            Yii::app()->end();
        }
	}

	/**
	 *   Ajax only. Returns runtime statistics about the daemon as JSON.
	 *   Built entirely from tbl_operation - does not need a daemon TCP roundtrip,
	 *   so it stays cheap to poll every few seconds.
	 */
	public function actionDaemonRuntimeStats()
	{
		if(!Yii::app()->request->isAjaxRequest)
			return;

		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store, no-cache, must-revalidate');

		$db = Yii::app()->db;
		$now = time();
		$opStarted   = Operation::STATUS_STARTED;
		$opSucceeded = Operation::STATUS_SUCCEEDED;
		$opFailed    = Operation::STATUS_FAILED;
		$opImportPdb = Operation::OPTYPE_IMPORTPDB;
		$opProcCrash = Operation::OPTYPE_PROCESS_CRASH_REPORT;
		$opDelete    = Operation::OPTYPE_DELETE_DEBUG_INFO;

		// Throughput windows
		$throughput = $db->createCommand(
			"SELECT
				SUM(CASE WHEN timestamp >= :t300  THEN 1 ELSE 0 END) AS last_5m,
				SUM(CASE WHEN timestamp >= :t900  THEN 1 ELSE 0 END) AS last_15m,
				SUM(CASE WHEN timestamp >= :t3600 THEN 1 ELSE 0 END) AS last_60m
			 FROM {{operation}}"
		)->queryRow(true, array(
			':t300'  => $now - 300,
			':t900'  => $now - 900,
			':t3600' => $now - 3600,
		));

		// Status mix in the last hour
		$statusRows = $db->createCommand(
			"SELECT status, COUNT(*) AS n
			 FROM {{operation}}
			 WHERE timestamp >= :since
			 GROUP BY status"
		)->queryAll(true, array(':since' => $now - 3600));

		$statusMix = array(
			'started'   => 0,
			'succeeded' => 0,
			'failed'    => 0,
		);
		foreach($statusRows as $r)
		{
			switch((int)$r['status'])
			{
				case $opStarted:   $statusMix['started']   = (int)$r['n']; break;
				case $opSucceeded: $statusMix['succeeded'] = (int)$r['n']; break;
				case $opFailed:    $statusMix['failed']    = (int)$r['n']; break;
			}
		}
		$totalLastHour = $statusMix['started'] + $statusMix['succeeded'] + $statusMix['failed'];
		$succRate = ($statusMix['succeeded'] + $statusMix['failed']) > 0
			? round(100.0 * $statusMix['succeeded'] / ($statusMix['succeeded'] + $statusMix['failed']), 1)
			: null;

		// Optype breakdown last hour
		$typeRows = $db->createCommand(
			"SELECT optype, status, COUNT(*) AS n
			 FROM {{operation}}
			 WHERE timestamp >= :since
			 GROUP BY optype, status"
		)->queryAll(true, array(':since' => $now - 3600));

		$typeNames = array(
			$opImportPdb => 'Import PDB',
			$opProcCrash => 'Process crash report',
			$opDelete    => 'Delete debug info',
		);
		$byType = array();
		foreach($typeNames as $tid => $tname)
			$byType[$tname] = array('ok'=>0, 'failed'=>0, 'started'=>0, 'total'=>0);
		foreach($typeRows as $r)
		{
			$tid = (int)$r['optype'];
			if(!isset($typeNames[$tid]))
				continue;
			$tname = $typeNames[$tid];
			$n = (int)$r['n'];
			$byType[$tname]['total'] += $n;
			switch((int)$r['status'])
			{
				case $opStarted:   $byType[$tname]['started']  += $n; break;
				case $opSucceeded: $byType[$tname]['ok']       += $n; break;
				case $opFailed:    $byType[$tname]['failed']   += $n; break;
			}
		}

		// What is the daemon doing RIGHT NOW (currently STARTED)
		$runningRows = $db->createCommand(
			"SELECT id, cmdid, optype, timestamp, operand1
			 FROM {{operation}}
			 WHERE status = :st
			 ORDER BY timestamp ASC
			 LIMIT 50"
		)->queryAll(true, array(':st' => $opStarted));

		$running = array();
		foreach($runningRows as $r)
		{
			$path = (string)$r['operand1'];
			// Take just the basename for display
			$file = basename($path);
			if($file === '' || $file === '.')
				$file = $path;
			$tid = (int)$r['optype'];
			$running[] = array(
				'id'        => (int)$r['id'],
				'cmdid'     => (string)$r['cmdid'],
				'optype'    => isset($typeNames[$tid]) ? $typeNames[$tid] : ('opcode '.$tid),
				'started'   => (int)$r['timestamp'],
				'elapsed_s' => max(0, $now - (int)$r['timestamp']),
				'file'      => $file,
			);
		}

		// Recent failures (last 10)
		$recentFailRows = $db->createCommand(
			"SELECT id, cmdid, optype, timestamp, operand1
			 FROM {{operation}}
			 WHERE status = :st
			 ORDER BY id DESC
			 LIMIT 10"
		)->queryAll(true, array(':st' => $opFailed));

		$recentFailures = array();
		foreach($recentFailRows as $r)
		{
			$path = (string)$r['operand1'];
			$file = basename($path);
			if($file === '' || $file === '.')
				$file = $path;
			$tid = (int)$r['optype'];
			$recentFailures[] = array(
				'id'      => (int)$r['id'],
				'optype'  => isset($typeNames[$tid]) ? $typeNames[$tid] : ('opcode '.$tid),
				'when'    => date('Y-m-d H:i:s', (int)$r['timestamp']),
				'ago_s'   => max(0, $now - (int)$r['timestamp']),
				'file'    => $file,
			);
		}

		echo CJSON::encode(array(
			'now'           => $now,
			'throughput'    => array(
				'per_5m'  => (int)$throughput['last_5m'],
				'per_15m' => (int)$throughput['last_15m'],
				'per_60m' => (int)$throughput['last_60m'],
				'rate_per_min'  => round((int)$throughput['last_60m'] / 60.0, 2),
			),
			'last_hour' => array(
				'total'       => $totalLastHour,
				'succeeded'   => $statusMix['succeeded'],
				'failed'      => $statusMix['failed'],
				'in_flight'   => $statusMix['started'],
				'success_pct' => $succRate,
			),
			'by_type'         => $byType,
			'running'         => $running,
			'running_count'   => count($running),
			'recent_failures' => $recentFailures,
		));

		Yii::app()->end();
	}
	
	/**
	 * This action is executed for every page requested by user to check
	 * if daemon is currently active. 
	 */
	public function actionCheckDaemon()
	{	
		$realCheck = false;
        $errorMsg = 'Unspecified error';
		$retCode = Yii::app()->daemon->checkDaemon($realCheck, $errorMsg);
		
		if($realCheck && $retCode!=Daemon::DAEMON_CHECK_OK)
        {
            $this->renderPartial('_daemonCheck', 
                    array('retCode'=>$retCode,'errorMsg'=>$errorMsg));
        }
	}
	
	/**
	 * This action sets current project for user currently being logged in.
	 */
	public function actionSetCurProject($proj, $ver)
	{	
		// Update current project
		Yii::app()->user->setCurProjectId($proj);
		
		// Update current project version
		Yii::app()->user->setCurProjectVer($ver);
		
		// Refresh the refferer page
		$urlReferrer = Yii::app()->request->urlReferrer;
		$this->redirect($urlReferrer);
	}
}