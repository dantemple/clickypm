import { Check } from 'lucide-react';
import { PROJECT_COLORS } from '@/lib/tasks';
import { cn } from '@/lib/utils';

export function ColorSwatches({
    name,
    value,
    onChange,
}: {
    name?: string;
    value: string;
    onChange: (color: string) => void;
}) {
    return (
        <div className="flex flex-wrap gap-2" role="radiogroup">
            {name && <input type="hidden" name={name} value={value} />}
            {PROJECT_COLORS.map((color) => (
                <button
                    key={color}
                    type="button"
                    role="radio"
                    aria-checked={value === color}
                    aria-label={color}
                    className={cn(
                        'flex size-7 items-center justify-center rounded-full ring-offset-2 ring-offset-background outline-none focus-visible:ring-2 focus-visible:ring-ring',
                        value === color && 'ring-2 ring-foreground/40',
                    )}
                    style={{ backgroundColor: color }}
                    onClick={() => onChange(color)}
                >
                    {value === color && <Check className="size-4 text-white" />}
                </button>
            ))}
        </div>
    );
}
