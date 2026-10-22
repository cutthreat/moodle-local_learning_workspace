<?php
namespace local_learning_workspace\local;
defined('MOODLE_INTERNAL') || die();

/**
 * Lossless framing of nine free answers. Lengths count bytes, never line headings.
 * New drafts use this envelope; historic unambiguous heading-based answers stay readable.
 * Candidate text stays contiguous so an examiner's literal evidence quote still matches.
 */
final class academy_dialogue_answer_codec {
    private const PREFIX = "ACADEMY_DIALOGUE_V1\n";

    public static function encode(array $turns): string {
        $values = [];
        for ($number = 1; $number <= 9; $number++) {
            $values[$number] = trim((string)($turns[$number] ?? ''));
        }
        if (!array_filter($values, static fn(string $value): bool => $value !== '')) {
            return '';
        }
        $result = self::PREFIX;
        foreach ($values as $value) {
            $result .= strlen($value) . ':' . $value . ',';
        }
        return $result;
    }

    public static function decode(string $answer): array {
        if (!str_starts_with($answer, self::PREFIX)) {
            return self::decode_legacy($answer);
        }
        $offset = strlen(self::PREFIX);
        $size = strlen($answer);
        $result = [];
        for ($number = 1; $number <= 9; $number++) {
            $colon = strpos($answer, ':', $offset);
            if ($colon === false || $colon - $offset > 8) {
                return [];
            }
            $digits = substr($answer, $offset, $colon - $offset);
            if (!preg_match('/^(?:0|[1-9][0-9]{0,7})$/D', $digits)) {
                return [];
            }
            $length = (int)$digits;
            $start = $colon + 1;
            if ($length > $size - $start - 1 || substr($answer, $start + $length, 1) !== ',') {
                return [];
            }
            $result[$number] = substr($answer, $start, $length);
            $offset = $start + $length + 1;
        }
        return $offset === $size ? $result : [];
    }

    /** Match candidate text, never a length prefix or the envelope header. */
    public static function quote_is_present(string $answer, string $quote): bool {
        $turns = self::decode($answer);
        if ($turns) {
            foreach ($turns as $turn) {
                if (self::text_contains_quote($turn, $quote)) {
                    return true;
                }
            }
            return false; // Evidence cannot span two separate replies.
        }
        if (str_starts_with($answer, self::PREFIX)) {
            return false; // Malformed framing must not become plain-text evidence.
        }
        return self::text_contains_quote($answer, $quote); // Unstructured historic answer.
    }

    /** HTML textarea submission uses CRLF; rendering/copying may return LF. */
    public static function text_contains_quote(string $answer, string $quote): bool {
        $normalize = static fn(string $value): string => str_replace(["\r\n", "\r"], "\n", $value);
        return $quote !== '' && strpos($normalize($answer), $normalize($quote)) !== false;
    }

    private static function decode_legacy(string $answer): array {
        $result = [];
        if (preg_match_all('/(?:^|\n\n)Ход ([1-9]):\n(.*?)(?=\n\nХод [1-9]:\n|\z)/su',
                $answer, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $number = (int)$match[1];
                if (isset($result[$number])) {
                    return []; // Ambiguous legacy text must not be silently reassigned.
                }
                $result[$number] = trim((string)$match[2]);
            }
        }
        return $result;
    }
}

