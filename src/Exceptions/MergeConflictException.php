<?php

declare(strict_types=1);

namespace Pitmaster\Exceptions;

use RuntimeException;

final class MergeConflictException extends RuntimeException
{
    /**
     * @param array<int, string> $conflictPaths
     */
    public function __construct(
        public readonly array $conflictPaths,
        string $message = '',
    ) {
        if ($message === '') {
            $message = 'Merge conflict in: ' . implode(', ', $conflictPaths);
        }

        parent::__construct($message);
    }
}
