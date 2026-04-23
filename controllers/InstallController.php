<?php

namespace app\controllers;

use Yii;
use yii\db\Connection;
use yii\db\Query;
use yii\web\Controller;
use yii\web\Response;
use app\models\User;
use app\models\Usergroup;
use app\components\UserParamsIni;

/**
 * Web installer for CrashFix.
 *
 * Supports a clean **existing Yii1 / legacy database** path: connect to the
 * same MySQL schema, optionally point file storage at the old
 * `protected/data` tree via {@see UserParamsIni::STORAGE_LAYOUT_LEGACY}, and
 * run migrations in **adopt** mode (benign “already exists” / duplicate errors
 * are treated as already applied so schema drift from production is handled
 * without hand-editing SQL).
 */
class InstallController extends Controller
{
    public $layout = 'install';
    public $enableCsrfValidation = true;

    /** @var string Session key: "fresh" | "existing_yii1" */
    private const SESSION_PROFILE = 'installer_profile';

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $installed = is_file(Yii::getAlias('@app/config/installed.txt'));
        $force = (bool) Yii::$app->request->get('force');

        if ($installed && !$force && $action->id !== 'finish') {
            return $this->redirect(['/site/index'])->send();
        }

        return true;
    }

    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionRequirements()
    {
        $configDir = Yii::getAlias('@app/config');
        $runtimeDir = Yii::getAlias('@app/runtime');
        $assetsDir = Yii::getAlias('@webroot/assets');

        $requirements = [
            ['name' => 'PHP Version >= 7.4',     'condition' => version_compare(PHP_VERSION, '7.4.0', '>='), 'memo' => 'PHP 7.4.0 or newer is required.'],
            ['name' => 'PDO extension',          'condition' => extension_loaded('pdo'),                     'memo' => 'PHP Data Objects extension.'],
            ['name' => 'PDO MySQL driver',       'condition' => extension_loaded('pdo_mysql'),               'memo' => 'Required to talk to the MySQL database.'],
            ['name' => 'GD extension',           'condition' => extension_loaded('gd'),                      'memo' => 'Used for screenshot thumbnails.'],
            ['name' => 'mbstring extension',     'condition' => extension_loaded('mbstring'),                'memo' => 'Required by Yii2 for multibyte string handling.'],
            ['name' => 'OpenSSL extension',      'condition' => extension_loaded('openssl'),                 'memo' => 'Required for secure cookie/key generation.'],
            ['name' => 'config/ writable',       'condition' => is_writable($configDir),                     'memo' => $configDir],
            ['name' => 'runtime/ writable',      'condition' => is_dir($runtimeDir) ? is_writable($runtimeDir) : @mkdir($runtimeDir, 0775, true), 'memo' => $runtimeDir],
            ['name' => 'web/assets/ writable',   'condition' => is_dir($assetsDir)  ? is_writable($assetsDir)  : @mkdir($assetsDir,  0775, true), 'memo' => $assetsDir],
        ];

        return $this->render('requirements', ['requirements' => $requirements]);
    }

    public function actionDbConfig()
    {
        $model = new \yii\base\DynamicModel([
            'install_profile',
            'host',
            'dbname',
            'username',
            'password',
            'tablePrefix',
            'legacy_data_path',
        ]);
        $model->addRule(['install_profile', 'host', 'dbname', 'username'], 'required')
            ->addRule(['install_profile'], 'in', ['range' => ['fresh', 'existing_yii1']])
            ->addRule(['host', 'dbname', 'username', 'tablePrefix'], 'string', ['max' => 64])
            ->addRule(['password'], 'string', ['max' => 256])
            ->addRule(['tablePrefix'], 'match', ['pattern' => '/^[A-Za-z0-9_]*$/', 'message' => 'Table prefix may only contain letters, digits and underscores.'])
            ->addRule(['legacy_data_path'], 'string', ['max' => 512]);

        $existing = UserParamsIni::readFlat(Yii::getAlias('@app/config/user_params.ini'));
        $model->install_profile = Yii::$app->session->get(self::SESSION_PROFILE);
        if ($model->install_profile === null || !in_array($model->install_profile, ['fresh', 'existing_yii1'], true)) {
            $model->install_profile = (($existing['storage_layout'] ?? '') === UserParamsIni::STORAGE_LAYOUT_LEGACY)
                ? 'existing_yii1'
                : 'fresh';
        }

        $model->host = $this->extractDsnPart($existing['db_connection_string'] ?? '', 'host') ?: '127.0.0.1';
        $model->dbname = $this->extractDsnPart($existing['db_connection_string'] ?? '', 'dbname') ?: 'crashfix';
        $model->username = $existing['db_username'] ?? 'root';
        $model->password = '';
        $model->tablePrefix = $existing['db_table_prefix'] ?? 'tbl_';
        $model->legacy_data_path = $existing['storage_base_path'] ?? '';

        if ($model->load(Yii::$app->request->post())) {
            if ($model->install_profile === 'existing_yii1') {
                $path = trim((string) $model->legacy_data_path);
                if ($path === '') {
                    $model->addError('legacy_data_path', 'Enter the full path to the old site’s protected/data folder (Yii1 CrashFix).');
                } elseif (!is_dir($path)) {
                    $model->addError('legacy_data_path', 'That path is not a directory or is not readable by PHP.');
                } else {
                    $hints = ['crashReports', 'debugInfo'];
                    $found = false;
                    foreach ($hints as $h) {
                        if (is_dir($path . DIRECTORY_SEPARATOR . $h)) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $model->addError(
                            'legacy_data_path',
                            'No crashReports/ or debugInfo/ folder found inside — double-check this is the Yii1 `protected/data` directory.'
                        );
                    }
                }
            } else {
                $model->legacy_data_path = '';
            }

            if (!$model->hasErrors() && $model->validate()) {
                $dsn = "mysql:host={$model->host};dbname={$model->dbname}";

                try {
                    $db = new Connection([
                        'dsn' => $dsn,
                        'username' => $model->username,
                        'password' => $model->password,
                        'charset' => 'utf8mb4',
                        'tablePrefix' => $model->tablePrefix,
                    ]);
                    $db->open();

                    if ($model->install_profile === 'existing_yii1') {
                        $this->assertExistingCrashfixSchema($db);
                    }

                    $db->close();

                    $storageLayout = UserParamsIni::STORAGE_LAYOUT_PROJECT;
                    $storageBase = '';
                    if ($model->install_profile === 'existing_yii1') {
                        $storageLayout = UserParamsIni::STORAGE_LAYOUT_LEGACY;
                        $storageBase = rtrim(str_replace('/', DIRECTORY_SEPARATOR, trim($model->legacy_data_path)), '\\/');
                    }

                    UserParamsIni::write(Yii::getAlias('@app/config/user_params.ini'), [
                        'db_connection_string' => $dsn,
                        'db_username' => $model->username,
                        'db_password' => $model->password,
                        'db_table_prefix' => $model->tablePrefix,
                    ], [
                        'storage_layout' => $storageLayout,
                        'storage_base_path' => $storageBase,
                    ]);

                    Yii::$app->session->set(self::SESSION_PROFILE, $model->install_profile);

                    return $this->redirect(['migrate']);
                } catch (\Exception $e) {
                    $model->addError('host', 'Database error: ' . $e->getMessage());
                }
            }
        }

        return $this->render('db-config', ['model' => $model]);
    }

    public function actionMigrate()
    {
        $profile = Yii::$app->session->get(self::SESSION_PROFILE, 'fresh');

        return $this->render('migrate', [
            'existingYii1' => ($profile === 'existing_yii1'),
        ]);
    }

    public function actionRunMigrations()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $legacyAdopt = Yii::$app->session->get(self::SESSION_PROFILE) === 'existing_yii1'
            && (bool) Yii::$app->request->post('legacy_adopt', true);

        try {
            Yii::$app->db->close();
            Yii::$app->db->open();

            $applied = $this->runPendingMigrations(Yii::getAlias('@app/migrations'), $legacyAdopt);

            return [
                'success' => true,
                'applied' => $applied,
                'message' => $applied
                    ? 'Applied ' . count($applied) . ' migration step(s).'
                    : 'Schema is already up to date.',
            ];
        } catch (\Throwable $e) {
            Yii::error($e->__toString(), 'install');

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function actionCreateAdmin()
    {
        $model = new User();
        $model->scenario = 'default';

        $allowSkip = Yii::$app->session->get(self::SESSION_PROFILE) === 'existing_yii1';

        if ($model->load(Yii::$app->request->post())) {
            $adminGroup = Usergroup::findOne(['name' => 'Admin']);
            $model->usergroup = $adminGroup ? $adminGroup->id : 1;

            $model->salt = 'placeholder';
            $model->status = User::STATUS_ACTIVE;
            $model->flags = User::FLAG_STANDARD_USER;

            if ($model->save()) {
                $model->flags &= ~User::FLAG_PASSWORD_RESETTED;
                $model->save(false, ['flags']);

                return $this->redirect(['finish']);
            }

            Yii::error($model->errors, 'install');
        }

        return $this->render('create-admin', [
            'model' => $model,
            'allowSkip' => $allowSkip,
        ]);
    }

    /**
     * Skip admin creation when moving from Yii1 — existing users keep working.
     */
    public function actionSkipAdmin()
    {
        if (Yii::$app->session->get(self::SESSION_PROFILE) !== 'existing_yii1') {
            return $this->redirect(['create-admin']);
        }

        Yii::$app->session->setFlash(
            'installer_notice',
            'No new administrator was created. Log in with an existing account from your Yii1 site.'
        );

        return $this->redirect(['finish']);
    }

    public function actionFinish()
    {
        @file_put_contents(
            Yii::getAlias('@app/config/installed.txt'),
            date('Y-m-d H:i:s')
        );
        Yii::$app->session->remove(self::SESSION_PROFILE);

        return $this->render('finish');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @return string[] versions of newly-applied migrations (values may include " (adopted)" suffix)
     */
    protected function runPendingMigrations(string $path, bool $legacyAdopt = false): array
    {
        $db = Yii::$app->db;
        $migrationTable = '{{%migration}}';

        if ($db->getTableSchema($db->tablePrefix . 'migration', true) === null) {
            $db->createCommand()->createTable($migrationTable, [
                'version' => 'varchar(180) NOT NULL PRIMARY KEY',
                'apply_time' => 'integer',
            ])->execute();
            $db->createCommand()->insert($migrationTable, [
                'version' => \yii\db\Migration::BASE_MIGRATION,
                'apply_time' => time(),
            ])->execute();
        }

        $appliedVersions = (new Query())
            ->select('version')
            ->from($migrationTable)
            ->column($db);
        $applied = array_flip($appliedVersions);

        $appliedNow = [];

        $files = glob(rtrim($path, '/\\') . DIRECTORY_SEPARATOR . 'm*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $version = basename($file, '.php');
            if (isset($applied[$version])) {
                continue;
            }

            require_once $file;

            if (!class_exists($version, false)) {
                throw new \RuntimeException("Migration class {$version} not found in {$file}.");
            }

            /** @var \yii\db\Migration $migration */
            $migration = new $version(['db' => $db]);

            try {
                if ($migration->up() === false) {
                    throw new \RuntimeException("Migration {$version} returned false.");
                }
            } catch (\Throwable $e) {
                if ($legacyAdopt && $this->isSkippableLegacyMigrationError($e)) {
                    Yii::warning(
                        "Installer legacy adopt: marking {$version} as applied (benign failure): " . $e->getMessage(),
                        'install'
                    );
                    $db->createCommand()->insert($migrationTable, [
                        'version' => $version,
                        'apply_time' => time(),
                    ])->execute();
                    $appliedNow[] = $version . ' (adopted)';

                    continue;
                }
                throw $e;
            }

            $db->createCommand()->insert($migrationTable, [
                'version' => $version,
                'apply_time' => time(),
            ])->execute();

            $appliedNow[] = $version;
        }

        return $appliedNow;
    }

    private function isSkippableLegacyMigrationError(\Throwable $e): bool
    {
        $code = null;
        if ($e instanceof \PDOException && isset($e->errorInfo[1])) {
            $code = (int) $e->errorInfo[1];
        }
        if ($e instanceof \yii\db\Exception && isset($e->errorInfo[1])) {
            $code = (int) $e->errorInfo[1];
        }
        if ($code !== null && in_array($code, [1050, 1060, 1061, 1062], true)) {
            return true;
        }

        $m = $e->getMessage();

        return (bool) preg_match('/Base table or view already exists/i', $m)
            || (bool) preg_match('/Duplicate column name/i', $m)
            || (bool) preg_match('/duplicate key name/i', $m)
            || (bool) preg_match('/Duplicate entry/i', $m);
    }

    /**
     * Light-weight sanity check so "existing Yii1" installs fail fast with a
     * clear message instead of a cryptic migration error.
     */
    private function assertExistingCrashfixSchema(Connection $db): void
    {
        $p = $db->tablePrefix;
        foreach (['user', 'crashreport', 'project'] as $logical) {
            $schema = $db->getTableSchema($p . $logical, true);
            if ($schema === null) {
                throw new \RuntimeException(
                    "This database does not look like an existing CrashFix schema — table `{$p}{$logical}` is missing. "
                    . 'Pick “New installation” if you meant to create tables from scratch, or verify the database name and table prefix.'
                );
            }
        }
    }

    protected function extractDsnPart(string $dsn, string $key): ?string
    {
        if ($dsn === '' || strpos($dsn, ':') === false) {
            return null;
        }
        [, $tail] = explode(':', $dsn, 2);
        foreach (explode(';', $tail) as $pair) {
            $pair = trim($pair);
            if ($pair === '' || strpos($pair, '=') === false) {
                continue;
            }
            [$k, $v] = explode('=', $pair, 2);
            if (strcasecmp(trim($k), $key) === 0) {
                return trim($v);
            }
        }

        return null;
    }
}
