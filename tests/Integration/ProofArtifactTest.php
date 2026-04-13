<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProofArtifactTest extends TestCase
{
    #[Test]
    public function proofArtifactBuildsWithExpectedRowAndScenarioMetadata(): void
    {
        $root = dirname(__DIR__, 2);
        $output = $root . '/.pitmaster/proof/test-proof-artifact.json';

        @unlink($output);

        exec(
            sprintf('cd %s && php ./bin/build-proof-artifact %s 2>&1', escapeshellarg($root), escapeshellarg($output)),
            $lines,
            $exitCode,
        );

        self::assertSame(0, $exitCode, implode("\n", $lines));
        self::assertFileExists($output);

        $artifact = json_decode((string) file_get_contents($output), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('config/support-matrix.json', $artifact['generated_from']['support_matrix']);
        self::assertGreaterThan(100, $artifact['summary']['rows']);
        self::assertGreaterThan(0, $artifact['summary']['rows_with_exact_scenarios']);
        self::assertIsArray($artifact['rows']);
        self::assertArrayHasKey('feature', $artifact['rows'][0]);
        self::assertArrayHasKey('scenarios', $artifact['rows'][0]);
    }
}
