<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Object\ObjectId;
use Pitmaster\Pitmaster;
use Pitmaster\Ref\Notes;
use Pitmaster\Repository;

/**
 * Test Ref\Notes against real git notes.
 */
final class NotesTest extends TestCase
{
    private string $tmpDir;
    private Repository $repo;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init');
        $this->git('config user.email test@pitmaster.dev');
        $this->git('config user.name "Test User"');

        // Create a commit to attach notes to
        $this->writeFile('a.txt', "content\n");
        $this->git('add a.txt');
        $this->git('commit -m "Initial commit"');

        $this->repo = Pitmaster::open($this->tmpDir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function setAndGetNote(): void
    {
        $commitId = $this->repo->head()->id;
        $notes = new Notes(
            $this->repo->objectDatabase(),
            $this->repo->refDatabase(),
        );

        $notes->set($commitId, 'This is a note');
        $result = $notes->get($commitId);

        $this->assertSame('This is a note', $result);
    }

    #[Test]
    public function noteContentMatchesGitNotesShow(): void
    {
        $commitId = $this->repo->head()->id;
        $notes = new Notes(
            $this->repo->objectDatabase(),
            $this->repo->refDatabase(),
        );

        $notes->set($commitId, 'Test note for git');

        // git notes show should return the same content
        $gitNote = trim($this->git('notes show ' . $commitId->hex));
        $this->assertSame('Test note for git', $gitNote);
    }

    #[Test]
    public function removeNote(): void
    {
        $commitId = $this->repo->head()->id;
        $notes = new Notes(
            $this->repo->objectDatabase(),
            $this->repo->refDatabase(),
        );

        $notes->set($commitId, 'To be removed');
        $this->assertNotNull($notes->get($commitId));

        $notes->remove($commitId);
        $this->assertNull($notes->get($commitId));
    }

    #[Test]
    public function listAllNotes(): void
    {
        // Create a second commit
        $this->writeFile('b.txt', "more\n");
        $this->git('add b.txt');
        $this->git('commit -m "Second commit"');
        $this->repo = Pitmaster::open($this->tmpDir);

        $log = $this->repo->log(10);
        $notes = new Notes(
            $this->repo->objectDatabase(),
            $this->repo->refDatabase(),
        );

        $notes->set($log[0]->id, 'Note on second');
        $notes->set($log[1]->id, 'Note on first');

        $all = $notes->listAll();
        $this->assertCount(2, $all);
        $this->assertSame('Note on second', $all[$log[0]->id->hex]);
        $this->assertSame('Note on first', $all[$log[1]->id->hex]);
    }

    #[Test]
    public function getNonExistentNoteReturnsNull(): void
    {
        $commitId = $this->repo->head()->id;
        $notes = new Notes(
            $this->repo->objectDatabase(),
            $this->repo->refDatabase(),
        );

        $this->assertNull($notes->get($commitId));
    }

    private function git(string $cmd): string
    {
        return shell_exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($this->tmpDir), $cmd)) ?? '';
    }

    private function writeFile(string $path, string $content): void
    {
        $full = $this->tmpDir . '/' . $path;
        $dir = dirname($full);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($full, $content);
    }
}
