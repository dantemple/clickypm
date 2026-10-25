<?php

namespace App\Enums;

enum ViewType: string
{
    case List = 'list';
    case Board = 'board';
    case Table = 'table';
    case Gantt = 'gantt';

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
            self::Board => [
                'show_closed' => true,
            ],
            self::Table => [
                'sort' => ['column' => 'position', 'direction' => 'asc'],
                'columns' => ['status', 'assignee', 'priority', 'due_date'],
                'show_closed' => true,
            ],
            self::Gantt => [
                'zoom' => 'week',
                'show_closed' => true,
            ],
        };
    }
}
