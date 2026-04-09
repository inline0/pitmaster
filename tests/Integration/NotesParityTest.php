<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Ref\Notes;
use Pitmaster\Repository;

final class NotesParityTest extends TestCase
{
    private string $tmpDir;
    private Repository $repo;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-notes-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init --initial-branch=main');
        $this->git('config user.email test@pitmaster.dev');
        $this->git('config user.name "Test User"');
        file_put_contents($this->tmpDir . '/tracked.txt', "notes parity\n");
        $this->git('add tracked.txt');
        $this->git('commit -m initial');
        $this->repo = Pitmaster::open($this->tmpDir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function customNotesNamespaceMatchesGitForReadAndWrite(): void
    {
        $commitId = $this->repo->head()->id;
        $notes = new Notes($this->repo->objectDatabase(), $this->repo->refDatabase());

        $notes->set($commitId, 'Pitmaster review', 'refs/notes/review');

        $this->assertSame('Pitmaster review', trim($this->git('notes --ref=review show ' . $commitId->hex)));
        $this->assertSame(
            trim($this->git('rev-parse refs/notes/review')),
            $this->repo->refDatabase()->resolve('refs/notes/review')?->hex,
        );

        $this->git('notes --ref=qa add -m "Git note" ' . $commitId->hex);

        $this->assertSame('Git note', trim((string) $notes->get($commitId, 'refs/notes/qa')));
    }

    #[Test]
    public function notesRemainVisibleFromLinkedWorktrees(): void
    {
        $linkedDir = $this->tmpDir . '-linked';
        $this->git('worktree add -b linked ' . escapeshellarg($linkedDir));

        $commitId = $this->repo->head()->id;
        $notes = new Notes($this->repo->objectDatabase(), $this->repo->refDatabase());
        $notes->set($commitId, 'Visible everywhere');

        $linkedRepo = Pitmaster::open($linkedDir);
        $linkedNotes = new Notes($linkedRepo->objectDatabase(), $linkedRepo->refDatabase());

        $this->assertSame('Visible everywhere', $linkedNotes->get($commitId));
        $this->assertSame('Visible everywhere', trim($this->gitIn($linkedDir, 'notes show ' . $commitId->hex)));
    }

    #[Test]
    public function notesMergeMatchesGitForNonConflictingRefs(): void
    {
        $source = $this->tmpDir;
        $pitDir = $this->tmpDir . '-pit-merge';
        $gitDir = $this->tmpDir . '-git-merge';
        file_put_contents($source . '/second.txt', "more\n");
        $this->git('add second.txt');
        $this->git('commit -m second');
        $this->copyRepo($source, $pitDir);
        $this->copyRepo($source, $gitDir);

        $pitRepo = Pitmaster::open($pitDir);
        $log = $pitRepo->log(10);
        $latest = $log[0]->id;
        $initial = $log[1]->id;
        $notes = new Notes($pitRepo->objectDatabase(), $pitRepo->refDatabase());

        $notes->set($initial, 'Main note');
        $notes->set($latest, 'Review note', 'refs/notes/review');
        $notes->merge('refs/notes/review');

        $this->gitIn($gitDir, 'notes add -m "Main note" ' . $initial->hex);
        $this->gitIn($gitDir, 'notes --ref=review add -m "Review note" ' . $latest->hex);
        $this->gitIn($gitDir, 'notes merge review');

        $this->assertSame(
            $this->gitIn($gitDir, 'notes list'),
            $this->gitIn($pitDir, 'notes list'),
        );
        $this->assertSame('Main note', $notes->get($initial));
        $this->assertSame('Review note', $notes->get($latest));
        $this->assertSame('Review note', trim($this->gitIn($gitDir, 'notes show ' . $latest->hex)));
    }

    private function git(string $command): string
    {
        return $this->gitIn($this->tmpDir, $command);
    }

    private function gitIn(string $dir, string $command): string
    {
        exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command), $output, $exitCode);
        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            $this->fail("git {$command} failed:\n{$result}");
        }

        return $result . ($result === '' ? '' : "\n");
    }

    private function copyRepo(string $source, string $target): void
    {
        exec(sprintf('cp -R %s %s', escapeshellarg($source), escapeshellarg($target)), $output, $exitCode);

        if ($exitCode !== 0) {
            $this->fail("Failed to copy repository from {$source} to {$target}");
        }
    }
}
