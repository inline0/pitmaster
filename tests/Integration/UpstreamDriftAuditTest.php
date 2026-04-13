<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UpstreamDriftAuditTest extends TestCase
{
    #[Test]
    public function vendoredUpstreamFixturesMapCleanlyToImportedScenarios(): void
    {
        $root = dirname(__DIR__, 2);

        exec(
            sprintf('cd %s && php ./bin/audit-upstream-drift 2>&1', escapeshellarg($root)),
            $output,
            $exitCode,
        );

        self::assertSame(0, $exitCode, "upstream drift audit failed:\n" . implode("\n", $output));
    }
}
