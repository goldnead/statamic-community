<?php

namespace Goldnead\Community\Exceptions;

use RuntimeException;

class CommunityException extends RuntimeException
{
    public static function emptyBody(): self
    {
        return new self('A post or comment needs a text.');
    }

    public static function archivedRoom(): self
    {
        return new self('This room is archived and takes no new posts.');
    }

    public static function tooLong(): self
    {
        return new self('A post or comment may not be longer than 60000 characters.');
    }

    public static function unknown(string $what, string $value): self
    {
        return new self("Unknown {$what} '{$value}'.");
    }
}
