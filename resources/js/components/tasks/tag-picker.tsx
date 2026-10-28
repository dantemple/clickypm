import { Check, Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useProject } from '@/components/projects/project-context';
import { TagBadge } from '@/components/tasks/tag-badge';
import { MAX_TAGS_PER_PROJECT, updateTask } from '@/lib/tasks';
import { cn } from '@/lib/utils';
import type { Tag, Task } from '@/types';

type Option = { kind: 'tag'; tag: Tag } | { kind: 'create'; name: string };

/**
 * The task's tags as chips, plus a search box that picks from the
 * project's tags or makes a new one from whatever was typed.
 */
export function TagPicker({ task }: { task: Task }) {
    const { tags, canEdit } = useProject();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [highlight, setHighlight] = useState(0);
    const container = useRef<HTMLDivElement>(null);
    const input = useRef<HTMLInputElement>(null);

    const selected = new Set(task.tags.map((tag) => tag.id));
    const term = query.trim().toLowerCase();
    const matches = tags.filter((tag) => tag.name.toLowerCase().includes(term));
    const taken = tags.some((tag) => tag.name.toLowerCase() === term);
    const canCreate =
        term !== '' && !taken && tags.length < MAX_TAGS_PER_PROJECT;
    const options: Option[] = [
        ...matches.map((tag): Option => ({ kind: 'tag', tag })),
        ...(canCreate
            ? [{ kind: 'create', name: query.trim() } as Option]
            : []),
    ];
    const active = Math.min(highlight, Math.max(options.length - 1, 0));

    // Clicking anywhere else closes the list, and so does Escape. The task
    // panel also closes on Escape, listening on the document, so this
    // listens one level up (on the window) and stops the key there.
    useEffect(() => {
        if (!open) {
            return;
        }

        input.current?.focus();

        const onPointerDown = (event: PointerEvent) => {
            if (!container.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                setOpen(false);
            }
        };

        document.addEventListener('pointerdown', onPointerDown);
        window.addEventListener('keydown', onKeyDown, { capture: true });

        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            window.removeEventListener('keydown', onKeyDown, {
                capture: true,
            });
        };
    }, [open]);

    const setTags = (ids: number[]) => updateTask(task, { tag_ids: ids });

    const toggle = (tag: Tag) =>
        setTags(
            selected.has(tag.id)
                ? task.tags.filter((t) => t.id !== tag.id).map((t) => t.id)
                : [...task.tags.map((t) => t.id), tag.id],
        );

    const create = (name: string) => {
        updateTask(task, { new_tags: [name] });
        setQuery('');
        setHighlight(0);
    };

    const choose = (option: Option) =>
        option.kind === 'tag' ? toggle(option.tag) : create(option.name);

    const onKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                setHighlight((active + 1) % Math.max(options.length, 1));
                break;
            case 'ArrowUp':
                event.preventDefault();
                setHighlight(
                    (active - 1 + options.length) % Math.max(options.length, 1),
                );
                break;
            case 'Enter':
                event.preventDefault();

                if (options[active]) {
                    choose(options[active]);
                }

                break;
            case 'Backspace':
                // An empty box plus backspace takes the last tag off.
                if (query === '' && task.tags.length > 0) {
                    setTags(task.tags.slice(0, -1).map((t) => t.id));
                }

                break;
        }
    };

    if (!canEdit) {
        return task.tags.length > 0 ? (
            <span className="flex flex-wrap gap-1">
                {task.tags.map((tag) => (
                    <TagBadge key={tag.id} tag={tag} />
                ))}
            </span>
        ) : (
            <span className="text-sm text-muted-foreground/60">None</span>
        );
    }

    return (
        <div ref={container} className="relative">
            <div className="flex flex-wrap items-center gap-1">
                {task.tags.map((tag) => (
                    <TagBadge
                        key={tag.id}
                        tag={tag}
                        onRemove={() => toggle(tag)}
                    />
                ))}
                {open ? (
                    <input
                        ref={input}
                        autoFocus
                        value={query}
                        role="combobox"
                        aria-label="Add tag"
                        aria-expanded
                        aria-controls="tag-options"
                        aria-activedescendant={
                            options[active] ? `tag-option-${active}` : undefined
                        }
                        placeholder={
                            tags.length === 0
                                ? 'Type a tag name…'
                                : 'Search or add…'
                        }
                        maxLength={30}
                        className="h-5 min-w-28 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground/60"
                        onChange={(event) => {
                            setQuery(event.target.value);
                            setHighlight(0);
                        }}
                        onKeyDown={onKeyDown}
                    />
                ) : (
                    <button
                        type="button"
                        aria-label="Add tag"
                        className={cn(
                            'flex items-center gap-1 rounded text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring',
                            'text-muted-foreground/60 hover:text-muted-foreground',
                        )}
                        onClick={() => setOpen(true)}
                    >
                        <Plus className="size-3.5" />
                        {task.tags.length === 0 && 'Add tag'}
                    </button>
                )}
            </div>

            {open && (
                <ul
                    id="tag-options"
                    role="listbox"
                    className="absolute top-full left-0 z-20 mt-1 max-h-60 w-64 overflow-y-auto rounded-md border bg-popover p-1 text-sm shadow-md"
                >
                    {options.map((option, index) => (
                        <li
                            key={
                                option.kind === 'tag' ? option.tag.id : 'create'
                            }
                            id={`tag-option-${index}`}
                            role="option"
                            aria-selected={
                                option.kind === 'tag' &&
                                selected.has(option.tag.id)
                            }
                            className={cn(
                                'flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5',
                                index === active && 'bg-accent',
                            )}
                            onPointerMove={() => setHighlight(index)}
                            onPointerDown={(event) => event.preventDefault()}
                            onClick={() => {
                                choose(option);
                                input.current?.focus();
                            }}
                        >
                            {option.kind === 'tag' ? (
                                <>
                                    <span
                                        className="size-2.5 shrink-0 rounded-full"
                                        style={{
                                            backgroundColor: option.tag.color,
                                        }}
                                    />
                                    <span className="truncate">
                                        {option.tag.name}
                                    </span>
                                    {selected.has(option.tag.id) && (
                                        <Check className="ml-auto size-4 shrink-0" />
                                    )}
                                </>
                            ) : (
                                <>
                                    <Plus className="size-4 shrink-0 text-muted-foreground" />
                                    <span className="truncate">
                                        Create “{option.name}”
                                    </span>
                                </>
                            )}
                        </li>
                    ))}
                    {options.length === 0 && (
                        <li className="px-2 py-1.5 text-muted-foreground">
                            {tags.length >= MAX_TAGS_PER_PROJECT
                                ? `A project can have up to ${MAX_TAGS_PER_PROJECT} tags.`
                                : 'No matching tags.'}
                        </li>
                    )}
                </ul>
            )}
        </div>
    );
}
