<?php

namespace App\Support;

class InitialsAvatar
{
    /**
     * Build a self-contained SVG data URI showing the initials of $name.
     * Keeps avatars local instead of leaking names to an external service.
     */
    public static function url(?string $name, string $background = '#6366f1', string $color = '#ffffff'): string
    {
        $initials = self::initials($name);

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128"><rect width="128" height="128" rx="24" fill="%s"/>'
            . '<text x="64" y="64" fill="%s" font-family="system-ui,-apple-system,Segoe UI,sans-serif" font-size="52" '
            . 'font-weight="700" text-anchor="middle" dominant-baseline="central">%s</text></svg>',
            htmlspecialchars($background, ENT_QUOTES),
            htmlspecialchars($color, ENT_QUOTES),
            htmlspecialchars($initials, ENT_QUOTES)
        );

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private static function initials(?string $name): string
    {
        $words = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (empty($words)) {
            return '?';
        }

        $initials = mb_strtoupper(mb_substr($words[0], 0, 1));

        if (count($words) > 1) {
            $initials .= mb_strtoupper(mb_substr($words[count($words) - 1], 0, 1));
        }

        return $initials;
    }
}
