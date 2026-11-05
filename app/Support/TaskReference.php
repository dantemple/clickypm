<?php

namespace App\Support;

/**
 * A task key as written outside the app: WEB-42 is task 42 of the project
 * whose key is WEB. Found in branch names, commit messages and PR titles.
 */
final class TaskReference
{
    /**
     * A project key (letters and numbers, starting with a letter) and a
     * number, in any case, not glued to other letters or numbers.
     */
    private const PATTERN = '/(?<![a-z0-9])([a-z][a-z0-9]{1,9})-(\d{1,9})(?![a-z0-9])/i';

    public function __construct(
        public readonly string $key,
        public readonly int $number,
    ) {}

    /**
     * The reference the whole string is, if it is one.
     */
    public static function parse(string $text): ?self
    {
        $matches = self::all($text);

        return count($matches) === 1 && preg_match('/^\s*'.preg_quote($matches[0]->key.'-'.$matches[0]->number, '/').'\s*$/i', $text)
            ? $matches[0]
            : null;
    }

    /**
     * Every distinct reference mentioned in the given strings, in order.
     *
     * @return array<int, self>
     */
    public static function all(string ...$texts): array
    {
        $found = [];

        foreach ($texts as $text) {
            preg_match_all(self::PATTERN, $text, $matches, PREG_SET_ORDER);

            foreach ($matches as [, $key, $number]) {
                $found[strtoupper($key).'-'.(int) $number] ??= new self(strtoupper($key), (int) $number);
            }
        }

        return array_values($found);
    }

    public function __toString(): string
    {
        return "{$this->key}-{$this->number}";
    }
}
