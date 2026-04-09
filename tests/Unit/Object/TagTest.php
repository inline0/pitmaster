<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Object;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\ObjectType;
use Pitmaster\Object\Tag;

final class TagTest extends TestCase
{
    #[Test]
    public function parseSupportsTaggerlessTagsWithoutMessageSeparator(): void
    {
        $content = implode("\n", [
            'object 4d46d9719e425ef2dfb5bfba098d0b62e21b2b92d0731892eef70db0870e3744',
            'type commit',
            'tag taggerless',
            '',
        ]);

        $id = ObjectId::compute(ObjectType::Tag, $content, 'sha256');
        $tag = Tag::parse($content, $id);

        $this->assertSame('taggerless', $tag->name);
        $this->assertSame(ObjectType::Commit, $tag->objectType);
        $this->assertSame('4d46d9719e425ef2dfb5bfba098d0b62e21b2b92d0731892eef70db0870e3744', $tag->object->hex);
        $this->assertSame('', $tag->tagger);
        $this->assertSame('', $tag->message);
    }
}
