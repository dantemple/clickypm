import { Check } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import { useThemeColor } from '@/hooks/use-theme-color';
import { cn } from '@/lib/utils';

export type ThemeColorOption = {
    value: string;
    label: string;
    swatch: string;
};

export default function ThemeColorPicker({
    colors,
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement> & { colors: ThemeColorOption[] }) {
    const { themeColor, updateThemeColor } = useThemeColor();

    return (
        <div
            role="radiogroup"
            aria-label="Theme color"
            className={cn('flex flex-wrap gap-3', className)}
            {...props}
        >
            {colors.map(({ value, label, swatch }) => {
                const selected = themeColor === value;

                return (
                    <button
                        key={value}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        aria-label={label}
                        title={label}
                        onClick={() => updateThemeColor(value)}
                        style={{ backgroundColor: swatch }}
                        className={cn(
                            'flex size-9 items-center justify-center rounded-lg text-white transition-transform hover:scale-105 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none',
                            selected &&
                                'ring-2 ring-offset-2 ring-offset-background',
                        )}
                    >
                        {selected && <Check className="size-4" />}
                    </button>
                );
            })}
        </div>
    );
}
