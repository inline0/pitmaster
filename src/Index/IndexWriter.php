<?php

declare(strict_types=1);

namespace Pitmaster\Index;

/**
 * Serialize an Index back to binary format for writing to .git/index.
 */
final class IndexWriter
{
    /**
     * Serialize index to binary data.
     */
    public static function serialize(Index $index): string
    {
        $entries = $index->entries();
        $body = '';

        foreach ($entries as $entry) {
            $body .= self::serializeEntry($entry);
        }

        $header = 'DIRC'
            . pack('N', $index->version())
            . pack('N', count($entries));

        $content = $header . $body;

        // Append SHA-1 checksum of everything before it
        $checksum = sha1($content, true);

        return $content . $checksum;
    }

    /**
     * Write index to a file path.
     */
    public static function write(Index $index, string $path): void
    {
        $data = self::serialize($index);

        // Atomic write
        $tmp = $path . '.lock';
        file_put_contents($tmp, $data);
        rename($tmp, $path);
    }

    private static function serializeEntry(IndexEntry $entry): string
    {
        $fixed = pack('N', $entry->ctimeSec)
            . pack('N', $entry->ctimeNsec)
            . pack('N', $entry->mtimeSec)
            . pack('N', $entry->mtimeNsec)
            . pack('N', $entry->dev)
            . pack('N', $entry->ino)
            . pack('N', $entry->mode)
            . pack('N', $entry->uid)
            . pack('N', $entry->gid)
            . pack('N', $entry->fileSize)
            . $entry->hash->binary
            . pack('n', $entry->flags);

        $pathBytes = $entry->path . "\0";
        $entryData = $fixed . $pathBytes;

        // Pad to 8-byte alignment
        $padLen = (8 - (strlen($entryData) % 8)) % 8;
        $entryData .= str_repeat("\0", $padLen);

        return $entryData;
    }
}
