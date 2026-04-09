<?php

declare(strict_types=1);

namespace Pitmaster\Object;

use Pitmaster\Exceptions\CorruptObjectException;

final readonly class Commit extends GitObject
{
    /**
     * @param array<int, ObjectId> $parents
     */
    public function __construct(
        string $content,
        ObjectId $id,
        public ObjectId $tree,
        public array $parents,
        public string $author,
        public string $committer,
        public string $message,
        public ?string $gpgSignature = null,
    ) {
        parent::__construct(ObjectType::Commit, $content, $id);
    }

    /**
     * Parse commit object from raw content.
     *
     * Format:
     *   tree <hex>\n
     *   parent <hex>\n     (zero or more)
     *   author ...\n
     *   committer ...\n
     *   [gpgsig ...]\n
     *   \n
     *   <message>
     */
    public static function parse(string $content, ObjectId $id): self
    {
        $headerEnd = strpos($content, "\n\n");

        if ($headerEnd === false) {
            throw CorruptObjectException::invalidContent($id->hex, 'missing blank line separating header from message');
        }

        $headerSection = substr($content, 0, $headerEnd);
        $message = substr($content, $headerEnd + 2);

        $tree = null;
        $parents = [];
        $author = '';
        $committer = '';
        $gpgSignature = null;
        $inGpgSig = false;
        $gpgLines = [];

        foreach (explode("\n", $headerSection) as $line) {
            if ($inGpgSig) {
                if (str_starts_with($line, ' ')) {
                    $gpgLines[] = substr($line, 1);
                    continue;
                }

                $gpgSignature = implode("\n", $gpgLines);
                $inGpgSig = false;
            }

            if (str_starts_with($line, 'tree ')) {
                $tree = ObjectId::fromHex(substr($line, 5));
            } elseif (str_starts_with($line, 'parent ')) {
                $parents[] = ObjectId::fromHex(substr($line, 7));
            } elseif (str_starts_with($line, 'author ')) {
                $author = substr($line, 7);
            } elseif (str_starts_with($line, 'committer ')) {
                $committer = substr($line, 10);
            } elseif (str_starts_with($line, 'gpgsig ')) {
                $inGpgSig = true;
                $gpgLines = [substr($line, 7)];
            }
        }

        if ($inGpgSig) {
            $gpgSignature = implode("\n", $gpgLines);
        }

        if ($tree === null) {
            throw CorruptObjectException::invalidContent($id->hex, 'commit missing tree');
        }

        return new self($content, $id, $tree, $parents, $author, $committer, $message, $gpgSignature);
    }

    /**
     * Build commit content from components.
     *
     * @param array<int, ObjectId> $parents
     */
    public static function buildContent(
        ObjectId $tree,
        array $parents,
        string $author,
        string $committer,
        string $message,
    ): string {
        $lines = ["tree {$tree->hex}"];

        foreach ($parents as $parent) {
            $lines[] = "parent {$parent->hex}";
        }

        $lines[] = "author {$author}";
        $lines[] = "committer {$committer}";

        if ($message !== '' && !str_ends_with($message, "\n")) {
            $message .= "\n";
        }

        return implode("\n", $lines) . "\n\n" . $message;
    }

    public function isRoot(): bool
    {
        return $this->parents === [];
    }

    public function isMerge(): bool
    {
        return count($this->parents) > 1;
    }

    /**
     * Parse the author timestamp from the author line.
     * Format: "Name <email> timestamp timezone"
     */
    public function authorTimestamp(): ?int
    {
        if (preg_match('/(\d+)\s+[+-]\d{4}$/', $this->author, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Parse the committer timestamp from the committer line.
     */
    public function committerTimestamp(): ?int
    {
        if (preg_match('/(\d+)\s+[+-]\d{4}$/', $this->committer, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
