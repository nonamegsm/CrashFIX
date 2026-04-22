<?php

namespace tests\unit\components;

use app\components\Storage;
use Codeception\Test\Unit;
use Yii;
use yii\base\InvalidArgumentException;

/**
 * Behaviour tests for the Storage component.
 *
 * These are pure-filesystem tests — no DB required. Each test runs
 * inside a temp dir under runtime/test-storage so it doesn't touch
 * real upload data.
 */
class StorageTest extends Unit
{
    private string $sandbox;
    private Storage $storage;

    protected function _before(): void
    {
        $this->sandbox = Yii::getAlias('@runtime/test-storage-' . bin2hex(random_bytes(4)));
        $this->storage = new Storage(['basePath' => $this->sandbox]);
    }

    protected function _after(): void
    {
        $this->storage->rmdirRecursive($this->sandbox);
    }

    public function testGetBaseDirCreatesIt(): void
    {
        $dir = $this->storage->getBaseDir();
        verify(is_dir($dir))->true();
        verify($dir)->equals($this->sandbox);
    }

    public function testCrashReportPathIsProjectScoped(): void
    {
        $path = $this->storage->crashReportPath(7, 42);
        verify($path)->stringContainsString(DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . '7');
        verify($path)->stringEndsWith('42.zip');
    }

    public function testBugAttachmentPathSanitisesFilename(): void
    {
        $path = $this->storage->bugAttachmentPath(1, 99, '../../etc/passwd');
        verify(basename($path))->stringStartsWith('99_');
        // No traversal characters survive sanitisation.
        verify($path)->stringNotContainsString('..');
        verify($path)->stringNotContainsString('/etc/');
    }

    public function testDebugInfoPathSanitisesNullBytes(): void
    {
        $path = $this->storage->debugInfoPath(1, 5, "evil\0name.pdb");
        verify($path)->stringNotContainsString("\0");
        verify(basename($path))->stringContainsString('5_');
    }

    public function testRejectsDoubleDotInExplicitSegment(): void
    {
        // We can't pass `..` directly (filenames are sanitised) but can
        // simulate a tampered project id of -1 which becomes "-1" — that
        // is a valid segment. The actual ".." rejection path is in
        // safeJoin and is exercised via reflection here.
        $this->expectException(InvalidArgumentException::class);
        $reflection = new \ReflectionClass(Storage::class);
        $method = $reflection->getMethod('safeJoin');
        $method->setAccessible(true);
        $method->invoke($this->storage, ['projects', '..', 'crashreports', 'foo.zip']);
    }

    public function testWriteUploadedFileIsAtomic(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'cfx_src_');
        file_put_contents($src, 'hello world');

        $dest = $this->storage->bugAttachmentPath(1, 1, 'hello.txt');
        $this->storage->writeUploadedFile($src, $dest);

        verify(is_file($dest))->true();
        verify(file_get_contents($dest))->equals('hello world');

        // No leftover .tmp shards.
        $tmps = glob(dirname($dest) . DIRECTORY_SEPARATOR . '*.tmp.*');
        verify($tmps)->empty();
    }

    public function testRmdirRecursiveIsIdempotent(): void
    {
        $this->storage->rmdirRecursive($this->sandbox . '/does-not-exist');
        // Just verify no exception thrown.
        verify(true)->true();
    }

    public function testRmdirRecursiveDeletesNestedTree(): void
    {
        $this->storage->mkdirRecursive($this->sandbox . '/a/b/c');
        file_put_contents($this->sandbox . '/a/b/c/file.txt', 'x');
        verify(is_file($this->sandbox . '/a/b/c/file.txt'))->true();

        $this->storage->rmdirRecursive($this->sandbox . '/a');
        verify(is_dir($this->sandbox . '/a'))->false();
    }
}
