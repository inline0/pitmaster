<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Oracle;

use InvalidArgumentException;
use FilesystemIterator;
use Pitmaster\Support\Json;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Discovers and loads scenarios from disk (mirrors php-browser's FixtureRepository).
 */
final readonly class ScenarioRepository
{
    public function __construct(private string $rootPath)
    {
    }

    public function get(string $name): Scenario
    {
        $path = $this->rootPath . "/scenarios/{$name}/scenario.json";

        if (!is_file($path)) {
            throw new InvalidArgumentException("Unknown scenario [{$name}] at {$path}");
        }

        return new Scenario($name, $this->rootPath, Json::decodeFile($path));
    }

    /**
     * Discover all scenarios.
     *
     * @return array<int, Scenario>
     */
    public function all(): array
    {
        $scenariosRoot = $this->rootPath . '/scenarios';

        if (!is_dir($scenariosRoot)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scenariosRoot, FilesystemIterator::SKIP_DOTS),
        );
        $scenarios = [];

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->getFilename() !== 'scenario.json') {
                continue;
            }

            $path = $fileInfo->getPathname();
            $name = substr($path, strlen($scenariosRoot) + 1, -strlen('/scenario.json'));

            if (!is_string($name) || $name === '') {
                continue;
            }

            $scenarios[] = new Scenario($name, $this->rootPath, Json::decodeFile($path));
        }

        usort(
            $scenarios,
            static fn (Scenario $left, Scenario $right): int => strcmp($left->name, $right->name),
        );

        return $scenarios;
    }

    /**
     * Discover scenarios in a specific category.
     *
     * @return array<int, Scenario>
     */
    public function category(string $category): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (Scenario $s): bool => $s->category() === $category,
        ));
    }
}
