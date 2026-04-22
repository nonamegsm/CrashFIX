<?php

namespace app\components;

use Yii;
use app\models\Project;
use app\models\User;
use app\models\Appversion;

class WebUser extends \yii\web\User
{
    private $_model;
    
    public function getMyProjects()
    {
        if ($this->isGuest) {
            Yii::error("getMyProjects: user is guest", 'webuser');
            return [];
        }
        Yii::error("getMyProjects: user is NOT guest. ID=" . $this->getId(), 'webuser');
        
        // Assuming Project::STATUS_ACTIVE is 0
        $projects = Project::find()
            ->innerJoin('tbl_user_project_access', 'tbl_user_project_access.project_id = tbl_project.id')
            ->where(['tbl_user_project_access.user_id' => $this->getId(), 'tbl_project.status' => Project::STATUS_ACTIVE])
            ->orderBy('name ASC')
            ->all();
            
        Yii::error("getMyProjects: found " . count($projects) . " projects", 'webuser');
        return $projects;
    }

    public function isMyProject($projectId)
    {
        foreach($this->getMyProjects() as $myProject) {
            if($myProject->id == $projectId) return true;
        }
        return false;
    }

    public function getCurProjectId()
    {
        if ($this->isGuest) {
            return false;
        }
        $myProjects = $this->getMyProjects();
        $user = $this->loadModel();
        if($user === null) return false;
        
        $projectId = $user->cur_project_id;
        
        if($projectId != null) {
            foreach($myProjects as $proj) {
                if($proj->id == $projectId) return $projectId;
            }
        }
        
        if(count($myProjects) > 0)
            $projectId = $myProjects[0]->id;			
        else 
            $projectId = false;
            
        $user->cur_project_id = $projectId;
        $user->save(false);
            
        return $projectId;		
    }
    
    public function getCurProject()
    {
        return Project::findOne($this->getCurProjectId());
    }

    public function setCurProjectId($projectId)
    {
        $myProjects = $this->getMyProjects();		
        $user = $this->loadModel();
        
        foreach($myProjects as $proj) {			
            if($proj->id == $projectId) {
                $user->cur_project_id = $projectId;											
                $user->save(false);
                return true;
            }
        }		
        return false;
    }

    public function getCurProjectVersions(&$selVer)
    {
        $curProjId = $this->getCurProjectId();
        if(!$curProjId) {
            throw new \yii\web\ForbiddenHttpException('Invalid request.');
        }
        
        $models = Appversion::find()->where(['project_id' => $curProjId])->orderBy('version DESC')->all();
        $versions = [];						
        foreach($models as $model) {
            $versions[$model->id] = $model->version;
        }
        
        if(count($versions) == 0) {
            $versions[0] = '(not set)';
        }
        
        $versions[-1] = '(all)';
        
        $user = $this->loadModel();
        if($user->cur_appversion_id != null) {
            $selVer = $user->cur_appversion_id;			
            if(!array_key_exists($selVer, $versions)) {
                $selVer = key($versions);
                $user->cur_appversion_id = $selVer;
                $user->save(false);
            }
        }
        else {
            reset($versions);
            $selVer = key($versions);			
        }
        
        return $versions;
    }

    public function getCurProjectVer()
    {
        $selVer = 0;
        $this->getCurProjectVersions($selVer);
        return $selVer;
    }

    public function setCurProjectVer($ver)
    {
        $prevVer = false;
        $versions = $this->getCurProjectVersions($prevVer);
        
        if(array_key_exists($ver, $versions)) {
            $user = $this->loadModel();
            $user->cur_appversion_id = $ver;
            $user->save(false);
            return true;
        }
        return false;
    }

    /**
     * Overrides the parent can() method to provide support for legacy permission flags
     * stored in the usergroup table.
     */
    public function can($permissionName, $params = [], $allowCaching = true)
    {
        if ($this->isGuest) {
            return false;
        }

        $user = $this->loadModel();
        if ($user === null || $user->group === null) {
            return false;
        }

        // 1. Check if the permission matches a legacy flag in the usergroup table
        // Permissions starting with gperm_ or pperm_ are likely legacy flags
        if (strpos($permissionName, 'gperm_') === 0 || strpos($permissionName, 'pperm_') === 0) {
            $group = $user->group;
            
            // If checking a project-level permission (pperm) and project_id is provided, use the project role
            if (strpos($permissionName, 'pperm_') === 0 && isset($params['project_id'])) {
                $access = \app\models\UserProjectAccess::findOne([
                    'user_id' => $user->id,
                    'project_id' => $params['project_id']
                ]);
                if ($access && $access->usergroup) {
                    $group = $access->usergroup;
                }
            }

            if ($group->hasAttribute($permissionName)) {
                return (bool)$group->$permissionName;
            }

            // Handle special virtual permissions used in menus
            if ($permissionName == 'pperm_browse_some_crash_reports') {
                return $group->pperm_browse_crash_reports || $group->pperm_manage_crash_reports;
            }
            if ($permissionName == 'pperm_browse_some_bugs') {
                return $group->pperm_browse_bugs || $group->pperm_manage_bugs;
            }
            if ($permissionName == 'pperm_browse_some_debug_info') {
                return $group->pperm_browse_debug_info || $group->pperm_manage_debug_info;
            }
        }

        // 2. Fallback to standard Yii 2 RBAC
        return parent::can($permissionName, $params, $allowCaching);
    }

    public function loadModel()
    {
        if ($this->isGuest) {
            return null;
        }
        if($this->_model === null) {
            $this->_model = User::findOne($this->id);
        }
        return $this->_model;
    }
}
