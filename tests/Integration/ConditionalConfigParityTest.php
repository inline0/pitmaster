<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use Pitmaster\Pitmaster;
use Pitmaster\Tests\Support\Workspace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConditionalConfigParityTest extends TestCase
{
    private string $root;
    private string $repo;

    protected function setUp(): void
    {
        $version = $this->git((string) getcwd(), ['--version']);
        if (preg_match('/git version (\d+\.\d+)/', $version, $match) !== 1 || version_compare($match[1], '2.56', '<')) {
            self::markTestSkipped('The worktree conditional include oracle requires Git 2.56 or newer');
        }
        $this->root = (string) realpath(Workspace::createDirectory('include-[meta]-'));
        $this->repo = $this->root . '/repo';
        mkdir($this->repo);
        $this->git($this->repo, ['init', '-b', 'main']);
        file_put_contents($this->repo . '/file.txt', "base\n");
        $this->git($this->repo, ['add', 'file.txt']);
        $this->git($this->repo, ['commit', '-m', 'base']);
        file_put_contents($this->root . '/matched.inc', "[demo]\nvalue = matched\n");
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            Workspace::remove($this->root);
        }
    }

    /** @return iterable<string, array{string, bool}> */
    public static function conditions(): iterable
    {
        yield 'worktree exact' => ['worktree:{WORKTREE}', true];
        yield 'exact directory slash needs descendants' => ['worktree:{WORKTREE}/', false];
        yield 'parent directory' => ['worktree:{ROOT}/', true];
        yield 'implicit globstar' => ['worktree:repo', true];
        yield 'leading globstar' => ['worktree:**/repo', true];
        yield 'middle globstar zero components' => ['worktree:{ROOT}/**/repo', true];
        yield 'single star' => ['worktree:{ROOT}/*', true];
        yield 'question mark' => ['worktree:{ROOT}/?epo', true];
        yield 'character class' => ['worktree:{ROOT}/[rs]epo', true];
        yield 'negated character class' => ['worktree:{ROOT}/[!x]epo', true];
        yield 'star does not cross directories' => ['worktree:{ROOT}/*/repo', false];
        yield 'relative with literal metacharacter prefix' => ['worktree:./repo', true];
        yield 'case sensitive' => ['worktree:./REPO', false];
        yield 'case insensitive' => ['worktree/i:./REPO', true];
        yield 'no match' => ['worktree:absent', false];
        yield 'gitdir exact' => ['gitdir:{GITDIR}', true];
        yield 'gitdir parent' => ['gitdir:{ROOT}/', true];
        yield 'gitdir globstar' => ['gitdir:**/.git', true];
        yield 'gitdir relative' => ['gitdir:./repo/.git', true];
        yield 'gitdir case insensitive' => ['gitdir/i:./REPO/.GIT', true];
        yield 'gitdir false' => ['gitdir:./absent/.git', false];
        yield 'branch exact' => ['onbranch:main', true];
        yield 'branch wildcard' => ['onbranch:ma?n', true];
        yield 'branch false' => ['onbranch:topic/', false];
        yield 'unknown condition' => ['unknown:anything', false];
    }

    #[DataProvider('conditions')]
    public function testConditionalIncludesMatchGit(string $condition, bool $matches): void
    {
        $condition = strtr($condition, [
            '{ROOT}' => self::escapeGlob($this->root),
            '{WORKTREE}' => self::escapeGlob($this->repo),
            '{GITDIR}' => self::escapeGlob($this->repo . '/.git'),
        ]);
        $this->configure($this->repo . '/.git/config', $condition);
        $expected = $matches ? ["before", "matched", "after"] : ["before", "after"];
        self::assertSame(implode("\n", $expected) . "\n", $this->git($this->repo, ['config', '--includes', '--get-all', 'demo.value']));
        self::assertSame($expected, Pitmaster::open($this->repo)->config()->getAll('demo.value'));
    }

    public function testLinkedWorktreeUsesItsOwnLocationAndBranch(): void
    {
        $linked = $this->root . '/linked';
        $this->git($this->repo, ['worktree', 'add', '-b', 'topic/linked', $linked]);
        $this->configure($this->repo . '/.git/config', 'worktree:**/linked');
        self::assertSame(['before', 'matched', 'after'], Pitmaster::open($linked)->config()->getAll('demo.value'));
        self::assertSame(
            $this->git($linked, ['config', '--includes', '--get-all', 'demo.value']),
            implode("\n", Pitmaster::open($linked)->config()->getAll('demo.value')) . "\n",
        );
        file_put_contents($this->root . '/settings.conf', "[includeIf \"onbranch:topic/\"]\npath = matched.inc\n");
        self::assertSame(['matched'], Pitmaster::open($linked)->config()->getAll('demo.value'));
        self::assertSame("matched\n", $this->git($linked, ['config', '--includes', '--get-all', 'demo.value']));
    }

    public function testBareRepositoryHasNoWorktreeCondition(): void
    {
        $bare = $this->root . '/bare.git';
        $this->git($this->root, ['init', '--bare', $bare]);
        $this->configure($bare . '/config', 'worktree:**');
        self::assertSame(['before', 'after'], Pitmaster::open($bare)->config()->getAll('demo.value'));
        self::assertSame("before\nafter\n", $this->git($bare, ['config', '--includes', '--get-all', 'demo.value']));
    }

    public function testSymlinkSpellingDoesNotMatchWorktreeCondition(): void
    {
        $alias = $this->root . '/alias';
        symlink($this->repo, $alias);
        $this->configure($this->repo . '/.git/config', 'worktree:**/alias');
        self::assertSame(['before', 'after'], Pitmaster::open($alias)->config()->getAll('demo.value'));
        self::assertSame("before\nafter\n", $this->git($alias, ['config', '--includes', '--get-all', 'demo.value']));
    }

    public function testDetachedHeadDoesNotMatchOnbranch(): void
    {
        $this->git($this->repo, ['checkout', '--detach', 'HEAD']);
        $this->configure($this->repo . '/.git/config', 'onbranch:*');
        self::assertSame(['before', 'after'], Pitmaster::open($this->repo)->config()->getAll('demo.value'));
        self::assertSame("before\nafter\n", $this->git($this->repo, ['config', '--includes', '--get-all', 'demo.value']));
    }

    private function configure(string $config, string $condition): void
    {
        file_put_contents($config, "\n[include]\npath = \"" . $this->root . "/settings.conf\"\n", FILE_APPEND);
        file_put_contents(
            $this->root . '/settings.conf',
            "[demo]\nvalue = before\n[includeIf \"" . addcslashes($condition, "\\\"") . "\"]\n"
                . "path = matched.inc\n[demo]\nvalue = after\n",
        );
    }

    private static function escapeGlob(string $path): string
    {
        return addcslashes($path, '*?[\\');
    }

    /** @param list<string> $arguments */
    private function git(string $directory, array $arguments): string
    {
        $process = proc_open(
            ['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.com', ...$arguments],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $directory,
            array_merge(getenv(), ['GIT_CONFIG_NOSYSTEM' => '1', 'GIT_CONFIG_GLOBAL' => '/dev/null']),
        );
        self::assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), (string) $stderr);
        return (string) $stdout;
    }
}
