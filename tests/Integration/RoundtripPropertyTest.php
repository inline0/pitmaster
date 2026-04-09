<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Config\GitConfig;
use Pitmaster\Index\Index;
use Pitmaster\Index\IndexEntry;
use Pitmaster\Index\IndexWriter;
use Pitmaster\Object\Blob;
use Pitmaster\Object\Commit;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\ObjectType;
use Pitmaster\Object\Tag;
use Pitmaster\Object\Tree;
use Pitmaster\Object\TreeEntry;
use Pitmaster\Ref\LooseRefStore;
use Pitmaster\Storage\ObjectSerializer;
use Pitmaster\Tests\Support\Workspace;

final class RoundtripPropertyTest extends TestCase
{
    /** @var list<string> */
    private array $paths = [];

    protected function setUp(): void
    {
        mt_srand(1337);
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            Workspace::remove($path);
        }
    }

    #[Test]
    public function objectIdsAndBlobsRoundTripAcrossRandomizedInputs(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $algo = $i % 2 === 0 ? 'sha1' : 'sha256';
            $payload = $this->randomPayload(mt_rand(0, 128));
            $blob = Blob::fromContent($payload, $algo);
            $decoded = ObjectSerializer::decode(ObjectSerializer::encode($blob), $blob->id->hex);

            self::assertSame($blob->id->hex, ObjectId::fromBinary($blob->id->binary)->hex);
            self::assertSame($blob->id->hex, ObjectId::fromHex($blob->id->hex)->hex);
            self::assertSame($payload, $decoded->content);
            self::assertSame($blob->id->hex, $decoded->id->hex);
        }
    }

    #[Test]
    public function treeCommitAndTagRoundTripAcrossRandomizedValidInputs(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $algo = $i % 2 === 0 ? 'sha1' : 'sha256';
            $entries = [];

            for ($j = 0; $j < 4; $j++) {
                $name = sprintf('file-%02d-%02d.txt', $i, $j);
                $blob = Blob::fromContent($this->randomPayload(mt_rand(1, 48)), $algo);
                $entries[] = new TreeEntry('100644', $name, $blob->id);
            }

            $tree = Tree::fromEntries($entries, $algo);
            $parsedTree = Tree::parse($tree->content, $tree->id);

            self::assertCount(count($entries), $parsedTree->entries);
            self::assertSame(
                array_map(static fn (TreeEntry $entry): string => $entry->name, $entries),
                array_map(static fn (TreeEntry $entry): string => $entry->name, $parsedTree->entries),
            );

            $parents = $i % 3 === 0
                ? []
                : [ObjectId::compute(ObjectType::Commit, "parent {$i}\n", $algo)];
            $message = "subject {$i}\n\n" . $this->randomPayload(mt_rand(0, 32));
            $commitContent = Commit::buildContent(
                $tree->id,
                $parents,
                'Author <author@example.com> 1700000000 +0000',
                'Committer <committer@example.com> 1700000000 +0000',
                $message,
            );
            $commitId = ObjectId::compute(ObjectType::Commit, $commitContent, $algo);
            $commit = Commit::parse($commitContent, $commitId);
            $expectedMessage = str_ends_with($message, "\n") ? $message : $message . "\n";

            self::assertSame($tree->id->hex, $commit->tree->hex);
            self::assertSame($expectedMessage, $commit->message);
            self::assertCount(count($parents), $commit->parents);

            $tagMessage = "tag message {$i}\n";
            $tagContent = implode("\n", [
                'object ' . $commitId->hex,
                'type commit',
                'tag v' . $i,
                'tagger Tagger <tagger@example.com> 1700000000 +0000',
                '',
                rtrim($tagMessage, "\n"),
            ]);
            $tagId = ObjectId::compute(ObjectType::Tag, $tagContent, $algo);
            $tag = Tag::parse($tagContent, $tagId);

            self::assertSame($commitId->hex, $tag->object->hex);
            self::assertSame('v' . $i, $tag->name);
            self::assertSame(rtrim($tagMessage, "\n"), $tag->message);
        }
    }

    #[Test]
    public function configRoundTripPreservesSectionsAndMultivalues(): void
    {
        $configPath = $this->createFile('roundtrip-config-', '.ini');
        $config = GitConfig::parse('');

        for ($i = 0; $i < 10; $i++) {
            $config->set("core.{$this->randomToken(6)}", $this->randomToken(10));
            $config->append('remote.origin.fetch', sprintf('+refs/heads/%s:refs/remotes/origin/%s', $i, $i));
        }

        $config->set('remote.origin.url', 'https://example.com/repo.git');
        $config->writeToFile($configPath);
        $roundTrip = GitConfig::fromFile($configPath);
        $expected = $config->all();
        $actual = $roundTrip->all();
        ksort($expected);
        ksort($actual);

        self::assertSame('https://example.com/repo.git', $roundTrip->get('remote.origin.url'));
        self::assertCount(10, $roundTrip->getAll('remote.origin.fetch'));
        self::assertSame($expected, $actual);
    }

    #[Test]
    public function looseRefsRoundTripAcrossRandomizedBranchesAndHeadTargets(): void
    {
        $gitDir = $this->createDirectory('roundtrip-refs-');
        mkdir($gitDir . '/refs/heads', 0777, true);
        mkdir($gitDir . '/refs/tags', 0777, true);
        $store = new LooseRefStore($gitDir);
        $headId = null;

        for ($i = 0; $i < 12; $i++) {
            $id = ObjectId::fromHex(bin2hex(random_bytes(20)));
            $branch = 'refs/heads/' . $this->randomToken(8);
            $store->update($branch, $id);

            if ($i === 0) {
                $store->updateSymbolic('HEAD', $branch);
                $headId = $id;
            }
        }

        self::assertSame($headId?->hex, $store->resolveHead()?->hex);
        self::assertCount(12, $store->list());
        self::assertSame('HEAD', $store->readHead()?->name);
    }

    #[Test]
    public function indexRoundTripPreservesRandomizedEntriesForSha1AndSha256(): void
    {
        foreach ([20 => 'sha1', 32 => 'sha256'] as $hashBytes => $algo) {
            $index = new Index($hashBytes);

            for ($i = 0; $i < 8; $i++) {
                $path = sprintf('nested/%s-%d.txt', $this->randomToken(4), $i);
                $entry = IndexEntry::create(
                    $path,
                    ObjectId::compute(ObjectType::Blob, $this->randomPayload(mt_rand(1, 32)), $algo),
                    0100644,
                    mt_rand(0, 4096),
                    $i % 3 === 0 ? 0 : 1,
                );
                $index->addEntry($entry);
            }

            $serialized = IndexWriter::serialize($index);
            $parsed = Index::parse($serialized, 'memory-index', $hashBytes);

            self::assertSame($index->version(), $parsed->version());
            self::assertSame($index->count(), $parsed->count());
            self::assertSame($hashBytes, $parsed->hashBytes());
            self::assertSame($index->paths(), $parsed->paths());
        }
    }

    private function createDirectory(string $prefix): string
    {
        $path = Workspace::createDirectory($prefix);
        $this->paths[] = $path;

        return $path;
    }

    private function createFile(string $prefix, string $suffix = ''): string
    {
        $path = Workspace::createFile($prefix, $suffix);
        $this->paths[] = $path;

        return $path;
    }

    private function randomPayload(int $length): string
    {
        $alphabet = "abcdefghijklmnopqrstuvwxyz0123456789 \n";
        $payload = '';

        for ($i = 0; $i < $length; $i++) {
            $payload .= $alphabet[mt_rand(0, strlen($alphabet) - 1)];
        }

        return $payload;
    }

    private function randomToken(int $length): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz';
        $token = '';

        for ($i = 0; $i < $length; $i++) {
            $token .= $alphabet[mt_rand(0, strlen($alphabet) - 1)];
        }

        return $token;
    }
}
