<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Protocol;

use PHPUnit\Framework\TestCase;
use Pitmaster\Exceptions\ProtocolException;
use Pitmaster\Protocol\KnownHosts;
use Pitmaster\Tests\Support\Workspace;

final class KnownHostsTest extends TestCase
{
    public function testOpenSshRecordsPinTheNegotiatedKeyAndRejectUntrustedHosts(): void
    {
        $file = Workspace::createFile('known-hosts-');
        $key = 'fixture-public-key-blob';
        $salt = 'fixture-hash-salt';
        $hashed = '|1|' . base64_encode($salt) . '|' . base64_encode(hash_hmac('sha1', '[example.test]:2222', $salt, true));
        $cases = [
            ['[example.test]:2222', '', true],
            ['other.test,[example.test]:2222', '', true],
            ['*.test', '', false],
            ['[*.test]:2222', '', true],
            ['![example.test]:2222,[*.test]:2222', '', false],
            [$hashed, '', true],
            ['example.test', '', false],
            ['[other.test]:2222', '', false],
            ['[example.test]:2222', '@revoked ', false],
            ['[example.test]:2222', '@cert-authority ', false],
        ];
        try {
            foreach ($cases as [$hosts, $marker, $allowed]) {
                file_put_contents($file, "# comment\n" . $marker . $hosts . ' ssh-ed25519 ' . base64_encode($key) . "\n");
                $accepted = true;
                try {
                    KnownHosts::verify('example.test', 2222, strtoupper(sha1($key)), [$file]);
                } catch (ProtocolException) {
                    $accepted = false;
                }
                self::assertSame($allowed, $accepted, $marker . $hosts);
            }
            file_put_contents($file, 'example.test ssh-ed25519 ' . base64_encode($key) . "\n");
            KnownHosts::verify('example.test', 22, sha1($key), [$file]);
            $this->expectException(ProtocolException::class);
            KnownHosts::verify('example.test', 22, sha1('different-host-key'), [$file]);
        } finally {
            Workspace::remove($file);
        }
    }

    public function testRevocationOverridesATrustedEntry(): void
    {
        $file = Workspace::createFile('known-hosts-');
        $record = 'example.test ssh-ed25519 ' . base64_encode('host-key') . "\n";
        try {
            file_put_contents($file, $record . '@revoked ' . $record);
            $this->expectException(ProtocolException::class);
            $this->expectExceptionMessage('revoked');
            KnownHosts::verify('example.test', 22, sha1('host-key'), [$file]);
        } finally {
            Workspace::remove($file);
        }
    }
}
