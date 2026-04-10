<?php

declare(strict_types=1);

namespace Pitmaster\Bench;

final class BenchmarkRuntime
{
    private int $workspaceCounter = 0;

    public function __construct(
        public readonly string $root,
        public readonly FixtureBuilder $fixtures,
    ) {
    }

    public function workspaceRoot(): string
    {
        return $this->root . '/bench/workspaces';
    }

    public function freshWorkspace(string $label): string
    {
        BenchmarkFilesystem::ensureDirectory($this->workspaceRoot());
        $path = sprintf(
            '%s/%s-%d-%d',
            $this->workspaceRoot(),
            preg_replace('/[^A-Za-z0-9._-]+/', '-', $label) ?: 'run',
            time(),
            ++$this->workspaceCounter,
        );

        BenchmarkFilesystem::removeDirectory($path);
        BenchmarkFilesystem::ensureDirectory($path);

        return $path;
    }
}
