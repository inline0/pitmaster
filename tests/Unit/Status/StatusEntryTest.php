<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Status;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Status\FileStatus;
use Pitmaster\Status\StatusEntry;

final class StatusEntryTest extends TestCase
{
    #[Test]
    public function testShortFormatUntracked(): void
    {
        $entry = new StatusEntry('newfile.txt', FileStatus::Untracked, FileStatus::Untracked);

        $this->assertSame('?? newfile.txt', $entry->shortFormat());
    }

    #[Test]
    public function testShortFormatModifiedInWorktree(): void
    {
        $entry = new StatusEntry('changed.txt', FileStatus::Unmodified, FileStatus::Modified);

        $this->assertSame(' M changed.txt', $entry->shortFormat());
    }

    #[Test]
    public function testShortFormatAddedToIndex(): void
    {
        $entry = new StatusEntry('staged.txt', FileStatus::Added, FileStatus::Unmodified);

        $this->assertSame('A  staged.txt', $entry->shortFormat());
    }

    #[Test]
    public function testShortFormatDeletedFromIndex(): void
    {
        $entry = new StatusEntry('removed.txt', FileStatus::Deleted, FileStatus::Unmodified);

        $this->assertSame('D  removed.txt', $entry->shortFormat());
    }

    #[Test]
    public function testShortFormatModifiedInBoth(): void
    {
        $entry = new StatusEntry('both.txt', FileStatus::Modified, FileStatus::Modified);

        $this->assertSame('MM both.txt', $entry->shortFormat());
    }

    #[Test]
    public function testShortFormatIgnored(): void
    {
        $entry = new StatusEntry('ignored.log', FileStatus::Ignored, FileStatus::Ignored);

        $this->assertSame('!! ignored.log', $entry->shortFormat());
    }

    #[Test]
    public function testReadonlyProperties(): void
    {
        $entry = new StatusEntry('test.txt', FileStatus::Added, FileStatus::Modified);

        $this->assertSame('test.txt', $entry->path);
        $this->assertSame(FileStatus::Added, $entry->index);
        $this->assertSame(FileStatus::Modified, $entry->worktree);
    }
}
