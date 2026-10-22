<?php

namespace App\Services;

class AntiCircumventionFilter
{
    /**
     * Detect prohibited contact information (Ethiopian phone, Telegram, Email, Spelled numbers).
     */
    public function containsProhibitedContactInfo(?string $text): bool
    {
        if (empty($text)) {
            return false;
        }

        // 1. Email pattern
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/i', $text)) {
            return true;
        }

        // 2. Telegram patterns (@handle, t.me/...)
        if (preg_match('/(?:\B@|t\.me\/|telegram\.me\/)[a-zA-Z0-9_]{4,}/i', $text)) {
            return true;
        }

        // 3. Ethiopian Phone numbers with flexible formatting (+251, 09, 07, spaces, dots, dashes)
        if (preg_match('/(?:\+?251|0)[\s.-]*[97](?:[\s.-]*\d){8}\b/', $text)) {
            return true;
        }

        // 4. Any continuous sequence of 8 to 12 digits
        $digitsOnly = preg_replace('/\D/', '', $text);
        if (preg_match('/(?:251[97]\d{8}|0[97]\d{8})/', $digitsOnly)) {
            return true;
        }

        // 5. Spelled out numbers ("zero nine", "zero seven", etc.)
        $normalizedText = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', ' ', $text));
        $spelledPatterns = [
            '/\b(?:zero|0)\s*(?:nine|9|seven|7)\b/i',
            '/\b(?:zehn|ande|hulet|sost|arat|amist|sidist|sebat|simint|zetegn)\b/i',
            '/\bcall\s*me\b/i',
            '/\binbox\s*me\b/i',
            '/\btelegram\b/i',
        ];

        foreach ($spelledPatterns as $pattern) {
            if (preg_match($pattern, $normalizedText)) {
                return true;
            }
        }

        return false;
    }
}
