<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Config\GitAttributes;
use Pitmaster\Pitmaster;
use Pitmaster\Protocol\Bundle;
use Pitmaster\Protocol\ShallowClone;
use Pitmaster\Repository;

final class ParityCoverageTest extends TestCase
{
    private string $tmpDir;
    private Repository $repo;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-parity-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init');
        $this->git('config user.email test@pitmaster.dev');
        $this->git('config user.name "Test User"');
        $this->repo = Pitmaster::open($this->tmpDir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function lightweightAndAnnotatedTagsMatchGit(): void
    {
        $this->writeFile('a.txt', "content\n");
        $this->repo->add('a.txt');
        $this->repo->commit("Initial\n");

        $this->repo->createLightweightTag('v1.0');
        $annotatedId = $this->repo->createTag('v1.1', "Release 1.1\n");

        $gitTags = array_values(array_filter(explode("\n", trim($this->git('tag --list --sort=refname')))));
        $pmTags = $this->repo->tags();

        $this->assertSame(['v1.0', 'v1.1'], $pmTags);
        $this->assertSame($gitTags, $pmTags);
        $this->assertSame($this->repo->head()->id->hex, trim($this->git('rev-parse refs/tags/v1.0')));
        $this->assertSame($annotatedId->hex, trim($this->git('rev-parse refs/tags/v1.1')));

        $this->repo->deleteTag('v1.0');
        $this->repo->deleteTag('v1.1');

        $this->assertSame([], $this->repo->tags());
        $this->assertSame('', trim($this->git('tag --list')));
    }

    #[Test]
    public function deleteBranchMatchesGitAndProtectsCheckedOutBranch(): void
    {
        $this->writeFile('a.txt', "content\n");
        $this->repo->add('a.txt');
        $this->repo->commit("Initial\n");

        $this->repo->createBranch('feature');
        $this->assertContains('feature', $this->repo->branches());

        $this->repo->deleteBranch('feature');

        $this->assertSame(['main'], $this->repo->branches());
        $this->assertSame('', trim($this->git('branch --list feature')));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot delete checked out branch: main');
        $this->repo->deleteBranch('main');
    }

    #[Test]
    public function statusPorcelainV2MatchesGitForBasicStates(): void
    {
        $this->writeFile('staged.txt', "base staged\n");
        $this->writeFile('modified.txt', "base modified\n");
        $this->repo->add('staged.txt', 'modified.txt');
        $this->repo->commit("Initial\n");

        $this->writeFile('staged.txt', "new staged\n");
        $this->repo->add('staged.txt');

        $this->writeFile('modified.txt', "new modified\n");
        $this->writeFile('untracked.txt', "untracked\n");

        $gitStatus = $this->git('status --porcelain=v2');
        $pmStatus = $this->repo->statusPorcelainV2();

        $this->assertSame($gitStatus, $pmStatus);
    }

    #[Test]
    public function statusPorcelainV2MatchesGitForStagedRename(): void
    {
        $this->writeFile('old.txt', "rename me\n");
        $this->repo->add('old.txt');
        $this->repo->commit("Initial\n");

        $this->git('mv old.txt new.txt');

        $this->assertSame(
            $this->git('status --porcelain=v2'),
            $this->repo->statusPorcelainV2(),
        );
    }

    #[Test]
    public function gitAttributesParserMatchesGitCheckAttr(): void
    {
        $this->writeFile('.gitattributes', implode("\n", [
            '*.txt text eol=lf',
            '*.md diff=markdown',
            'docs/* custom',
            '*.dat -diff',
            '',
        ]));
        $this->writeFile('readme.txt', "txt\n");
        $this->writeFile('guide.md', "md\n");
        $this->writeFile('docs/file.bin', "bin\n");
        $this->writeFile('archive.dat', "dat\n");

        $attrs = GitAttributes::forRepo($this->tmpDir);

        $this->assertSame(
            $this->gitAttributesFor('readme.txt', ['text', 'eol']),
            [
                'text' => true,
                'eol' => 'lf',
            ],
        );
        $this->assertSame($attrs->getAttributes('readme.txt'), [
            'text' => true,
            'eol' => 'lf',
        ]);

        $this->assertSame($this->gitAttributesFor('guide.md', ['diff']), [
            'diff' => 'markdown',
        ]);
        $this->assertSame($attrs->getAttributes('guide.md'), [
            'diff' => 'markdown',
        ]);

        $this->assertSame($this->gitAttributesFor('docs/file.bin', ['custom']), [
            'custom' => true,
        ]);
        $this->assertSame($attrs->getAttributes('docs/file.bin'), [
            'custom' => true,
        ]);

        $this->assertSame($this->gitAttributesFor('archive.dat', ['diff']), [
            'diff' => false,
        ]);
        $this->assertSame($attrs->getAttributes('archive.dat'), [
            'diff' => false,
        ]);
    }

    #[Test]
    public function bundleRoundTripsWithGit(): void
    {
        $this->writeFile('a.txt', "one\n");
        $this->repo->add('a.txt');
        $this->repo->commit("Initial\n");
        $this->repo->createBranch('feature');

        $bundlePath = $this->tmpDir . '/source.bundle';
        $copyPath = $this->tmpDir . '/copy.bundle';

        $this->git('bundle create ' . escapeshellarg($bundlePath) . ' --all');

        $bundle = Bundle::open($bundlePath);
        $this->assertArrayHasKey('refs/heads/main', $bundle->refs());
        $this->assertArrayHasKey('refs/heads/feature', $bundle->refs());
        $this->assertStringStartsWith('PACK', $bundle->packData());

        $bundle->writeTo($copyPath);

        $verifyOutput = $this->git('bundle verify ' . escapeshellarg($copyPath));
        $this->assertStringContainsString('The bundle contains these', $verifyOutput);
        $this->assertStringContainsString('refs/heads/main', $verifyOutput);
    }

    #[Test]
    public function shallowFileWrittenByPitmasterIsAcceptedByGit(): void
    {
        $this->writeFile('a.txt', "one\n");
        $this->repo->add('a.txt');
        $this->repo->commit("First\n");
        $this->writeFile('a.txt', "two\n");
        $this->repo->add('a.txt');
        $head = $this->repo->commit("Second\n");

        ShallowClone::writeShallow($this->tmpDir . '/.git', [$head]);

        $this->assertSame("true\n", $this->git('rev-parse --is-shallow-repository'));

        $read = ShallowClone::readShallow($this->tmpDir . '/.git');
        $this->assertCount(1, $read);
        $this->assertSame($head->hex, $read[0]->hex);
        $this->assertSame($head->hex . "\n", file_get_contents($this->tmpDir . '/.git/shallow'));
    }

    /**
     * @param array<int, string> $attributes
     * @return array<string, string|bool>
     */
    private function gitAttributesFor(string $path, array $attributes): array
    {
        $result = [];
        $command = 'check-attr ' . implode(' ', array_map('escapeshellarg', $attributes)) . ' -- ' . escapeshellarg($path);
        $output = trim($this->git($command));

        foreach (explode("\n", $output) as $line) {
            if ($line === '') {
                continue;
            }

            $parts = explode(': ', $line);

            if (count($parts) !== 3) {
                continue;
            }

            $attribute = $parts[1];
            $value = $parts[2];

            if ($value === 'set') {
                $result[$attribute] = true;
            } elseif ($value === 'unset') {
                $result[$attribute] = false;
            } elseif ($value !== 'unspecified') {
                $result[$attribute] = $value;
            }
        }

        return $result;
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
