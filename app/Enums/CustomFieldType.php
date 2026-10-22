<?php

namespace App\Enums;

enum CustomFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Dropdown = 'dropdown';
    case Checkbox = 'checkbox';
    case Url = 'url';

    /**
     * Get the display label for the field type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Url => 'Link',
            default => ucfirst($this->value),
        };
    }

    /**
     * Get the field types as options for the frontend.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
