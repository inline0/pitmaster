<?php

declare(strict_types=1);

namespace Pitmaster\Object;

use Pitmaster\Encoding\BinaryReader;
use Pitmaster\Exceptions\CorruptObjectException;

final readonly class Tree extends GitObject
{
    /**
     * @param array<int, TreeEntry> $entries
     */
    public function __construct(
        string $content,
        ObjectId $id,
        public array $entries,
    ) {
        parent::__construct(ObjectType::Tree, $content, $id);
    }

    /**
     * Parse tree object from raw content.
     *
     * Tree format: repeated (<mode> <name>\0<binary hash>)
     */
    public static function parse(string $content, ObjectId $id): self
    {
        $reader = new BinaryReader($content);
        $entries = [];
        $hashBytes = $id->hashLength();

        while (!$reader->isEof()) {
            // Read "<mode> <name>\0"
            $modeAndName = $reader->readNullTerminated();
            $spacePos = strpos($modeAndName, ' ');

            if ($spacePos === false) {
                throw CorruptObjectException::invalidContent(
                    $id->hex,
                    'tree entry missing space between mode and name'
                );
            }

            $mode = substr($modeAndName, 0, $spacePos);
            $name = substr($modeAndName, $spacePos + 1);

            // Read the entry hash using the repository object format.
            $hashHex = $reader->readHash($hashBytes);
            $hash = ObjectId::fromHex($hashHex);

            $entries[] = new TreeEntry($mode, $name, $hash);
        }

        return new self($content, $id, $entries);
    }

    /**
     * Build tree content from entries.
     *
     * @param array<int, TreeEntry> $entries
     */
    public static function buildContent(array $entries): string
    {
        $content = '';

        foreach ($entries as $entry) {
            $content .= $entry->mode . ' ' . $entry->name . "\0" . $entry->hash->binary;
        }

        return $content;
    }

    /**
     * @param array<int, TreeEntry> $entries
     */
    public static function fromEntries(array $entries, string $algo = 'sha1'): self
    {
        $content = self::buildContent($entries);
        $id = ObjectId::compute(ObjectType::Tree, $content, $algo);

        return new self($content, $id, $entries);
    }

    /**
     * Find an entry by name.
     */
    public function entry(string $name): ?TreeEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->name === $name) {
                return $entry;
            }
        }

        return null;
    }
}
