<?php

namespace App\Enums;

enum ViewType: string
{
    case List = 'list';
    case Table = 'table';

    /**
     * Get the display label for the view type.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Get the default settings for a view of this type.
     *
     * @return array<string, mixed>
     */
    public function defaultSettings(): array
    {
        return match ($this) {
            self::List => [
                'group_by' => 'status',
                'show_closed' => false,
            ],
            self::Table => [
                'sort' => ['column' => 'position', 'direction' => 'asc'],
                'columns' => ['status', 'assignee', 'priority', 'due_date'],
                'show_closed' => true,
            ],
        };
    }
}
