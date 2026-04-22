<?php

namespace app\components;

use Yii;
use app\models\Project;
use app\models\Appversion;
use app\models\Crashreport;
use app\models\Debuginfo;
use yii\web\UploadedFile;

/**
 * Used for batch import of crash report files and/or symbol files from the import
 * directory. 
 */
class BatchImporter
{
    const IMPORT_CRASH_REPORTS = 1;
    const IMPORT_DEBUG_INFO    = 2;
    
    private $_importedCrashReportsCount = 0;
    private $_importedDebugInfoCount = 0;
    
    /**
     * Performs batch import of crash reports and symbol files. 
     */
    public function importFiles($dirName, &$countOfCrashReports, &$countOfDebugInfo)
    {
        if (!is_dir($dirName)) {
            Yii::error('BatchImporter: not a directory: ' . $dirName);
            return false;
        }
        
        $importLockFile = $dirName . '/importlock';
        if (file_exists($importLockFile)) {
            Yii::warning('BatchImporter: found import lock file ' . $importLockFile . '; import not allowed.');
            return false;
        }
               
        $this->_importedCrashReportsCount = 0;
        $this->_importedDebugInfoCount = 0;
        
        $this->importFilesOf($dirName . '/crashReports', self::IMPORT_CRASH_REPORTS);
        $this->importFilesOf($dirName . '/debugInfo', self::IMPORT_DEBUG_INFO);
        
        $countOfCrashReports = $this->_importedCrashReportsCount;
        $countOfDebugInfo = $this->_importedDebugInfoCount;
        
        return true;
    }    
    
    private function importFilesOf($dirName, $type)
    {
        if (!is_dir($dirName)) {
            Yii::error('BatchImporter: not a directory: ' . $dirName);
            return false;
        }
        
        $fileList = scandir($dirName);
        if ($fileList === false) {
            Yii::error('Directory name is invalid: ' . $dirName);
            return false;
        }
        
        foreach ($fileList as $file) {
            if ($file != '.' && $file != '..' && is_dir($dirName . '/' . $file)) {
                $subDir = $dirName . '/' . $file;                
                $this->importProjectFiles($subDir, $file, $type);                
            }
        }
        
        return true;
    } 
        
    private function importProjectFiles($dirName, $projName, $type)
    {
        $project = Project::findOne(['name' => $projName]);
        if ($project === null) {
            Yii::error('Such a project name not found: ' . $projName);
            return false;
        }
        
        $fileList = scandir($dirName);
        if ($fileList === false) {
            Yii::error('Directory name is invalid: ' . $dirName);
            return false;
        }
        
        foreach ($fileList as $file) {
            if ($file != '.' && $file != '..' && is_dir($dirName . '/' . $file)) {
                $appver = Appversion::findOne(['version' => $file, 'project_id' => $project->id]);
                if (!$appver) {
                    $appver = new Appversion();
                    $appver->version = $file;
                    $appver->project_id = $project->id;
                    $appver->save();
                }
                
                $subDir = $dirName . '/' . $file;
                if ($type == self::IMPORT_CRASH_REPORTS) {
                    $this->importCrashReports($subDir, $project->id, $appver->id);
                } else {
                    $this->importDebugInfo($subDir, $project->id, $appver->id);
                }
            }
        }
        
        return true;
    }
        
    private function importCrashReports($dirName, $projectId, $projVerId)
    {
        $fileList = scandir($dirName);
        if ($fileList === false) {
            Yii::error('Directory name is invalid: ' . $dirName);
            return -1;
        }
                
        foreach ($fileList as $file) {
            $path = $dirName . '/' . $file;  
            $path_parts = pathinfo($path);
        
            if ($file != '.' && $file != '..' && is_file($path) && strtolower($path_parts['extension'] ?? '') == 'zip') {
                $crashReport = new Crashreport();
                $crashReport->project_id = $projectId;
                $crashReport->appversion = $projVerId;
                
                // Note: Manual simulation of UploadedFile for batch import
                // In Yii 2, this might need custom handling if the model relies on is_uploaded_file()
                
                if ($crashReport->save()) {
                    $this->_importedCrashReportsCount++;
                } else {
                    Yii::error('Could not import crash report file:' . $path);
                }
            }
        }
        
        return $this->_importedCrashReportsCount;
    }
    
    private function importDebugInfo($dirName, $projectId, $projVerId)
    {
        $fileList = scandir($dirName);
        if ($fileList === false) {
            Yii::error('Directory name is invalid: ' . $dirName);
            return -1;
        }
        
        foreach ($fileList as $file) {
            $path = $dirName . '/' . $file;            
            $path_parts = pathinfo($path);
        
            if ($file != '.' && $file != '..' && is_file($path) && strtolower($path_parts['extension'] ?? '') == 'pdb') {
                $debugInfo = new Debuginfo();
                $debugInfo->project_id = $projectId;       
                $debugInfo->guid = 'tmp_' . MiscHelpers::GUID();
                
                if ($debugInfo->save()) {
                    $this->_importedDebugInfoCount++;
                } else {
                    Yii::error('Could not import debug info file:' . $path);
                }
            }
        }
        
        return $this->_importedDebugInfoCount;
    }
}
