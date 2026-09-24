<?php

declare(strict_types=1);

namespace Darvis\ApiX\Support;

/**
 * Checks a post text the way X counts it, before anything is sent or billed.
 *
 * Length follows the weighted count of twitter-text: most Latin, Greek and Cyrillic characters
 * count as 1, other characters (CJK, emoji) as 2, and a link as 23. An emoji made of several
 * code points counts per code point here, so the check errs on the safe side.
 */
final class PostText
{
    public const MAX_LENGTH = 280;

    private const LINK_LENGTH = 23;

    /**
     * Code point ranges that count as 1.
     */
    private const LIGHT_RANGES = [
        [0, 4351],
        [8192, 8205],
        [8208, 8223],
        [8242, 8247],
    ];

    /**
     * Anything X turns into a link: a URL with a scheme, www. or a bare domain on a common TLD.
     */
    private const LINK_PATTERN = '~(?:https?://\S+|\bwww\.\S+|\b[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9-]+)*\.(?:com|org|net|io|dev|app|ai|co|nl|be|de|eu|me|info|ly|gg|xyz|tech|site)\b(?:/\S*)?)~i';

    public static function weightedLength(string $text): int
    {
        $text = (string) preg_replace(self::LINK_PATTERN, str_repeat('x', self::LINK_LENGTH), $text);
        $length = 0;

        foreach (mb_str_split($text) as $character) {
            $length += self::isLight(mb_ord($character)) ? 1 : 2;
        }

        return $length;
    }

    public static function containsLink(string $text): bool
    {
        return preg_match(self::LINK_PATTERN, $text) === 1;
    }

    private static function isLight(int|false $codePoint): bool
    {
        if ($codePoint === false) {
            return false;
        }

        foreach (self::LIGHT_RANGES as [$from, $to]) {
            if ($codePoint >= $from && $codePoint <= $to) {
                return true;
            }
        }

        return false;
    }
}
