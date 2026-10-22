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
