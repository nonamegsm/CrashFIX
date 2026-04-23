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
				'actions'=>array('index', 'logout', 'resetPassword', 'setCurProject', 'checkDaemon',
				                 'failedReports', 'failedRetry', 'failedDelete'),
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

	/**
	 * Show every crash report and debug-info file in the current
	 * project that the daemon could not process. Each row shows the
	 * most recent processing-error message so the user can see why
	 * the daemon rejected it without clicking through to detail.
	 *
	 * Project-scoped via the user's current project. Anyone with at
	 * least one of the relevant browse permissions sees the page;
	 * the two grids are individually gated and degrade to a
	 * "no permission" notice when neither is held.
	 */
	public function actionFailedReports()
	{
		$this->sidebarActiveItem = 'FailedReports';

		$user = Yii::app()->user;
		$projectId = (int)$user->getCurProjectId();
		$canCrash  = (bool)$user->checkAccess('pperm_browse_some_crash_reports');
		$canDebug  = (bool)$user->checkAccess('pperm_browse_some_debug_info');

		// Free-text search params (one per grid). Distinct names so
		// search/sort/page on one section never resets the other.
		$req = Yii::app()->request;
		$crashQ = trim((string)$req->getParam('cr-q', ''));
		$debugQ = trim((string)$req->getParam('di-q', ''));

		$db = Yii::app()->db;
		$peTbl = ProcessingError::model()->tableName();

		// Helper closure: builds the OR clause that matches filename /
		// guid / any historical processingerror.message via EXISTS
		// subquery. EXISTS keeps the filter as a single SQL pass and
		// avoids HAVING-vs-WHERE alias scoping problems with
		// `last_error`.
		$buildSearchCondition = function($q, $fileCol, $guidCol, $peType, $peTbl) {
			if ($q === '') return null;
			$like = '%' . addcslashes($q, '\\%_') . '%';
			return array(
				'condition' => "(
					t.{$fileCol} LIKE :search_q OR
					t.{$guidCol} LIKE :search_q OR
					EXISTS (SELECT 1 FROM {$peTbl} pe2
					         WHERE pe2.srcid = t.id
					           AND pe2.type  = " . (int)$peType . "
					           AND pe2.message LIKE :search_q)
				)",
				'params' => array(':search_q' => $like),
			);
		};

		// ---- Failed crash reports -----------------------------------
		$crashProvider = null;
		$crashTotal = 0;
		if($projectId > 0 && $canCrash)
		{
			$crCriteria = new CDbCriteria();
			$crCriteria->select = array(
				'*',
				'(SELECT pe.message FROM '.$peTbl.' pe '.
				' WHERE pe.type = '.(int)ProcessingError::TYPE_CRASH_REPORT_ERROR.
				' AND pe.srcid = t.id ORDER BY pe.id DESC LIMIT 1) AS last_error',
			);
			$crCriteria->condition = 't.project_id = :pid AND t.status = :st';
			$crCriteria->params    = array(':pid'=>$projectId, ':st'=>CrashReport::STATUS_INVALID);

			$srch = $buildSearchCondition($crashQ, 'srcfilename', 'crashguid',
				ProcessingError::TYPE_CRASH_REPORT_ERROR, $peTbl);
			if ($srch !== null) {
				$crCriteria->condition .= ' AND ' . $srch['condition'];
				$crCriteria->params = array_merge($crCriteria->params, $srch['params']);
			}

			$crashProvider = new CActiveDataProvider('CrashReport', array(
				'criteria'   => $crCriteria,
				'pagination' => array('pageSize'=>50, 'pageVar'=>'cr-page'),
				'sort'       => array(
					'sortVar'      => 'cr-sort',
					'defaultOrder' => 't.received DESC',
					'attributes'   => array(
						'id'          => array('asc'=>'t.id ASC',          'desc'=>'t.id DESC'),
						'srcfilename' => array('asc'=>'t.srcfilename ASC', 'desc'=>'t.srcfilename DESC'),
						'received'    => array('asc'=>'t.received ASC',    'desc'=>'t.received DESC'),
						'filesize'    => array('asc'=>'t.filesize ASC',    'desc'=>'t.filesize DESC'),
					),
				),
			));
			$crashTotal = $crashProvider->getTotalItemCount();
		}

		// ---- Failed debug-info files --------------------------------
		$debugProvider = null;
		$debugTotal = 0;
		if($projectId > 0 && $canDebug)
		{
			$diCriteria = new CDbCriteria();
			$diCriteria->select = array(
				'*',
				'(SELECT pe.message FROM '.$peTbl.' pe '.
				' WHERE pe.type = '.(int)ProcessingError::TYPE_DEBUG_INFO_ERROR.
				' AND pe.srcid = t.id ORDER BY pe.id DESC LIMIT 1) AS last_error',
			);
			$diCriteria->condition = 't.project_id = :pid AND t.status = :st';
			$diCriteria->params    = array(':pid'=>$projectId, ':st'=>DebugInfo::STATUS_INVALID);

			$srch = $buildSearchCondition($debugQ, 'filename', 'guid',
				ProcessingError::TYPE_DEBUG_INFO_ERROR, $peTbl);
			if ($srch !== null) {
				$diCriteria->condition .= ' AND ' . $srch['condition'];
				$diCriteria->params = array_merge($diCriteria->params, $srch['params']);
			}

			$debugProvider = new CActiveDataProvider('DebugInfo', array(
				'criteria'   => $diCriteria,
				'pagination' => array('pageSize'=>50, 'pageVar'=>'di-page'),
				'sort'       => array(
					'sortVar'      => 'di-sort',
					'defaultOrder' => 't.dateuploaded DESC',
					'attributes'   => array(
						'id'           => array('asc'=>'t.id ASC',           'desc'=>'t.id DESC'),
						'filename'     => array('asc'=>'t.filename ASC',     'desc'=>'t.filename DESC'),
						'status'       => array('asc'=>'t.status ASC',       'desc'=>'t.status DESC'),
						'dateuploaded' => array('asc'=>'t.dateuploaded ASC', 'desc'=>'t.dateuploaded DESC'),
						'filesize'     => array('asc'=>'t.filesize ASC',     'desc'=>'t.filesize DESC'),
					),
				),
			));
			$debugTotal = $debugProvider->getTotalItemCount();
		}

		$this->render('failedReports', array(
			'crashProvider' => $crashProvider,
			'debugProvider' => $debugProvider,
			'crashTotal'    => $crashTotal,
			'debugTotal'    => $debugTotal,
			'projectId'     => $projectId,
			'canCrash'      => $canCrash,
			'canDebug'      => $canDebug,
			'crashQ'        => $crashQ,
			'debugQ'        => $debugQ,
		));
	}

	/**
	 * POST /site/failedRetry
	 *
	 * Re-queue failed items by flipping their status back to Pending.
	 * Existing tbl_processingerror rows are kept so the user can still
	 * see the previous failure reason.
	 *
	 * Inputs (POST):
	 *   kind = "crash" | "debug"
	 *   ONE of:
	 *     id    = integer  -- single row
	 *     ids[] = int[]    -- bulk by selection
	 *     all   = "1"      -- bulk all matching the current filter
	 *   q     = string    -- only meaningful when all=1
	 */
	public function actionFailedRetry()
	{
		if(!Yii::app()->request->isPostRequest)
			throw new CHttpException(405, 'POST required.');

		$req = Yii::app()->request;
		$kind = (string)$req->getPost('kind', '');
		$id   = (int)   $req->getPost('id', 0);
		$ids  = (array) $req->getPost('ids', array());
		$all  = (string)$req->getPost('all', '') === '1';
		$q    = trim((string)$req->getPost('q', ''));

		$projectId = (int)Yii::app()->user->getCurProjectId();
		if($projectId <= 0 || ($kind !== 'crash' && $kind !== 'debug'))
		{
			Yii::app()->user->setFlash('failed-retry-error', 'Invalid retry request.');
			$this->redirect(array('site/failedReports'));
			return;
		}

		$ctx = $this->buildFailedBulkContext($kind, $projectId, $id, $ids, $all, $q);
		if($ctx['error'] !== null)
		{
			Yii::app()->user->setFlash('failed-retry-error', $ctx['error']);
			$this->redirect(array('site/failedReports'));
			return;
		}

		// Single SQL UPDATE for the matching rows.
		$newStatus = ($kind === 'crash')
			? CrashReport::STATUS_PENDING_PROCESSING
			: DebugInfo::STATUS_PENDING_PROCESSING;
		$count = $ctx['model']->updateAll(
			array('status' => $newStatus),
			$ctx['condition'],
			$ctx['params']
		);

		Yii::app()->user->setFlash('failed-retry-success',
			"Re-queued $count " . ($kind === 'crash' ? 'crash report' : 'debug info file')
			. ($count === 1 ? '' : 's') .
			". Daemon will retry on the next poll cycle.");
		$this->redirect($this->failedReturnUrl($req));
	}

	/**
	 * POST /site/failedDelete
	 *
	 * Permanently delete failed items. Uses ActiveRecord delete()
	 * (not raw DELETE) so beforeDelete()/afterDelete() hooks fire
	 * and clean up the on-disk files referenced by the model.
	 * Capped at 500 rows per request to stay under PHP timeouts;
	 * if more rows match the user is told to click again.
	 *
	 * Inputs identical to actionFailedRetry.
	 */
	public function actionFailedDelete()
	{
		if(!Yii::app()->request->isPostRequest)
			throw new CHttpException(405, 'POST required.');

		$req = Yii::app()->request;
		$kind = (string)$req->getPost('kind', '');
		$id   = (int)   $req->getPost('id', 0);
		$ids  = (array) $req->getPost('ids', array());
		$all  = (string)$req->getPost('all', '') === '1';
		$q    = trim((string)$req->getPost('q', ''));

		$projectId = (int)Yii::app()->user->getCurProjectId();
		if($projectId <= 0 || ($kind !== 'crash' && $kind !== 'debug'))
		{
			Yii::app()->user->setFlash('failed-retry-error', 'Invalid delete request.');
			$this->redirect(array('site/failedReports'));
			return;
		}

		$ctx = $this->buildFailedBulkContext($kind, $projectId, $id, $ids, $all, $q);
		if($ctx['error'] !== null)
		{
			Yii::app()->user->setFlash('failed-retry-error', $ctx['error']);
			$this->redirect(array('site/failedReports'));
			return;
		}

		// Cap per click. AR delete() fires per-row hooks that unlink
		// files; processing 5000 of those in one request risks PHP
		// max_execution_time / FastCGI timeouts.
		$cap = 500;
		$criteria = new CDbCriteria();
		$criteria->condition = $ctx['condition'];
		$criteria->params    = $ctx['params'];
		$criteria->limit     = $cap + 1;
		$rows = $ctx['model']->findAll($criteria);

		$hasMore = count($rows) > $cap;
		if($hasMore) array_pop($rows);

		$deleted = 0;
		$errors  = 0;
		foreach($rows as $row)
		{
			try {
				if($row->delete()) $deleted++;
				else               $errors++;
			} catch(Exception $e) {
				$errors++;
				Yii::log('failed-delete row '.$row->id.': '.$e->getMessage(), 'warning');
			}
		}

		$kindLabel = ($kind === 'crash') ? 'crash report' : 'debug info file';
		$msg = "Deleted $deleted $kindLabel" . ($deleted === 1 ? '' : 's') . '.';
		if($errors > 0) $msg .= " $errors could not be deleted (see logs).";
		if($hasMore)    $msg .= " More rows match the filter; click Delete again to continue.";
		Yii::app()->user->setFlash($errors === 0 ? 'failed-retry-success' : 'failed-retry-error', $msg);
		$this->redirect($this->failedReturnUrl($req));
	}

	/**
	 * Shared validation + WHERE-clause builder for the bulk retry /
	 * delete endpoints. Returns
	 *   array('error' => string|null, 'model' => CActiveRecord,
	 *         'condition' => string, 'params' => array)
	 *
	 * Three input modes supported:
	 *   single  -> id   given      -> WHERE id = :id
	 *   bulk    -> ids[] given     -> WHERE id IN (...)
	 *   all     -> all=1 given     -> WHERE status IN (failed-statuses)
	 *                                  + free-text filter via EXISTS
	 *                                    on tbl_processingerror.message
	 *                                    matching the same shape as
	 *                                    actionFailedReports().
	 */
	private function buildFailedBulkContext($kind, $projectId, $id, array $ids, $all, $q)
	{
		$user = Yii::app()->user;

		if($kind === 'crash')
		{
			if(!$user->checkAccess('pperm_browse_some_crash_reports'))
				throw new CHttpException(403);
			$model     = CrashReport::model();
			$statuses  = array(CrashReport::STATUS_INVALID);
			$fileCol   = 'srcfilename';
			$guidCol   = 'crashguid';
			$peType    = ProcessingError::TYPE_CRASH_REPORT_ERROR;
		}
		else
		{
			if(!$user->checkAccess('pperm_browse_some_debug_info'))
				throw new CHttpException(403);
			$model     = DebugInfo::model();
			$statuses  = array(DebugInfo::STATUS_INVALID);
			$fileCol   = 'filename';
			$guidCol   = 'guid';
			$peType    = ProcessingError::TYPE_DEBUG_INFO_ERROR;
		}

		$tbl    = $model->tableName();
		$peTbl  = ProcessingError::model()->tableName();
		$statusCsv = implode(',', array_map('intval', $statuses));

		// Single
		if($id > 0)
		{
			return array(
				'error'     => null,
				'model'     => $model,
				'condition' => "id = :id AND project_id = :pid AND status IN ($statusCsv)",
				'params'    => array(':id'=>(int)$id, ':pid'=>$projectId),
			);
		}

		// Bulk by selection
		if(!empty($ids))
		{
			$cleanIds = array();
			foreach($ids as $v) {
				$v = (int)$v;
				if($v > 0) $cleanIds[] = $v;
			}
			if(empty($cleanIds))
				return array('error'=>'No items selected.', 'model'=>null,
				             'condition'=>null, 'params'=>null);

			$idCsv = implode(',', $cleanIds);
			return array(
				'error'     => null,
				'model'     => $model,
				'condition' => "id IN ($idCsv) AND project_id = :pid AND status IN ($statusCsv)",
				'params'    => array(':pid'=>$projectId),
			);
		}

		// All matching (optionally filtered by q)
		if($all)
		{
			$cond   = "project_id = :pid AND status IN ($statusCsv)";
			$params = array(':pid'=>$projectId);
			if($q !== '')
			{
				$like = '%' . addcslashes($q, '\\%_') . '%';
				$cond .= " AND (
					$fileCol LIKE :search_q OR
					$guidCol LIKE :search_q OR
					EXISTS (SELECT 1 FROM $peTbl pe2
					         WHERE pe2.srcid = $tbl.id
					           AND pe2.type  = " . (int)$peType . "
					           AND pe2.message LIKE :search_q)
				)";
				$params[':search_q'] = $like;
			}
			return array(
				'error'     => null,
				'model'     => $model,
				'condition' => $cond,
				'params'    => $params,
			);
		}

		return array('error'=>'No items targeted.', 'model'=>null,
		             'condition'=>null, 'params'=>null);
	}

	/**
	 * Redirect target after a bulk action: try to preserve the
	 * filter/sort/page so the user lands back on the same view.
	 * Falls back to bare /site/failedReports if no return URL was POSTed
	 * or it looks suspicious (open-redirect guard).
	 */
	private function failedReturnUrl($req)
	{
		$back = (string)$req->getPost('return', '');
		if($back !== '' && strncmp($back, '/', 1) === 0
			&& strpos($back, "\n") === false && strpos($back, "\r") === false) {
			return $back;
		}
		return array('site/failedReports');
	}
}