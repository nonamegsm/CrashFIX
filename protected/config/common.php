<?php

/**
 * Returns common param array.
 * @return array common params. 
 */
function getCommonParams()
{
    return array(
        'version'=>'1.0.4', // CrashFix web app version       
    );
    
}


/**
 * This helper function is used for extracting db connection parameters from INI
 * file.
 * @param string $overrideTablePrefix Allows to override table prefix string.
 * @return array Database connection config array.
 */
function dbParams($overrideTablePrefix=null)
{	
	$userParams = parse_ini_file(dirname(__FILE__).DIRECTORY_SEPARATOR.'user_params.ini');

	return array(					
            'class'=>'CDbConnection',
            'connectionString'=>str_replace('%DATA_DIR%', dirname(__FILE__).'/../data', $userParams['db_connection_string']),
            'username'=>$userParams['db_username'],
            'password'=>$userParams['db_password'],
			'tablePrefix' => $overrideTablePrefix==null?$userParams['db_table_prefix']:$overrideTablePrefix,
			// Force utf8mb4 on every connection so that crash reports
			// containing non-Latin-1 strings (Cyrillic, CJK, emoji,
			// German esszett, ...) do not blow up tbl_customprop /
			// tbl_crashreport inserts with
			//   "Incorrect string value: '\xD0\xA4...' for column ..."
			// `utf8mb4` is the full 4-byte UTF-8; the older alias
			// `utf8` is the deprecated 3-byte form that rejects
			// supplementary-plane chars (most emojis).
			'charset' => 'utf8mb4',
            //'emulatePrepare'=>true,  // needed by some MySQL installations
		    //'schemaCachingDuration'=>3600, // one hour        
        );
}

