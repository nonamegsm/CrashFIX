<?php

namespace app\models;

use Yii;
use yii\web\UploadedFile;
use app\models\Lookup;
use app\models\Processingerror;

/**
 * This is the model class for table "tbl_debuginfo".
 *
 * @property int $id
 * @property int $project_id
 * @property int $dateuploaded
 * @property int $status
 * @property string $filename
 * @property string $guid
 * @property string $md5
 * @property int $filesize
 *
 * @property string|null $format          Detected high-level format (pdb / dwarf-elf / dwarf-pe / unknown). NULL = not yet parsed by daemon.
 * @property string|null $container       Container kind: pe / elf / pdb. NULL = unknown.
 * @property string|null $architecture    CPU target: x86 / x86_64 / armv7 / aarch64 / ... NULL = unknown.
 * @property int|null    $has_source_lines 1 = source line tables present and usable, 0 = not present, NULL = unknown.
 * @property string|null $build_id_kind   Names the kind of identifier in $guid: pdb-guid-age / gnu-build-id / pe-guid-age. NULL = unknown.
 *
 * @property UploadedFile|null $fileAttachment Transient: the uploaded file
 */
class Debuginfo extends \yii\db\ActiveRecord
{
    use DebuginfoPollTrait;

    // Status codes match the seeded `lookup` table (DebugInfoStatus).
    const STATUS_WAITING             = 1;
    const STATUS_PROCESSING          = 2;
    const STATUS_READY               = 3;
    const STATUS_INVALID             = 4;
    const STATUS_UNSUPPORTED_FORMAT  = 5; // daemon recognised the file but cannot parse this format
    const STATUS_PARTIALLY_MATCHED   = 6; // some functions/lines available (e.g. .debug_info kept, .debug_line stripped)

    // Format-column values written by the daemon (see RFC-001).
    const FORMAT_PDB        = 'pdb';
    const FORMAT_DWARF_ELF  = 'dwarf-elf';
    const FORMAT_DWARF_PE   = 'dwarf-pe';
    const FORMAT_UNKNOWN    = 'unknown';

    // build_id_kind values, used only for display label prefixing.
    const BUILDID_PDB_GUID_AGE = 'pdb-guid-age';
    const BUILDID_GNU_BUILD_ID = 'gnu-build-id';
    const BUILDID_PE_GUID_AGE  = 'pe-guid-age';

    /** @var UploadedFile|null Transient upload to be persisted in afterSave. */
    public $fileAttachment;

    public static function tableName()
    {
        return 'tbl_debuginfo';
    }

    public function rules()
    {
        return [
            [['project_id'], 'required'],
            [['project_id', 'dateuploaded', 'status', 'filesize', 'has_source_lines'], 'integer'],
            [['filename'], 'string', 'max' => 512],
            [['guid'],     'string', 'max' => 48],
            [['md5'],      'string', 'max' => 32],
            [['format', 'build_id_kind'], 'string', 'max' => 32],
            [['container'],    'string', 'max' => 8],
            [['architecture'], 'string', 'max' => 16],
            // Whitelist intentionally permissive: PDB plus the common
            // DWARF carriers (.so / .exe / .dll / .debug / .elf) plus
            // the legacy aliases (.sym / .dbg). Server-side daemon does
            // the actual format detection and may still mark a file
            // STATUS_UNSUPPORTED_FORMAT regardless of extension.
            [['fileAttachment'], 'file', 'skipOnEmpty' => true,
                'extensions' => ['pdb', 'sym', 'dbg', 'so', 'exe', 'dll', 'debug', 'elf']],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'               => 'ID',
            'project_id'       => 'Project',
            'dateuploaded'     => 'Date Uploaded',
            'status'           => 'Status',
            'filename'         => 'File Name',
            // Renamed from 'Module GUID': the same column now stores
            // either a PDB GUID+Age or a GNU build-id, so the umbrella
            // label is more accurate. The format-specific name is shown
            // as a value prefix (see getBuildIdValue()).
            'guid'             => 'Build ID',
            'md5'              => 'MD5',
            'filesize'         => 'File Size',
            'format'           => 'Format',
            'container'        => 'Container',
            'architecture'     => 'Architecture',
            'has_source_lines' => 'Has source lines',
            'build_id_kind'    => 'Build ID kind',
        ];
    }

    /**
     * Human-readable status string. Falls back through three layers so
     * the view never renders an empty cell:
     *
     *   1. Lookup table (preferred - keeps the wording in one place
     *      and lets translators / admins customise the strings).
     *   2. Hard-coded const map below (so the page still works if the
     *      lookup table seed has not been re-run after the migration).
     *   3. The raw integer (last-resort sentinel).
     */
    public function getStatusStr(): string
    {
        if (Yii::$app->has('db')) {
            $name = Lookup::item('DebugInfoStatus', (int) $this->status);
            if ($name !== false && $name !== null && $name !== '') {
                return (string) $name;
            }
        }
        switch ((int) $this->status) {
            case self::STATUS_WAITING:            return 'Waiting';
            case self::STATUS_PROCESSING:         return 'Processing';
            case self::STATUS_READY:              return 'Ready';
            case self::STATUS_INVALID:            return 'Invalid';
            case self::STATUS_UNSUPPORTED_FORMAT: return 'Unsupported format';
            case self::STATUS_PARTIALLY_MATCHED:  return 'Ready (partial)';
            case self::STATUS_PENDING_DELETE:     return 'Pending Delete';
            case self::STATUS_DELETE_IN_PROGRESS: return 'Deleting';
        }
        return 'status ' . (int) $this->status;
    }

    /**
     * Returns a stable user-facing label for the format column. NULL or
     * empty values render as "detecting..." so the user sees that the
     * daemon has not yet looked at the file (vs. "Unknown" which means
     * the daemon ran and could not identify it).
     */
    public function getFormatStr(): string
    {
        if (!isset($this->format) || $this->format === null || $this->format === '') {
            return 'detecting…';
        }
        switch ($this->format) {
            case self::FORMAT_PDB:       return 'PDB';
            case self::FORMAT_DWARF_ELF: return 'DWARF (ELF)';
            case self::FORMAT_DWARF_PE:  return 'DWARF (PE)';
            case self::FORMAT_UNKNOWN:   return 'Unknown';
        }
        return (string) $this->format;
    }

    /**
     * Format-aware label for the Build ID detail row. Keeps the
     * canonical concept ("Build identifier") while letting the value
     * prefix carry the format-specific name.
     */
    public function getBuildIdLabel(): string
    {
        return 'Build identifier';
    }

    /**
     * Format-aware value for the Build ID detail row. Composes
     * "<kind-prefix>: <guid>" so the user always sees the technically
     * precise name of what the GUID column actually holds.
     */
    public function getBuildIdValue(): string
    {
        $guid = (string) ($this->guid ?? '');
        if ($guid === '' || strncmp($guid, 'tmp_', 4) === 0) {
            return 'n/a';
        }
        $prefix = null;
        $kind = isset($this->build_id_kind) ? (string) $this->build_id_kind : '';
        switch ($kind) {
            case self::BUILDID_PDB_GUID_AGE: $prefix = 'PDB GUID+Age';   break;
            case self::BUILDID_GNU_BUILD_ID: $prefix = 'GNU build-id';   break;
            case self::BUILDID_PE_GUID_AGE:  $prefix = 'PE GUID+Age';    break;
        }
        return $prefix === null ? $guid : ($prefix . ': ' . $guid);
    }

    /**
     * Yes/No/Unknown helper for the has_source_lines column.
     */
    public function getHasSourceLinesStr(): string
    {
        if (!isset($this->has_source_lines) || $this->has_source_lines === null) {
            return 'unknown';
        }
        return ((int) $this->has_source_lines) === 1 ? 'yes' : 'no';
    }

    /**
     * Errors recorded while importing / parsing this debug file (daemon / poll).
     *
     * @return \yii\db\ActiveQuery
     */
    public function getProcessingErrors()
    {
        return $this->hasMany(Processingerror::class, ['srcid' => 'id'])
            ->andOnCondition(['type' => Processingerror::TYPE_DEBUG_INFO_ERROR])
            ->orderBy(['id' => SORT_DESC]);
    }

    /**
     * Rows for a definition-list style “what we extracted” panel on the detail page.
     *
     * @return list<array{term: string, description: string}>
     */
    public function getExtractionSummaryDlItems(): array
    {
        $rows = [];
        $st = (int) $this->status;

        if ($st === self::STATUS_WAITING || $st === self::STATUS_PROCESSING) {
            $rows[] = [
                'term' => 'Importer',
                'description' => 'This upload is still queued or running. Detection results (format, container, architecture, build id, source line tables) appear here after processing finishes.',
            ];

            return $rows;
        }

        if ($st === self::STATUS_INVALID) {
            $rows[] = [
                'term' => 'Importer',
                'description' => 'This debug info record is marked invalid or pending cleanup; details below may be incomplete or outdated.',
            ];
        }

        if ($st === self::STATUS_UNSUPPORTED_FORMAT) {
            $rows[] = [
                'term' => 'Importer',
                'description' => 'The daemon inspected this file but cannot parse or use this symbol format for stack resolution. Crash dumps will not match stacks against this artifact.',
            ];
        } elseif ($st === self::STATUS_PARTIALLY_MATCHED) {
            $rows[] = [
                'term' => 'Importer',
                'description' => 'Symbols were stored with reduced fidelity (for example missing or stripped source line sections). Some frames may resolve without exact file/line information.',
            ];
        }

        $rows[] = ['term' => 'Symbol format', 'description' => $this->describeSymbolFormatForHumans()];
        $rows[] = ['term' => 'Container', 'description' => $this->describeContainerForHumans()];
        $rows[] = ['term' => 'Architecture', 'description' => $this->describeArchitectureForHumans()];
        $rows[] = ['term' => 'Source line mappings', 'description' => $this->describeSourceLinesForHumans()];
        $rows[] = ['term' => 'Build identifier', 'description' => $this->describeBuildIdentifierForHumans()];

        return $rows;
    }

    private function describeSymbolFormatForHumans(): string
    {
        $f = isset($this->format) ? (string) $this->format : '';
        if ($f === '') {
            $st = (int) $this->status;
            if ($st === self::STATUS_READY || $st === self::STATUS_PARTIALLY_MATCHED) {
                return 'Not recorded (likely imported before extended metadata was added, or the importer omitted the Format field).';
            }

            return 'Not reported yet.';
        }

        return match ($f) {
            self::FORMAT_PDB => 'Microsoft PDB — a separate symbol database, usually produced alongside a PE build and matched via GUID + Age.',
            self::FORMAT_DWARF_ELF => 'DWARF inside or next to an ELF binary — typical for Linux/Android native builds.',
            self::FORMAT_DWARF_PE => 'DWARF embedded in a Windows PE image (EXE/DLL) instead of a classic PDB sidecar.',
            self::FORMAT_UNKNOWN => 'The importer could not classify the debug bundle beyond “unknown”.',
            default => 'Reported as “' . $f . '”.',
        };
    }

    private function describeContainerForHumans(): string
    {
        $c = isset($this->container) ? strtolower(trim((string) $this->container)) : '';
        if ($c === '') {
            return 'Not reported yet — the importer did not set Container.';
        }

        return match ($c) {
            'pdb' => 'Standalone PDB — debug records live in this file, not inside an executable image.',
            'pe' => 'PE image — Windows portable executable (EXE/DLL). Debug info may live inside the image or be referenced externally.',
            'elf' => 'ELF — shared object or executable on Unix-like systems.',
            default => 'Reported container type: “' . $c . '”.',
        };
    }

    private function describeArchitectureForHumans(): string
    {
        $a = isset($this->architecture) ? strtolower(trim((string) $this->architecture)) : '';
        if ($a === '') {
            return 'Not reported yet.';
        }

        return match ($a) {
            'x86', 'i386', 'i686' => '32-bit x86 — stacks and addresses are interpreted as IA-32.',
            'x86_64', 'amd64', 'x64' => '64-bit x86 — stacks use the AMD64 ABI.',
            'aarch64', 'arm64' => '64-bit ARM (AArch64).',
            'arm', 'armv7', 'thumb', 'thumbv7' => '32-bit ARM.',
            default => 'Reported machine target: “' . $a . '”. Crash dumps must match this ISA for symbol translation.',
        };
    }

    private function describeSourceLinesForHumans(): string
    {
        if (!isset($this->has_source_lines) || $this->has_source_lines === null) {
            return 'Unknown — the importer did not report whether .debug_line / PDB line records are usable.';
        }
        if (((int) $this->has_source_lines) === 1) {
            return 'Present — the bundle contains line number programs (DWARF) or equivalent PDB line info, so resolved stacks can show source file paths and approximate lines when addr2line/PDB lookup succeeds.';
        }

        return 'Not present or stripped — you may see function names without reliable source file and line attribution.';
    }

    private function describeBuildIdentifierForHumans(): string
    {
        $raw = (string) ($this->guid ?? '');
        if ($raw === '' || strncmp($raw, 'tmp_', 4) === 0) {
            return 'No stable build id stored yet (placeholder GUID until the importer finishes, or legacy row).';
        }

        $kind = isset($this->build_id_kind) ? (string) $this->build_id_kind : '';

        if ($kind === self::BUILDID_GNU_BUILD_ID) {
            return 'GNU build-id fingerprint (hex digest embedded in the ELF note). Minidumps tie modules to this digest.'
                . "\n\nRaw value: " . $raw;
        }

        if ($kind === self::BUILDID_PDB_GUID_AGE || $kind === self::BUILDID_PE_GUID_AGE) {
            $label = $kind === self::BUILDID_PDB_GUID_AGE ? 'PDB' : 'PE';
            if (preg_match('/^([0-9a-fA-F]{32})(\d+)$/', $raw, $m)) {
                $pretty = self::formatHexGuid32($m[1]);
                $age = $m[2];

                return "{$label} modules are matched using a GUID plus an Age counter.\n\n"
                    . "• GUID (identity of the symbol stream): {$pretty}\n"
                    . "• Age (link revision counter): {$age}\n\n"
                    . 'Minidumps encode the same pair so the server can pick this upload when resolving stacks.';
            }

            return "{$label} GUID+Age could not be split automatically from the stored value.\n\nStored value: {$raw}";
        }

        return 'Opaque build key stored by the importer.' . "\n\nRaw value: " . $raw;
    }

    private static function formatHexGuid32(string $hex): string
    {
        $hex = strtolower($hex);
        if (strlen($hex) !== 32 || !ctype_xdigit($hex)) {
            return $hex;
        }

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }

    public function getProject()
    {
        return $this->hasOne(Project::class, ['id' => 'project_id']);
    }

    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if ($insert) {
            $this->dateuploaded = $this->dateuploaded ?: time();
            $this->status       = $this->status ?: self::STATUS_WAITING;

            if ($this->fileAttachment instanceof UploadedFile) {
                $this->filename = $this->filename ?: ($this->fileAttachment->baseName . '.' . $this->fileAttachment->extension);
                $this->filesize = (int) $this->fileAttachment->size;
                if (empty($this->md5) && is_readable($this->fileAttachment->tempName)) {
                    $this->md5 = md5_file($this->fileAttachment->tempName) ?: str_repeat('0', 32);
                }
            }

            if (empty($this->guid)) {
                // Best-effort fallback when daemon hasn't told us the
                // PE/PDB GUID yet. Treated as opaque so the unique index
                // on (project_id,guid) still functions.
                $this->guid = strtoupper(bin2hex(random_bytes(16)));
            }
            if (empty($this->md5)) {
                $this->md5 = str_repeat('0', 32);
            }
            if (empty($this->filename)) {
                $this->filename = 'unknown.pdb';
            }
            if (empty($this->filesize)) {
                $this->filesize = 0;
            }
        }

        return true;
    }

    public function persistAttachment(): void
    {
        if (!$this->fileAttachment instanceof UploadedFile) {
            return;
        }
        $storage = Yii::$app->storage;
        $dest = $storage->debugInfoPath((int) $this->project_id, (int) $this->id, (string) $this->filename);
        $storage->writeUploadedFile($this->fileAttachment->tempName, $dest);
    }

    public function afterDelete()
    {
        parent::afterDelete();
        if (!Yii::$app->has('storage')) {
            return;
        }
        $storage = Yii::$app->storage;
        @unlink($storage->debugInfoPath((int) $this->project_id, (int) $this->id, (string) $this->filename));
    }

    public function dumpFileAttachmentContent(): void
    {
        $storage = Yii::$app->storage;
        $path = $storage->debugInfoPath((int) $this->project_id, (int) $this->id, (string) $this->filename);
        $storage->streamDownload($path, (string) $this->filename, true);
    }

    /**
     * Soft-delete: mark the row as Invalid so the daemon will pick it up
     * for cleanup. Falls back to hard delete if the daemon never claims
     * it.
     */
    public function markForDeletion(): bool
    {
        $this->status = self::STATUS_INVALID;
        return $this->save(false, ['status']);
    }

    /**
     * True when a record with the same (project_id, guid) already exists.
     * Used by the external upload endpoint to avoid duplicate uploads.
     */
    public function checkFileGUIDExists(): bool
    {
        if (empty($this->guid) || empty($this->project_id)) {
            return false;
        }
        return self::find()
            ->where(['project_id' => $this->project_id, 'guid' => $this->guid])
            ->andWhere(['<>', 'status', self::STATUS_INVALID])
            ->exists();
    }
}
