<?php

namespace App\Enums;

enum TaskPriority: int
{
    case Urgent = 1;
    case High = 2;
    case Normal = 3;
    case Low = 4;

    /**
     * Get the display label for the priority.
     */
    public function label(): string
    {
        return $this->name;
    }

    /**
     * The priority written by name in any case ("high", "Urgent"), if there is one.
     */
    public static function fromName(string $name): ?self
    {
        foreach (self::cases() as $priority) {
            if (strcasecmp($priority->name, trim($name)) === 0) {
                return $priority;
            }
        }

        return null;
    }

    /**
     * Get the priorities as options for the frontend.
     *
     * @return array<array{value: int, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $priority) => ['value' => $priority->value, 'label' => $priority->label()],
            self::cases(),
        );
    }
}
