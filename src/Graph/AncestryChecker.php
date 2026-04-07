<?php

declare(strict_types=1);

namespace Pitmaster\Graph;

use Pitmaster\Merge\MergeBase;
use Pitmaster\Object\ObjectId;
use Pitmaster\Storage\ObjectDatabase;

/**
 * Check ancestry relationships between commits.
 */
final class AncestryChecker
{
    private readonly MergeBase $mergeBase;

    public function __construct(ObjectDatabase $objects)
    {
        $this->mergeBase = new MergeBase($objects);
    }

    /**
     * Is commit A an ancestor of commit B?
     */
    public function isAncestor(ObjectId $a, ObjectId $b): bool
    {
        return $this->mergeBase->isAncestor($a, $b);
    }
}
