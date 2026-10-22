<?php

namespace App\Services;

/**
 * PII / Anti-Circumvention filter for chat messages.
 *
 * SRS FR-4.5: Blocks Ethiopian phone numbers, Telegram handles, and emails
 * from being shared in chat BEFORE escrow reaches FUNDED (CONTRACTED) state.
 */
class PiiFilterService
{
    /**
     * Ethiopian phone patterns (various formats users try to share).
     *
     * @var list<string>
     */
    private const PHONE_PATTERNS = [
        '/\+251\s*[79]\d{8}/',            // +2519... or +2517...
        '/0[79]\d{8}/',                    // 09... or 07... local format
        '/\b(zero\s*nine|0\s*9|oh\s*nine)/i', // spelled-out "zero nine"
        '/\b(zero\s*seven|0\s*7|oh\s*seven)/i',
        // Spaced digit sequences that look like phone numbers
        '/\b\d[\s\-\.]{0,2}\d[\s\-\.]{0,2}\d[\s\-\.]{0,2}\d[\s\-\.]{0,2}\d[\s\-\.]{0,2}\d[\s\-\.]{0,2}\d[\s\-\.]{0,2}\d[\s\-\.]{0,2}\d[\s\-\.]{0,2}\d\b/',
    ];

    /**
     * Contact-sharing patterns to block.
     *
     * @var list<string>
     */
    private const CONTACT_PATTERNS = [
        '/@[a-zA-Z0-9_]{3,}/',             // Telegram @username
        '/t\.me\/[a-zA-Z0-9_]+/',          // t.me/ links
        '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', // email addresses
        '/wa\.me\/\d+/',                    // WhatsApp links
        '/telegram\.me\/[a-zA-Z0-9_]+/',
    ];

    /**
     * Test whether a message body contains PII that should be blocked.
     */
    public function containsBlockedContent(string $body): bool
    {
        foreach ([...self::PHONE_PATTERNS, ...self::CONTACT_PATTERNS] as $pattern) {
            if (preg_match($pattern, $body)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Identify which category of PII was detected (for logging).
     */
    public function detectCategory(string $body): string
    {
        foreach (self::PHONE_PATTERNS as $pattern) {
            if (preg_match($pattern, $body)) {
                return 'phone_number';
            }
        }

        foreach (self::CONTACT_PATTERNS as $pattern) {
            if (preg_match($pattern, $body)) {
                return 'contact_handle';
            }
        }

        return 'unknown';
    }
}
