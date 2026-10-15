<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use Pitmaster\Pitmaster;
use Pitmaster\Index\Index;
use Pitmaster\Index\IndexEntry;
use Pitmaster\Index\IndexWriter;
use Pitmaster\Object\Blob;
use Pitmaster\Object\ObjectId;
use Pitmaster\Tests\Support\Workspace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AddResolvedParityTest extends TestCase
{
    private string $oracle;
    private string $actual;

    protected function setUp(): void
    {
        $version = $this->git(getcwd(), ['--version']);
        if (preg_match('/git version (\d+\.\d+)/', $version, $match) !== 1 || version_compare($match[1], '2.56', '<')) {
            self::markTestSkipped('The add --resolved oracle requires Git 2.56 or newer');
        }
        $this->oracle = Workspace::createDirectory('resolved-oracle-');
        $this->actual = Workspace::createDirectory('resolved-actual-');
        foreach ([$this->oracle, $this->actual] as $directory) {
            $this->initialize($directory);
        }
    }

    private function initialize(string $directory, string $format = 'sha1'): void
    {
        $this->git($directory, ['init', '-b', 'main', '--object-format=' . $format]);
        if (!is_dir($directory . '/.git/info')) {
            mkdir($directory . '/.git/info');
        }
        file_put_contents($directory . '/unrelated.txt', "base\n");
        $this->git($directory, ['add', 'unrelated.txt']);
        $this->git($directory, ['commit', '-m', 'base']);
        file_put_contents($directory . '/unrelated.txt', "leave unstaged\n");
        $base = trim($this->git($directory, ['hash-object', '-w', '--stdin'], "base\n"));
        $ours = trim($this->git($directory, ['hash-object', '-w', '--stdin'], "ours\n"));
        $theirs = trim($this->git($directory, ['hash-object', '-w', '--stdin'], "theirs\n"));
        mkdir($directory . '/nested');
        foreach (['resolved.txt', 'nested/conflict.txt'] as $path) {
            $entries = '';
            foreach ([1 => $base, 2 => $ours, 3 => $theirs] as $stage => $id) {
                $entries .= "100644 {$id} {$stage}\t{$path}\n";
            }
            $this->git($directory, ['update-index', '--index-info'], $entries);
            file_put_contents($directory . '/' . $path, "resolved\n");
        }
    }

    protected function tearDown(): void
    {
        foreach (['oracle', 'actual'] as $property) {
            if (isset($this->{$property})) {
                Workspace::remove($this->{$property});
            }
        }
    }

    /** @return iterable<string, array{string|null, string, string, bool}> */
    public static function contents(): iterable
    {
        yield 'text' => ["resolved\n", '', '', false];
        yield 'deletion' => [null, '', '', false];
        yield 'symlink' => ['target', '', '', true];
        yield 'binary before marker' => ["binary\0data\n<<<<<<< ours\n", '', '', false];
        yield 'marker without space' => ["<<<<<<<literal\n", '', '', false];
        yield 'marker without newline' => ['=======', '', '', false];
        yield 'custom marker size' => ["<<<<<<< literal\n", '*.txt conflict-marker-size=9', '', false];
        yield 'nested unspecified overrides root' => ["=========\n", '*.txt conflict-marker-size=9', '*.txt !conflict-marker-size', false];
    }

    #[DataProvider('contents')]
    public function testResolvedStagingMatchesGit(?string $content, string $rootAttributes, string $nestedAttributes, bool $link): void
    {
        foreach ([$this->oracle, $this->actual] as $directory) {
            file_put_contents($directory . '/.gitattributes', $rootAttributes . "\n");
            file_put_contents($directory . '/nested/.gitattributes', $nestedAttributes . "\n");
            unlink($directory . '/nested/conflict.txt');
            if ($link) {
                symlink((string) $content, $directory . '/nested/conflict.txt');
            } elseif ($content !== null) {
                file_put_contents($directory . '/nested/conflict.txt', $content);
            }
        }
        $this->git($this->oracle, ['add', '--resolved']);
        Pitmaster::open($this->actual)->addResolved();
        self::assertSame($this->git($this->oracle, ['ls-files', '--stage']), $this->git($this->actual, ['ls-files', '--stage']));
        self::assertSame($this->git($this->oracle, ['diff', '--name-only']), $this->git($this->actual, ['diff', '--name-only']));
        self::assertSame("unrelated.txt\n", $this->git($this->actual, ['diff', '--name-only']));
    }

    /** @return iterable<string, array{string, string}> */
    public static function unresolvedContents(): iterable
    {
        yield 'ours' => ["<<<<<<< ours\n", ''];
        yield 'separator' => ["=======\n", ''];
        yield 'base CRLF' => ["||||||| base\r\n", ''];
        yield 'theirs' => [">>>>>>> theirs\n", ''];
        yield 'custom size' => ["=========\n", '*.txt conflict-marker-size=9'];
        yield 'boolean attribute uses default' => ["<<<<<<< ours\n", '*.txt conflict-marker-size'];
    }

    #[DataProvider('unresolvedContents')]
    public function testOneRemainingMarkerLeavesEveryIndexEntryUntouched(string $content, string $attributes): void
    {
        foreach ([$this->oracle, $this->actual] as $directory) {
            file_put_contents($directory . '/nested/conflict.txt', $content);
            file_put_contents($directory . '/.gitattributes', $attributes . "\n");
        }
        $before = file_get_contents($this->actual . '/.git/index');
        $this->git($this->oracle, ['add', '--resolved'], '', 128);
        try {
            Pitmaster::open($this->actual)->addResolved();
            self::fail('Unresolved markers must prevent staging');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('nested/conflict.txt', $error->getMessage());
        }
        self::assertSame($before, file_get_contents($this->actual . '/.git/index'));
        self::assertSame($this->git($this->oracle, ['ls-files', '--stage']), $this->git($this->actual, ['ls-files', '--stage']));
    }

    public function testLiteralPathSelectionKeepsOtherUnmergedPaths(): void
    {
        $this->git($this->oracle, ['add', '--resolved', 'resolved.txt']);
        Pitmaster::open($this->actual)->addResolved('resolved.txt');
        self::assertSame($this->git($this->oracle, ['ls-files', '--stage']), $this->git($this->actual, ['ls-files', '--stage']));
        self::assertStringContainsString('nested/conflict.txt', $this->git($this->actual, ['ls-files', '--unmerged']));
    }

    public function testRepositoryAttributesOverrideDirectoryAttributes(): void
    {
        foreach ([$this->oracle, $this->actual] as $directory) {
            file_put_contents($directory . '/nested/.gitattributes', "*.txt conflict-marker-size=11\n");
            file_put_contents($directory . '/.git/info/attributes', "*.txt conflict-marker-size=9\n");
            file_put_contents($directory . '/nested/conflict.txt', "=========\n");
        }
        $this->git($this->oracle, ['add', '--resolved'], '', 128);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('nested/conflict.txt');
        Pitmaster::open($this->actual)->addResolved();
    }

    public function testCliStagesOnlyResolvedConflicts(): void
    {
        $this->git($this->oracle, ['add', '--resolved']);
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/bin/pitmaster', 'add', '--resolved'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->actual,
        );
        self::assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $stderr);
        self::assertSame('', $stdout);
        self::assertSame($this->git($this->oracle, ['ls-files', '--stage']), $this->git($this->actual, ['ls-files', '--stage']));
    }

    public function testBareRepositoryCannotStageResolutions(): void
    {
        $bare = $this->actual . '/bare.git';
        $this->git($this->actual, ['init', '--bare', $bare]);
        $this->git($bare, ['add', '--resolved'], '', 128);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must be run in a work tree');
        Pitmaster::open($bare)->addResolved();
    }

    public function testInvalidIndexPathCannotReadOutsideTheWorktree(): void
    {
        $outside = Workspace::createFile('outside-resolved-');
        $content = bin2hex(random_bytes(32));
        file_put_contents($outside, $content);
        try {
            $index = Index::open($this->actual . '/.git/index');
            $hash = ObjectId::fromHex(trim($this->git($this->actual, ['hash-object', '-w', '--stdin'], "base\n")));
            $index->addEntry(IndexEntry::create('../' . basename($outside), $hash, stage: 1));
            IndexWriter::write($index, $this->actual . '/.git/index');
            $before = file_get_contents($this->actual . '/.git/index');
            try {
                Pitmaster::open($this->actual)->addResolved();
                self::fail('Invalid index paths must be rejected');
            } catch (\RuntimeException $error) {
                self::assertStringContainsString('Invalid path in unmerged index', $error->getMessage());
            }
            self::assertSame($before, file_get_contents($this->actual . '/.git/index'));
            $id = Blob::fromContent($content)->id->hex;
            self::assertFileDoesNotExist($this->actual . '/.git/objects/' . substr($id, 0, 2) . '/' . substr($id, 2));
        } finally {
            Workspace::remove($outside);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function objectFormats(): iterable
    {
        yield 'sha1' => ['sha1'];
        yield 'sha256' => ['sha256'];
    }

    #[DataProvider('objectFormats')]
    public function testGitCanRecreateConflictsAfterResolutionAndAnotherIndexWrite(string $format): void
    {
        foreach ([$this->oracle, $this->actual] as $directory) {
            Workspace::remove($directory);
            mkdir($directory);
            $this->initialize($directory, $format);
        }
        $this->git($this->oracle, ['add', '--resolved']);
        Pitmaster::open($this->actual)->addResolved();
        $this->git($this->oracle, ['add', 'unrelated.txt']);
        Pitmaster::open($this->actual)->add('unrelated.txt');
        self::assertSame(
            $this->git($this->oracle, ['ls-files', '--resolve-undo']),
            $this->git($this->actual, ['ls-files', '--resolve-undo']),
        );
        foreach ([$this->oracle, $this->actual] as $directory) {
            $this->git($directory, ['checkout', '-m', '--', 'resolved.txt']);
        }
        self::assertSame($this->git($this->oracle, ['ls-files', '--stage']), $this->git($this->actual, ['ls-files', '--stage']));
        self::assertSame(file_get_contents($this->oracle . '/resolved.txt'), file_get_contents($this->actual . '/resolved.txt'));
        self::assertStringContainsString('<<<<<<< ours', (string) file_get_contents($this->actual . '/resolved.txt'));
    }

    /** @param list<string> $arguments */
    private function git(string $directory, array $arguments, string $stdin = '', int $expectedExit = 0): string
    {
        $environment = array_merge(getenv(), [
            'GIT_CONFIG_NOSYSTEM' => '1',
            'GIT_CONFIG_GLOBAL' => '/dev/null',
            'GIT_AUTHOR_DATE' => '2026-10-15T10:00:00Z',
            'GIT_COMMITTER_DATE' => '2026-10-15T10:00:00Z',
        ]);
        $process = proc_open(
            ['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.com', ...$arguments],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $directory,
            $environment,
        );
        self::assertIsResource($process);
        fwrite($pipes[0], $stdin);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame($expectedExit, proc_close($process), (string) $stderr);
        return (string) $stdout;
    }
}
