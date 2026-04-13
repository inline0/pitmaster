<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SupportEvidenceValidationTest extends TestCase
{
    #[Test]
    public function supportMatrixRowsResolveToExistingEvidence(): void
    {
        $root = dirname(__DIR__, 2);

        exec(
            sprintf('cd %s && php ./bin/verify-support-evidence 2>&1', escapeshellarg($root)),
            $output,
            $exitCode,
        );

        self::assertSame(
            0,
            $exitCode,
            "support evidence validation failed:\n" . implode("\n", $output),
        );
    }
}
