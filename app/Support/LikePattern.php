<?php

namespace App\Support;

/**
 * Builds LIKE patterns in which user-supplied text is always literal.
 *
 * Without escaping, a search term containing % or _ would be interpreted by
 * the database as a wildcard, so a search for "%" would match every record.
 * Queries using these patterns must declare ESCAPE with ESCAPE_CHARACTER.
 */
final class LikePattern
{
    public const ESCAPE_CHARACTER = '!';

    /**
     * A pattern matching any value that contains $term exactly as written.
     */
    public static function contains(string $term): string
    {
        $escaped = str_replace(
            [self::ESCAPE_CHARACTER, '%', '_'],
            [
                self::ESCAPE_CHARACTER.self::ESCAPE_CHARACTER,
                self::ESCAPE_CHARACTER.'%',
                self::ESCAPE_CHARACTER.'_',
            ],
            $term
        );

        return '%'.$escaped.'%';
    }
}
