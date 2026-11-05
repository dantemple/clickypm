import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { MemberAvatar } from '@/components/tasks/pickers';
import { mentionQueryAt } from '@/lib/mentions';
import { cn } from '@/lib/utils';
import type { Member, Mention } from '@/types';

const MAX_SUGGESTIONS = 6;

/**
 * A textarea for writing comments. Typing "@" lists the people who can be
 * mentioned; picking one writes their name and remembers who it was, so
 * the comment can be stored with a proper mention (see lib/mentions.ts).
 *
 * ⌘/Ctrl + Enter submits and Escape cancels, unless the list is open, in
 * which case Escape closes the list.
 */
export function MentionTextarea({
    value,
    mentions,
    onChange,
    members,
    onSubmit,
    onCancel,
    placement = 'above',
    className,
    ...props
}: {
    value: string;
    mentions: Mention[];
    onChange: (value: string, mentions: Mention[]) => void;
    /** Who can be mentioned. */
    members: Member[];
    onSubmit?: () => void;
    onCancel?: () => void;
    /** Where the list opens, so it isn't clipped by whatever scrolls. */
    placement?: 'above' | 'below';
    className?: string;
    rows?: number;
    placeholder?: string;
    autoFocus?: boolean;
    'aria-label'?: string;
}) {
    const textarea = useRef<HTMLTextAreaElement>(null);
    const [query, setQuery] = useState<{
        start: number;
        query: string;
    } | null>(null);
    const [highlight, setHighlight] = useState(0);
    // Where the caret goes once a picked name has been rendered into the value.
    const [caret, setCaret] = useState<number | null>(null);

    const term = query?.query.trim().toLowerCase() ?? '';
    const suggestions = query
        ? members
              .filter((member) => member.name.toLowerCase().includes(term))
              .slice(0, MAX_SUGGESTIONS)
        : [];
    const open = suggestions.length > 0;
    const active = Math.min(highlight, suggestions.length - 1);

    // Work out whether the caret sits just after an "@query". Runs after
    // every edit and whenever the caret moves.
    const track = () => {
        const element = textarea.current;

        if (!element) {
            return;
        }

        const next = mentionQueryAt(element.value, element.selectionStart);

        if (next?.start !== query?.start) {
            setHighlight(0);
        }

        setQuery(next);
    };

    const pick = (member: Member) => {
        const element = textarea.current;

        if (!element || !query) {
            return;
        }

        const caret = element.selectionStart;
        const inserted = `@${member.name} `;
        const next =
            value.slice(0, query.start) + inserted + value.slice(caret);
        const position = query.start + inserted.length;

        onChange(
            next,
            mentions.some((mention) => mention.id === member.id)
                ? mentions
                : [...mentions, { id: member.id, name: member.name }],
        );
        setQuery(null);
        setCaret(position);
    };

    // The new value lands on the next render; place the caret after it.
    useLayoutEffect(() => {
        const element = textarea.current;

        if (caret === null || !element) {
            return;
        }

        element.focus();
        element.setSelectionRange(caret, caret);
        setCaret(null);
    }, [caret]);

    // The task panel closes on Escape, listening on the document, so this
    // listens one level up (on the window) and stops the key there while
    // the textarea has focus.
    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                event.key !== 'Escape' ||
                document.activeElement !== textarea.current
            ) {
                return;
            }

            if (open) {
                event.preventDefault();
                event.stopPropagation();
                setQuery(null);
            } else if (onCancel) {
                event.preventDefault();
                event.stopPropagation();
                onCancel();
            }
        };

        window.addEventListener('keydown', onKeyDown, { capture: true });

        return () =>
            window.removeEventListener('keydown', onKeyDown, {
                capture: true,
            });
    }, [open, onCancel]);

    return (
        <div className="relative">
            <textarea
                ref={textarea}
                value={value}
                className={cn(
                    'w-full resize-y bg-transparent text-sm outline-none placeholder:text-muted-foreground',
                    className,
                )}
                role="combobox"
                aria-expanded={open}
                aria-autocomplete="list"
                aria-controls={open ? 'mention-suggestions' : undefined}
                onChange={(event) => {
                    onChange(event.target.value, mentions);
                    track();
                }}
                onSelect={track}
                onBlur={() => setQuery(null)}
                onKeyDown={(event) => {
                    if (open) {
                        if (event.key === 'ArrowDown') {
                            event.preventDefault();
                            setHighlight((active + 1) % suggestions.length);

                            return;
                        }

                        if (event.key === 'ArrowUp') {
                            event.preventDefault();
                            setHighlight(
                                (active - 1 + suggestions.length) %
                                    suggestions.length,
                            );

                            return;
                        }

                        if (event.key === 'Enter' || event.key === 'Tab') {
                            event.preventDefault();
                            pick(suggestions[active]);

                            return;
                        }
                    }

                    if (
                        event.key === 'Enter' &&
                        (event.metaKey || event.ctrlKey)
                    ) {
                        event.preventDefault();
                        onSubmit?.();
                    }
                }}
                {...props}
            />
            {open && (
                <ul
                    id="mention-suggestions"
                    role="listbox"
                    aria-label="People to mention"
                    className={cn(
                        'absolute left-0 z-20 w-64 max-w-full overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md',
                        placement === 'above'
                            ? 'bottom-full mb-1'
                            : 'top-full mt-1',
                    )}
                >
                    {suggestions.map((member, index) => (
                        <li
                            key={member.id}
                            role="option"
                            aria-selected={index === active}
                            className={cn(
                                'flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm',
                                index === active && 'bg-accent',
                            )}
                            onMouseEnter={() => setHighlight(index)}
                            // Mousedown, so the textarea keeps focus and
                            // the blur above doesn't close the list first.
                            onMouseDown={(event) => {
                                event.preventDefault();
                                pick(member);
                            }}
                        >
                            <MemberAvatar member={member} className="size-5" />
                            <span className="truncate">{member.name}</span>
                            {member.isClient && (
                                <span className="ml-auto rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground uppercase">
                                    Client
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
