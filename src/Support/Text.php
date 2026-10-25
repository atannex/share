<?php

declare(strict_types=1);

namespace Atannex\Share\Support;

final class Text
{
    /**
     * Shortened to at most $limit characters (the ellipsis included), cut at
     * a word boundary when one is close.
     */
    public static function limit(string $text, int $limit, string $end = '…'): string
    {
        if ($limit <= 0) {
            return '';
        }

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $room = $limit - mb_strlen($end);
        if ($room <= 0) {
            return mb_substr($end, 0, $limit);
        }

        $cut = mb_substr($text, 0, $room + 1);
        $space = mb_strrpos($cut, ' ');

        // Cut at the last space unless that loses more than a third of the room.
        $cut = $space !== false && $space >= $room * 2 / 3 ? mb_substr($cut, 0, $space) : mb_substr($cut, 0, $room);

        return rtrim($cut, " \t\n\r\0\x0B,;:.-–—").$end;
    }
}
