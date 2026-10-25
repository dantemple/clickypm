<?php

namespace App\Enums;

enum ThemeColor: string
{
    case Slate = 'slate';
    case Violet = 'violet';
    case Blue = 'blue';
    case Pink = 'pink';
    case Purple = 'purple';
    case Indigo = 'indigo';
    case Orange = 'orange';
    case Teal = 'teal';
    case Stone = 'stone';
    case Green = 'green';

    /**
     * Get the display label for the colour.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * The colour of the swatch in the picker. The sidebar gradient each one
     * produces is defined in app.css under [data-theme-color="…"].
     */
    public function swatch(): string
    {
        return match ($this) {
            self::Slate => '#5b5c66',
            self::Violet => '#7c6ff2',
            self::Blue => '#3b8de0',
            self::Pink => '#e06a9e',
            self::Purple => '#b45fd8',
            self::Indigo => '#6a84f5',
            self::Orange => '#d9752e',
            self::Teal => '#3f9a9c',
            self::Stone => '#a08c7d',
            self::Green => '#6cbf7f',
        };
    }

    /**
     * All the colours, for the picker.
     *
     * @return array<int, array{value: string, label: string, swatch: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $color) => [
            'value' => $color->value,
            'label' => $color->label(),
            'swatch' => $color->swatch(),
        ], self::cases());
    }
}
