<?php

namespace app\models;

use Yii;
use yii\web\UploadedFile;

/**
 * This is the model class for table "tbl_bug".
 *
 * @property int $id
 * @property int $date_created
 * @property int $date_last_modified
 * @property int|null $date_closed
 * @property int $project_id
 * @property int $appversion_id
 * @property int $status
 * @property string $summary
 * @property string $description
 * @property int $reported_by
 * @property int $assigned_to
 * @property int $priority
 * @property int $reproducability
 * @property int|null $merged_into
 */
class Bug extends \yii\db\ActiveRecord
{
    // Open statuses.
    const STATUS_NEW      = 1;   // New bug.
    const STATUS_REVIEWED = 2;   // Bug is being reviewed.
    const STATUS_ACCEPTED = 3;   // Bug accepted.
    const STATUS_STARTED  = 4;   // Bug is being fixed.	
    const STATUS_OPEN_MAX = 100; // Maximum available open status.
    // Closed statuses.
    const STATUS_FIXED     = 101; // Bug has been fixed.
    const STATUS_VERIFIED  = 102; // Bug has been verified by QA
    const STATUS_DUPLICATE = 103; // Bug is a duplicate of another bug
    const STATUS_WONTFIX   = 104; // We have decided not to take an action on this bug.
    
    // Bug priorities
    const PRIORITY_LOW      = 1; // Low priority
    const PRIORITY_MEDIUM   = 2; // Medium priority
    const PRIORITY_HIGH     = 3; // High priority
    const PRIORITY_CRITICAL = 4; // Critical priority
    
    // Bug reproducibility constants
    const REPRO_NOT_TRIED   = 1; // Not tried to reproduce this bug.
    const REPRO_NEVER       = 2; // Bug can never be reproduced.  
    const REPRO_SOMETIMES   = 3; // Bug can be sometimes reproduced.
    const REPRO_ALWAYS      = 4; // Bug can always be reproduced.

    /** Transient: comment text supplied via the bug-view comment/change form. */
    public ?string $comment = null;

    /** Transient: file to attach as part of a comment/change. */
    public ?UploadedFile $fileAttachment = null;

    /**
     * Transient: comma-separated list of crashreport ids to link
     * (set by the controller from `?crashreport=N` URL parameter).
     * @var string|int[]|null
     */
    public $crashreports = null;

    /**
     * Transient: comma-separated list of crashgroup ids to link.
     * @var string|int[]|null
     */
    public $crashgroups = null;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_bug';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['date_closed', 'merged_into'], 'default', 'value' => null],
            [['assigned_to'], 'default', 'value' => -1],
            [['summary', 'description', 'priority', 'reproducability'], 'required'],
            [['date_created', 'date_last_modified', 'date_closed', 'project_id', 'appversion_id', 'status', 'reported_by', 'assigned_to', 'priority', 'reproducability', 'merged_into'], 'integer'],
            [['description', 'comment'], 'string'],
            [['summary'], 'string', 'max' => 256],
            [['crashreports', 'crashgroups'], 'safe'],
            [['fileAttachment'], 'file', 'skipOnEmpty' => true],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'date_created' => 'Date Created',
            'date_last_modified' => 'Date Last Modified',
            'date_closed' => 'Date Closed',
            'project_id' => 'Project ID',
            'appversion_id' => 'Appversion ID',
            'status' => 'Status',
            'summary' => 'Summary',
            'description' => 'Description',
            'reported_by' => 'Reported By',
            'assigned_to' => 'Assigned To',
            'priority' => 'Priority',
            'reproducability' => 'Reproducability',
            'merged_into' => 'Merged Into',
        ];
    }

    public function getReporter()
    {
        return $this->hasOne(User::class, ['id' => 'reported_by']);
    }

    public function getOwner()
    {
        return $this->hasOne(User::class, ['id' => 'assigned_to']);
    }

    public function getBugchanges()
    {
        return $this->hasMany(BugChange::class, ['bug_id' => 'id'])->orderBy('timestamp ASC');
    }

    public function getCrashreportLinks()
    {
        return $this->hasMany(BugCrashreport::class, ['bug_id' => 'id']);
    }

    public function getCrashgroupLinks()
    {
        return $this->hasMany(BugCrashgroup::class, ['bug_id' => 'id']);
    }

    public function isOpen(): bool
    {
        return (int) $this->status < self::STATUS_OPEN_MAX;
    }

    /**
     * Pre-fill the summary line based on the linked crash report or
     * crash group. Called from BugController::actionCreate when a
     * `?crashreport=N` or `?crashgroup=N` query string is present so
     * the developer doesn't have to re-type the exception module/type.
     */
    public function autoFillSummary(): void
    {
        if (!empty($this->crashreports)) {
            $id = is_array($this->crashreports) ? (int) reset($this->crashreports) : (int) $this->crashreports;
            $report = Crashreport::findOne($id);
            if ($report) {
                $this->summary       = $this->buildSummaryLine($report);
                $this->project_id    = $this->project_id    ?: (int) $report->project_id;
                $this->appversion_id = $this->appversion_id ?: (int) $report->appversion_id;
            }
            return;
        }

        if (!empty($this->crashgroups)) {
            $id = is_array($this->crashgroups) ? (int) reset($this->crashgroups) : (int) $this->crashgroups;
            $group = Crashgroup::findOne($id);
            if ($group) {
                $this->summary       = mb_substr((string) $group->title, 0, 256);
                $this->project_id    = $this->project_id    ?: (int) $group->project_id;
                $this->appversion_id = $this->appversion_id ?: (int) $group->appversion_id;
            }
        }
    }

    /**
     * Open a new bug from the bug-create form. Wraps the AR `save()` so
     * we can also write the initial BugChange row, link any pre-attached
     * crash reports / groups, and bump the project's last_modified
     * timestamp atomically.
     */
    public function open(): bool
    {
        $now      = time();
        $reporter = (int) (Yii::$app->user->id ?? 0);

        $this->date_created       = $now;
        $this->date_last_modified = $now;
        $this->status             = self::STATUS_NEW;
        $this->reported_by        = $reporter;

        if ($this->priority === null)        $this->priority        = self::PRIORITY_MEDIUM;
        if ($this->reproducability === null) $this->reproducability = self::REPRO_NOT_TRIED;
        if ($this->assigned_to === null)     $this->assigned_to     = -1;
        if ($this->appversion_id === null)   $this->appversion_id   = 0;

        $tx = Yii::$app->db->beginTransaction();
        try {
            if (!$this->save()) {
                $tx->rollBack();
                return false;
            }

            $this->writeChange([
                'flags'            => BugChange::FLAG_INITIAL_CHANGE,
                'comment'          => $this->comment,
                'fileAttachment'   => $this->fileAttachment,
                'status_change_id' => null,
            ]);

            $this->linkAttachedCrashReports();
            $this->linkAttachedCrashGroups();

            $tx->commit();
            return true;
        } catch (\Throwable $e) {
            $tx->rollBack();
            $this->addError('description', 'Could not open bug: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Apply a comment / status change to an already-opened bug.
     * Records every diff against the current attributes into a
     * `bug_status_change` row, updates the bug row, and writes the
     * `bug_change` audit row that links comment + status diff + attachment.
     *
     * @param array<string,mixed> $posted Subset of the Bug AR attributes
     *                                    submitted from the comment form.
     * @return bool true on success
     */
    public function change(array $posted): bool
    {
        $now    = time();
        $userId = (int) (Yii::$app->user->id ?? 0);

        $diff = [];
        foreach (['status', 'assigned_to', 'priority', 'reproducability', 'merged_into'] as $col) {
            if (array_key_exists($col, $posted) && (string) $posted[$col] !== (string) $this->{$col}) {
                $diff[$col] = $posted[$col];
            }
        }
        // The summary can change but it lives on `bug` directly, not in the diff row.
        if (isset($posted['summary']) && (string) $posted['summary'] !== (string) $this->summary) {
            $this->summary = $posted['summary'];
        }

        $tx = Yii::$app->db->beginTransaction();
        try {
            $statusChangeId = null;
            if (!empty($diff)) {
                $statusChange = new BugStatusChange();
                foreach ($diff as $col => $val) {
                    $statusChange->{$col} = $val === '' ? null : $val;
                    $this->{$col} = $val;
                }
                if (!$statusChange->save()) {
                    throw new \RuntimeException('Could not save status diff: ' . json_encode($statusChange->errors));
                }
                $statusChangeId = (int) $statusChange->id;

                if (isset($diff['status']) && (int) $diff['status'] >= self::STATUS_OPEN_MAX) {
                    $this->date_closed = $now;
                }
            }

            $this->date_last_modified = $now;
            if (!$this->save()) {
                throw new \RuntimeException('Could not save bug row: ' . json_encode($this->errors));
            }

            $this->writeChange([
                'flags'            => 0,
                'comment'          => $this->comment,
                'fileAttachment'   => $this->fileAttachment,
                'status_change_id' => $statusChangeId,
                'user_id'          => $userId,
                'timestamp'        => $now,
            ]);

            $tx->commit();
            return true;
        } catch (\Throwable $e) {
            $tx->rollBack();
            $this->addError('comment', 'Could not save change: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Insert a single BugChange row, plus optional Comment + Attachment
     * children. Shared between open() and change().
     *
     * @param array{flags:int,comment?:?string,fileAttachment?:?UploadedFile,status_change_id?:?int,user_id?:int,timestamp?:int} $opts
     */
    protected function writeChange(array $opts): BugChange
    {
        $now      = $opts['timestamp'] ?? time();
        $userId   = $opts['user_id']   ?? (int) (Yii::$app->user->id ?? 0);
        $commentId = null;

        if (!empty($opts['comment'])) {
            $comment = new BugComment();
            $comment->text = $opts['comment'];
            if (!$comment->save()) {
                throw new \RuntimeException('Could not save bug comment.');
            }
            $commentId = (int) $comment->id;
        }

        $change = new BugChange();
        $change->bug_id           = (int) $this->id;
        $change->timestamp        = $now;
        $change->user_id          = $userId;
        $change->flags            = (int) $opts['flags'];
        $change->status_change_id = $opts['status_change_id'] ?? null;
        $change->comment_id       = $commentId;
        if (!$change->save()) {
            throw new \RuntimeException('Could not save bug change row.');
        }

        if (!empty($opts['fileAttachment']) && $opts['fileAttachment'] instanceof UploadedFile) {
            $att = new BugAttachment();
            $att->bug_change_id  = (int) $change->id;
            $att->fileAttachment = $opts['fileAttachment'];
            if (!$att->save()) {
                throw new \RuntimeException('Could not save bug attachment.');
            }
            $att->persistAttachment();
        }

        return $change;
    }

    protected function linkAttachedCrashReports(): void
    {
        if (empty($this->crashreports)) return;
        $ids = is_array($this->crashreports) ? $this->crashreports : preg_split('/[,\s]+/', (string) $this->crashreports);
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) continue;
            $exists = BugCrashreport::find()
                ->where(['bug_id' => $this->id, 'crashreport_id' => $id])
                ->exists();
            if ($exists) continue;
            $link = new BugCrashreport();
            $link->bug_id         = (int) $this->id;
            $link->crashreport_id = $id;
            $link->save(false);
        }
    }

    protected function linkAttachedCrashGroups(): void
    {
        if (empty($this->crashgroups)) return;
        $ids = is_array($this->crashgroups) ? $this->crashgroups : preg_split('/[,\s]+/', (string) $this->crashgroups);
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) continue;
            $exists = BugCrashgroup::find()
                ->where(['bug_id' => $this->id, 'crashgroup_id' => $id])
                ->exists();
            if ($exists) continue;
            $link = new BugCrashgroup();
            $link->bug_id        = (int) $this->id;
            $link->crashgroup_id = $id;
            $link->save(false);
        }
    }

    protected function buildSummaryLine(Crashreport $report): string
    {
        $parts = [];
        if (!empty($report->exception_type))   $parts[] = $report->exception_type;
        if (!empty($report->exceptionmodule))  $parts[] = basename($report->exceptionmodule);
        if (empty($parts)) {
            $parts[] = 'Crash report #' . $report->id;
        }
        return mb_substr(implode(' in ', $parts), 0, 256);
    }
}
