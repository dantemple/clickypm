<?php

namespace App\Enums;

/**
 * Optional modules a workspace can switch on. Off by default; switching one
 * off hides it but keeps its data.
 */
enum TeamFeature: string
{
    case Gantt = 'gantt';

    /**
     * Get the display label for the feature.
     */
    public function label(): string
    {
        return match ($this) {
            self::Gantt => 'Gantt charts',
        };
    }

    /**
     * What the feature adds, for the settings page.
     */
    public function description(): string
    {
        return match ($this) {
            self::Gantt => 'A Gantt view for projects, with start dates and milestones on tasks.',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The features as options for the frontend.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $feature) => ['value' => $feature->value, 'label' => $feature->label(), 'description' => $feature->description()],
            self::cases(),
        );
    }
}
