<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;
use Pitmaster\Stash\Stash;
use Pitmaster\Merge\Rerere;
use Pitmaster\Tests\Support\Workspace;

function command(string $command, string $dir): string
{
    $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $dir);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start oracle command');
    }
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException($error);
    }
    return $output;
}

$native = $argv[1] === 'pitmaster';
$case = $argv[2];
$work = Workspace::createDirectory('open-issue-scenario-');
try {
    command('cp -R ' . escapeshellarg(getcwd()) . '/. ' . escapeshellarg($work), getcwd());
    putenv('GIT_AUTHOR_DATE=2024-01-02T00:00:00+0000');
    putenv('GIT_COMMITTER_DATE=2024-01-02T00:00:00+0000');
    $repo = Pitmaster::open($work);
    $git = static fn (string $args, ?string $dir = null): string => command('git ' . $args, $dir ?? $work);
    $stashFor = static function (string $dir): Stash {
        $handle = Pitmaster::open($dir);
        return new Stash($handle->objectDatabase(), $handle->refDatabase(), $handle->gitDir(), $dir);
    };
    switch ($case) {
        case 'diff':
            $fresh = $work . '/diff-fixture';
            $git('init --initial-branch=main ' . escapeshellarg($fresh));
            $git('config user.name Test', $fresh);
            $git('config user.email test@example.com', $fresh);
            $git('config core.filemode true', $fresh);
            file_put_contents($fresh . '/one.txt', "one\n");
            file_put_contents($fresh . '/two.txt', "two\n");
            $git('add .', $fresh);
            $format = static fn (array $entries): string => implode('', array_map(static fn ($entry): string => $entry->format(), $entries));
            $out[] = $native ? $format(Pitmaster::open($fresh)->diffStaged()) : $git('diff --cached --no-color', $fresh);
            $out[] = $native ? $format(Pitmaster::open($fresh)->diffStaged('one.txt')) : $git('diff --cached --no-color -- one.txt', $fresh);
            $git('commit -m base', $fresh);
            chmod($fresh . '/one.txt', 0755);
            unlink($fresh . '/two.txt');
            file_put_contents($fresh . '/binary.dat', "\0\1");
            file_put_contents($fresh . '/new.txt', "new\n");
            file_put_contents($fresh . '/empty.txt', '');
            $git('add -A', $fresh);
            $out[] = $native ? $format(Pitmaster::open($fresh)->diffStaged()) : $git('diff --cached --no-color', $fresh);
            $git('reset --hard HEAD', $fresh);
            rename($fresh . '/one.txt', $fresh . '/renamed.txt');
            $git('add -A', $fresh);
            $out[] = $native ? $format(Pitmaster::open($fresh)->diffStaged()) : $git('diff --cached --no-color', $fresh);
            break;
        case 'log':
            foreach (['docs/x.txt', 'docs'] as $path) {
                $out[] = $native
                    ? array_map(static fn ($commit) => $commit->id->hex, $repo->logPath($path, 1))
                    : explode("\n", trim($git('log -n 1 --format=%H -- ' . escapeshellarg($path))));
            }
            break;
        case 'merge':
            file_put_contents($work . '/a.txt', "local changes\n");
            $native ? $repo->merge('old') : $git('merge old');
            $out = [$git('rev-parse HEAD'), $git('status --porcelain'), file_get_contents($work . '/a.txt')];
            break;
        case 'index':
            file_put_contents($work . '/a.txt', "staged changes\n");
            $native ? $repo->add('a.txt') : $git('add a.txt');
            $out = [$git('ls-files --stage'), $git('status --porcelain')];
            break;
        case 'stash':
            $stash = $stashFor($work);
            foreach (['one', 'two', 'three'] as $message) {
                file_put_contents($work . '/a.txt', $message . "\n");
                $native ? $stash->push($message) : $git('stash push -m ' . $message);
            }
            $native ? $stash->drop(1) : $git('stash drop stash@{1}');
            $afterDrop = $git('stash list --format=%gs');
            $native ? $stash->pop() : $git('stash pop');
            $out = [$afterDrop, $git('stash list --format=%gs'), file_get_contents($work . '/a.txt')];
            break;
        case 'linked':
            $linked = $work . '/linked';
            if ($native) {
                $repo->addWorktree($linked, 'old');
            } else {
                $git('worktree add ' . escapeshellarg($linked) . ' old');
            }
            file_put_contents($linked . '/a.txt', "linked stash\n");
            $native ? $stashFor($linked)->push('linked') : $git('stash push -m linked', $linked);
            $main = $native ? Pitmaster::open($linked)->worktrees()[0]->path : substr(explode("\n", $git('worktree list --porcelain', $linked))[0], 9);
            $out = [str_replace($work, 'REPO', $main), $git('stash list --format=%gs'), $git('log -n 1 --format=%an refs/stash')];
            break;
        case 'hooks':
            $linked = $work . '/linked';
            $git('worktree add ' . escapeshellarg($linked) . ' old');
            putenv('PITMASTER_HOOK_TEST=from-parent');
            foreach (['common', 'relative', 'absolute'] as $mode) {
                $hooks = $mode === 'common' ? $work . '/.git/hooks' : $work . '/custom-hooks';
                if (!is_dir($hooks)) {
                    mkdir($hooks);
                }
                file_put_contents($hooks . '/post-commit', "#!/bin/sh\nprintf '%s\\n' \"\$PITMASTER_HOOK_TEST\" > hook-result\n");
                chmod($hooks . '/post-commit', 0755);
                if ($mode !== 'common') {
                    $git('config core.hooksPath ' . escapeshellarg($mode === 'relative' ? '../custom-hooks' : $hooks));
                }
                file_put_contents($linked . '/a.txt', $mode . "\n");
                if ($native) {
                    $handle = Pitmaster::open($linked);
                    $handle->add('a.txt');
                    $handle->commit($mode);
                } else {
                    $git('add a.txt', $linked);
                    $git('commit -m ' . $mode, $linked);
                }
                $out[] = file_get_contents($linked . '/hook-result');
            }
            break;
        case 'rerere':
            foreach (['true', 'yes', 'on', '1', 'false', 'off', '0'] as $value) {
                $git('config rerere.enabled ' . $value);
                $out[] = $native ? (new Rerere($repo->gitDir()))->isEnabled() : trim($git('config --bool rerere.enabled')) === 'true';
            }
            break;
        case 'init':
            $fresh = $work . '/fresh';
            $native ? Pitmaster::init($fresh) : $git('init --initial-branch=main ' . escapeshellarg($fresh));
            $git('config user.name Test', $fresh);
            $git('config user.email test@example.com', $fresh);
            if (!$native) {
                $git('config core.logallrefupdates true', $fresh);
            }
            file_put_contents($fresh . '/a.txt', "a\n");
            if ($native) {
                $handle = Pitmaster::open($fresh);
                $handle->add('a.txt');
                $handle->commit('initial');
            } else {
                $git('add a.txt', $fresh);
                $git('commit -m initial', $fresh);
            }
            $out = [$git('reflog --format=%gs', $fresh)];
            break;
        default:
            throw new RuntimeException('Unknown regression case: ' . $case);
    }
    echo json_encode($out, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    Workspace::remove($work);
}
