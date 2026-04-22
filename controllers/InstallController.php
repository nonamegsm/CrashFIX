<?php

namespace app\controllers;

use Yii;
use yii\db\Connection;
use yii\db\Query;
use yii\web\Controller;
use yii\web\Response;
use app\models\User;
use app\models\Usergroup;

/**
 * Web installer for CrashFix.
 *
 * Walks the operator through requirements check, database configuration,
 * schema migration (delegating to the migrations/ directory), creation of
 * the initial admin account, and finalisation.
 *
 * The installer is idempotent: it will redirect to the home page once
 * `config/installed.txt` exists, unless the request includes the
 * `?force=1` query flag (intended for re-running setup during development).
 */
class InstallController extends Controller
{
    public $layout = 'install';
    public $enableCsrfValidation = true;

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // If already installed and operator did not explicitly request to
        // re-run setup, send them home. The run-migrations endpoint is
        // still allowed so that re-invoking it is safe (it tracks state).
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
        $model = new \yii\base\DynamicModel(['host', 'dbname', 'username', 'password', 'tablePrefix']);
        $model->addRule(['host', 'dbname', 'username'], 'required')
              ->addRule(['host', 'dbname', 'username', 'tablePrefix'], 'string', ['max' => 64])
              ->addRule(['password'], 'string', ['max' => 256])
              ->addRule(['tablePrefix'], 'match', ['pattern' => '/^[A-Za-z0-9_]*$/', 'message' => 'Table prefix may only contain letters, digits and underscores.']);

        $existing = $this->readUserParams();
        $model->host = $this->extractDsnPart($existing['db_connection_string'] ?? '', 'host') ?: '127.0.0.1';
        $model->dbname = $this->extractDsnPart($existing['db_connection_string'] ?? '', 'dbname') ?: 'crashfix';
        $model->username = $existing['db_username'] ?? 'root';
        $model->password = '';
        $model->tablePrefix = $existing['db_table_prefix'] ?? 'tbl_';

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $dsn = "mysql:host={$model->host};dbname={$model->dbname}";

            try {
                $db = new Connection([
                    'dsn' => $dsn,
                    'username' => $model->username,
                    'password' => $model->password,
                    'charset' => 'utf8mb4',
                ]);
                $db->open();
                $db->close();

                $this->writeUserParams([
                    'db_connection_string' => $dsn,
                    'db_username'          => $model->username,
                    'db_password'          => $model->password,
                    'db_table_prefix'      => $model->tablePrefix,
                ]);

                return $this->redirect(['migrate']);
            } catch (\Exception $e) {
                $model->addError('host', 'Could not connect to database: ' . $e->getMessage());
            }
        }

        return $this->render('db-config', ['model' => $model]);
    }

    public function actionMigrate()
    {
        return $this->render('migrate');
    }

    /**
     * Runs all pending migrations from the migrations/ directory.
     *
     * Returns JSON. Idempotent: re-running it after a successful install
     * is a no-op (already-applied migrations are skipped).
     */
    public function actionRunMigrations()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        try {
            // Force a fresh DB connection in case user_params.ini was just written.
            Yii::$app->db->close();
            Yii::$app->db->open();

            $applied = $this->runPendingMigrations(Yii::getAlias('@app/migrations'));

            return [
                'success' => true,
                'applied' => $applied,
                'message' => $applied
                    ? 'Applied ' . count($applied) . ' migration(s).'
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

        if ($model->load(Yii::$app->request->post())) {
            $adminGroup = Usergroup::findOne(['name' => 'Admin']);
            $model->usergroup = $adminGroup ? $adminGroup->id : 1;

            // beforeSave() hashes the password, applies salt, sets status,
            // and OR's in the standard-user flag. Pre-fill with placeholders
            // that satisfy required-validators but get overwritten.
            $model->salt   = 'placeholder';
            $model->status = User::STATUS_ACTIVE;
            $model->flags  = User::FLAG_STANDARD_USER;

            if ($model->save()) {
                // First admin created: clear the password-reset flag so the
                // operator isn't forced through the reset flow on first login.
                $model->flags &= ~User::FLAG_PASSWORD_RESETTED;
                $model->save(false, ['flags']);

                return $this->redirect(['finish']);
            }

            Yii::error($model->errors, 'install');
        }

        return $this->render('create-admin', ['model' => $model]);
    }

    public function actionFinish()
    {
        @file_put_contents(
            Yii::getAlias('@app/config/installed.txt'),
            date('Y-m-d H:i:s')
        );
        return $this->render('finish');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Runs every migration in $path that hasn't been applied yet.
     *
     * Mimics what `yii migrate/up` does on the console, but inside the
     * web request so the installer does not require shell access.
     *
     * @return string[] versions of newly-applied migrations
     */
    protected function runPendingMigrations(string $path): array
    {
        $db = Yii::$app->db;
        $migrationTable = '{{%migration}}';
        $resolved = $db->quoteSql($migrationTable);

        // Bootstrap the migration history table if missing.
        if ($db->getTableSchema($db->tablePrefix . 'migration', true) === null) {
            $db->createCommand()->createTable($migrationTable, [
                'version'    => 'varchar(180) NOT NULL PRIMARY KEY',
                'apply_time' => 'integer',
            ])->execute();
            $db->createCommand()->insert($migrationTable, [
                'version'    => \yii\db\Migration::BASE_MIGRATION,
                'apply_time' => time(),
            ])->execute();
        }

        $appliedVersions = (new Query())
            ->select('version')
            ->from($migrationTable)
            ->column($db);
        $applied = array_flip($appliedVersions);

        $applied_now = [];

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

            if ($migration->up() === false) {
                throw new \RuntimeException("Migration {$version} failed.");
            }

            $db->createCommand()->insert($migrationTable, [
                'version'    => $version,
                'apply_time' => time(),
            ])->execute();

            $applied_now[] = $version;
        }

        return $applied_now;
    }

    /**
     * @return array<string,string>
     */
    protected function readUserParams(): array
    {
        $path = Yii::getAlias('@app/config/user_params.ini');
        if (!is_file($path)) {
            return [];
        }
        $parsed = @parse_ini_file($path);
        return is_array($parsed) ? $parsed : [];
    }

    /**
     * @param array<string,string> $params
     */
    protected function writeUserParams(array $params): void
    {
        $lines = ["[db]"];
        foreach ($params as $key => $value) {
            $escaped = str_replace('"', '\"', (string) $value);
            $lines[] = sprintf('%s = "%s"', $key, $escaped);
        }
        $contents = implode(PHP_EOL, $lines) . PHP_EOL;
        file_put_contents(Yii::getAlias('@app/config/user_params.ini'), $contents);
    }

    /**
     * Pulls a key (e.g. host, dbname) out of a `mysql:host=...;dbname=...` DSN.
     */
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
