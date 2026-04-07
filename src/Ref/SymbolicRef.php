<?php

declare(strict_types=1);

namespace Pitmaster\Ref;

/**
 * A symbolic reference (e.g., HEAD -> refs/heads/main).
 */
final readonly class SymbolicRef
{
    public function __construct(
        public string $name,
        public string $target,
    ) {
    }

    /**
     * Parse a symbolic ref file content.
     * Format: "ref: refs/heads/main\n"
     */
    public static function parse(string $name, string $content): ?self
    {
        $content = trim($content);

        if (!str_starts_with($content, 'ref: ')) {
            return null;
        }

        return new self($name, substr($content, 5));
    }

    /**
     * Encode to file content.
     */
    public function encode(): string
    {
        return "ref: {$this->target}\n";
    }
}
