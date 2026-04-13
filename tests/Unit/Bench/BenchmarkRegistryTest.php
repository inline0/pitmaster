<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Bench;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Bench\BenchmarkRegistry;

final class BenchmarkRegistryTest extends TestCase
{
    #[Test]
    public function canonicalAllSuiteDoesNotIncludeInstrumentationCases(): void
    {
        $names = array_map(
            static fn ($case): string => $case->name,
            BenchmarkRegistry::forSuite('all'),
        );

        self::assertContains('transport.smart-http.fetch', $names);
        self::assertNotContains('instrumentation.transport.smart-http.fetch.full', $names);
        self::assertNotContains('instrumentation.transport.ssh.discovery.full', $names);
    }

    #[Test]
    public function instrumentationSuiteReturnsOnlyInstrumentationCases(): void
    {
        $names = array_map(
            static fn ($case): string => $case->name,
            BenchmarkRegistry::forSuite('instrumentation'),
        );

        self::assertContains('instrumentation.transport.smart-http.fetch.full', $names);
        self::assertContains('instrumentation.transport.smart-http.fetch.discover', $names);
        self::assertContains('instrumentation.transport.smart-http.fetch.upload-pack', $names);
        self::assertContains('instrumentation.transport.ref-discovery.parse', $names);
        self::assertContains('instrumentation.transport.ref-discovery.decode-then-parse', $names);
        self::assertContains('instrumentation.transport.ssh.discovery.full', $names);
        self::assertContains('instrumentation.transport.ssh.discovery.command', $names);
        self::assertNotContains('transport.smart-http.fetch', $names);
        self::assertNotContains('transport.ssh.discovery', $names);
    }

    #[Test]
    public function existingGroupSuitesDoNotPullInstrumentationCasesIn(): void
    {
        $names = array_map(
            static fn ($case): string => $case->name,
            BenchmarkRegistry::forSuite('transport'),
        );

        self::assertContains('transport.smart-http.fetch', $names);
        self::assertContains('transport.ssh.discovery', $names);
        self::assertNotContains('instrumentation.transport.smart-http.fetch.full', $names);
        self::assertNotContains('instrumentation.transport.ssh.discovery.full', $names);
    }
}
