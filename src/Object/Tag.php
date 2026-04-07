<?php

declare(strict_types=1);

namespace Pitmaster\Object;

use Pitmaster\Exceptions\CorruptObjectException;

/**
 * Annotated tag object.
 *
 * Format:
 *   object <hex>\n
 *   type <type>\n
 *   tag <name>\n
 *   tagger <identity>\n
 *   \n
 *   <message>
 */
final readonly class Tag extends GitObject
{
    public function __construct(
        string $content,
        ObjectId $id,
        public ObjectId $object,
        public ObjectType $objectType,
        public string $name,
        public string $tagger,
        public string $message,
    ) {
        parent::__construct(ObjectType::Tag, $content, $id);
    }

    public static function parse(string $content, ObjectId $id): self
    {
        $headerEnd = strpos($content, "\n\n");

        if ($headerEnd === false) {
            throw CorruptObjectException::invalidContent($id->hex, 'tag missing blank line');
        }

        $headerSection = substr($content, 0, $headerEnd);
        $message = substr($content, $headerEnd + 2);

        $object = null;
        $objectType = null;
        $name = '';
        $tagger = '';

        foreach (explode("\n", $headerSection) as $line) {
            if (str_starts_with($line, 'object ')) {
                $object = ObjectId::fromHex(substr($line, 7));
            } elseif (str_starts_with($line, 'type ')) {
                $objectType = ObjectType::from(substr($line, 5));
            } elseif (str_starts_with($line, 'tag ')) {
                $name = substr($line, 4);
            } elseif (str_starts_with($line, 'tagger ')) {
                $tagger = substr($line, 7);
            }
        }

        if ($object === null) {
            throw CorruptObjectException::invalidContent($id->hex, 'tag missing object');
        }

        if ($objectType === null) {
            throw CorruptObjectException::invalidContent($id->hex, 'tag missing type');
        }

        return new self($content, $id, $object, $objectType, $name, $tagger, $message);
    }
}
