<?php

declare(strict_types=1);

namespace Pitmaster\Ref;

use Pitmaster\Object\ObjectId;

/**
 * Reflog reader/writer.
 *
 * Each reflog entry: <old-hash> <new-hash> <identity> <timestamp> <tz>\t<message>\n
 */
final class Reflog
{
    /**
     * @param array<int, array{old: string, new: string, identity: string, message: string}> $entries
     */
    private function __construct(
        private readonly string $path,
        private array $entries = [],
    ) {
    }

    /**
     * Open a reflog for a ref.
     */
    public static function open(string $gitDir, string $refName): self
    {
        $path = $gitDir . '/logs/' . $refName;

        if (!is_file($path)) {
            return new self($path);
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return new self($path);
        }

        $entries = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // Format: <old> <new> <identity> <timestamp> <tz>\t<message>
            $tabPos = strpos($line, "\t");
            $message = $tabPos !== false ? substr($line, $tabPos + 1) : '';
            $prefix = $tabPos !== false ? substr($line, 0, $tabPos) : $line;

            $parts = explode(' ', $prefix, 3);

            if (count($parts) < 3) {
                continue;
            }

            $entries[] = [
                'old' => $parts[0],
                'new' => $parts[1],
                'identity' => $parts[2],
                'message' => $message,
            ];
        }

        return new self($path, $entries);
    }

    /**
     * Append an entry to the reflog.
     */
    public function append(
        ObjectId $oldId,
        ObjectId $newId,
        string $identity,
        string $message,
    ): void {
        $dir = dirname($this->path);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $zeroHash = str_repeat('0', strlen($newId->hex));
        $old = $oldId->hex !== '' ? $oldId->hex : $zeroHash;
        $new = $newId->hex;

        $line = "{$old} {$new} {$identity}\t{$message}\n";
        file_put_contents($this->path, $line, FILE_APPEND);

        $this->entries[] = [
            'old' => $old,
            'new' => $new,
            'identity' => $identity,
            'message' => $message,
        ];
    }

    /**
     * @return array<int, array{old: string, new: string, identity: string, message: string}>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Get the most recent entry.
     */
    public function latest(): ?array
    {
        if ($this->entries === []) {
            return null;
        }

        return $this->entries[count($this->entries) - 1];
    }

    public function count(): int
    {
        return count($this->entries);
    }
}
