<?php

declare(strict_types=1);

namespace Pitmaster\Bench;

final class BenchmarkCase
{
    /**
     * @param list<string> $groups
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $groups,
        public readonly int $defaultRuns,
        public readonly int $defaultWarmups,
        public readonly array $metadata,
        private readonly ?\Closure $setUp,
        private readonly ?\Closure $prepareIteration,
        private readonly \Closure $run,
        private readonly ?\Closure $cleanupIteration,
        private readonly ?\Closure $tearDown,
    ) {
    }

    /**
     * @param list<string> $groups
     * @param array<string, mixed> $metadata
     */
    public static function define(
        string $name,
        string $description,
        array $groups,
        int $defaultRuns,
        int $defaultWarmups,
        array $metadata,
        ?callable $setUp,
        ?callable $prepareIteration,
        callable $run,
        ?callable $cleanupIteration = null,
        ?callable $tearDown = null,
    ): self {
        return new self(
            $name,
            $description,
            $groups,
            $defaultRuns,
            $defaultWarmups,
            $metadata,
            $setUp !== null ? \Closure::fromCallable($setUp) : null,
            $prepareIteration !== null ? \Closure::fromCallable($prepareIteration) : null,
            \Closure::fromCallable($run),
            $cleanupIteration !== null ? \Closure::fromCallable($cleanupIteration) : null,
            $tearDown !== null ? \Closure::fromCallable($tearDown) : null,
        );
    }

    public function belongsTo(string $suite): bool
    {
        return $suite === 'all' || in_array($suite, $this->groups, true);
    }

    public function setUp(BenchmarkRuntime $runtime): mixed
    {
        return $this->setUp !== null ? ($this->setUp)($runtime) : null;
    }

    public function prepareIteration(BenchmarkRuntime $runtime, mixed $suiteContext, int $iteration): mixed
    {
        return $this->prepareIteration !== null
            ? ($this->prepareIteration)($runtime, $suiteContext, $iteration)
            : null;
    }

    public function run(BenchmarkRuntime $runtime, mixed $suiteContext, mixed $iterationContext): void
    {
        ($this->run)($runtime, $suiteContext, $iterationContext);
    }

    public function cleanupIteration(BenchmarkRuntime $runtime, mixed $suiteContext, mixed $iterationContext): void
    {
        if ($this->cleanupIteration !== null) {
            ($this->cleanupIteration)($runtime, $suiteContext, $iterationContext);
        }
    }

    public function tearDown(BenchmarkRuntime $runtime, mixed $suiteContext): void
    {
        if ($this->tearDown !== null) {
            ($this->tearDown)($runtime, $suiteContext);
        }
    }
}
