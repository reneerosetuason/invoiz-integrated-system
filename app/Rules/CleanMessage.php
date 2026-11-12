<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Keeps chat bodies respectful: rejects profanity with a friendly
 * message. Two matching modes to balance evasion-catching against
 * false positives:
 *  - STRONG: unambiguous profanity, also caught when letters are
 *    padded with spaces or punctuation ("f u c k", "f.u.c.k").
 *  - WORDS: word-boundary matched on lightly leetspeak-normalized
 *    text (0->o, 1->i, 3->e, @->a, $->s).
 */
class CleanMessage implements ValidationRule
{
    /** Matched inside the fully-collapsed text too. Unambiguous only. */
    private const STRONG = [
        'fuck', 'fucker', 'fucking', 'motherfucker',
        'putangina', 'putang ina', 'tangina',
    ];

    /** Matched on word boundaries only (avoids "class", "puked", ...). */
    private const WORDS = [
        'shit', 'bitch', 'asshole', 'dickhead', 'cunt', 'wtf', 'stfu',
        'bastard', 'whore', 'slut', 'dumbass', 'jackass', 'prick',
        'bollocks', 'mother fucker',
        // Filipino
        'gago', 'tanga', 'bobo', 'punyeta', 'ulol', 'puke', 'tite',
        'tarantado', 'hinayupak', 'leche', 'bwisit', 'bwict',
        'gunggong', 'engot',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $text = mb_strtolower($value);
        // Light leetspeak normalization.
        $normalized = strtr($text, [
            '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a',
            '5' => 's', '7' => 't', '@' => 'a', '$' => 's',
        ]);
        // Collapse letter-padding tricks like "f u c k" and "f.u.c.k".
        $collapsed = preg_replace('/[^a-z]+/u', '', $normalized) ?? '';

        foreach (self::STRONG as $word) {
            $needle = str_replace(' ', '', $word);
            if (mb_strpos($collapsed, $needle) !== false
                || preg_match('/(?<![a-z])'.preg_quote($word, '/').'(?![a-z])/u', $normalized)) {
                $fail('Please keep your message respectful. Profanity is not allowed.');

                return;
            }
        }

        foreach (self::WORDS as $word) {
            if (preg_match('/(?<![a-z])'.preg_quote($word, '/').'(?![a-z])/u', $normalized)) {
                $fail('Please keep your message respectful. Profanity is not allowed.');

                return;
            }
        }
    }
}
