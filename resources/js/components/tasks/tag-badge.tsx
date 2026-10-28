import { X } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { Tag } from '@/types';

/** A tag as a small tinted chip, with an optional remove button. */
export function TagBadge({
    tag,
    onRemove,
    className,
}: {
    tag: Pick<Tag, 'name' | 'color'>;
    onRemove?: () => void;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex h-5 max-w-40 items-center gap-0.5 rounded-full px-2 text-[11px] font-medium whitespace-nowrap',
                className,
            )}
            // A light tint of the tag's colour behind text in the colour itself.
            style={{ backgroundColor: `${tag.color}26`, color: tag.color }}
            title={tag.name}
        >
            <span className="truncate">{tag.name}</span>
            {onRemove && (
                <button
                    type="button"
                    aria-label={`Remove ${tag.name}`}
                    className="-mr-1 rounded-full p-0.5 outline-none hover:bg-current/15 focus-visible:ring-2 focus-visible:ring-ring"
                    onClick={(event) => {
                        event.stopPropagation();
                        onRemove();
                    }}
                >
                    <X className="size-3" />
                </button>
            )}
        </span>
    );
}

/** A task's tags in a row, folding any beyond `max` into a "+N" chip. */
export function TagList({
    tags,
    max = 3,
    className,
}: {
    tags: Tag[];
    max?: number;
    className?: string;
}) {
    if (tags.length === 0) {
        return null;
    }

    const shown = tags.slice(0, max);
    const rest = tags.length - shown.length;

    return (
        <span className={cn('flex min-w-0 items-center gap-1', className)}>
            {shown.map((tag) => (
                <TagBadge key={tag.id} tag={tag} />
            ))}
            {rest > 0 && (
                <span
                    className="inline-flex h-5 items-center rounded-full bg-muted px-1.5 text-[11px] font-medium text-muted-foreground"
                    title={tags
                        .slice(max)
                        .map((tag) => tag.name)
                        .join(', ')}
                >
                    +{rest}
                </span>
            )}
        </span>
    );
}
