<?php

declare(strict_types=1);

namespace Pitmaster\Object;

final readonly class Blob extends GitObject
{
    public function __construct(string $content, ObjectId $id)
    {
        parent::__construct(ObjectType::Blob, $content, $id);
    }

    public static function fromContent(string $content, string $algo = 'sha1'): self
    {
        $id = ObjectId::compute(ObjectType::Blob, $content, $algo);

        return new self($content, $id);
    }
}
