import type { Mention } from '@/types';

/**
 * How a mention is stored in a comment's body: the name people read and
 * the id that says who it is, even if they're renamed later. Matches
 * Comment::MENTION_PATTERN on the server.
 *
 * @example "Over to you @[Dan Temple](user:12)."
 */
const MENTION_PATTERN = /@\[([^\]\n]{1,255})\]\(user:(\d+)\)/g;

export type BodySegment =
    | { kind: 'text'; text: string }
    | { kind: 'mention'; mention: Mention };

/** The body split into runs of text and the mentions between them. */
export function splitMentions(body: string): BodySegment[] {
    const segments: BodySegment[] = [];
    let last = 0;

    for (const match of body.matchAll(MENTION_PATTERN)) {
        if (match.index > last) {
            segments.push({
                kind: 'text',
                text: body.slice(last, match.index),
            });
        }

        segments.push({
            kind: 'mention',
            mention: { id: Number(match[2]), name: match[1] },
        });
        last = match.index + match[0].length;
    }

    if (last < body.length) {
        segments.push({ kind: 'text', text: body.slice(last) });
    }

    return segments;
}

/**
 * The body as it's edited: each mention shown as "@Name", with the people
 * behind those names kept alongside so it can be stored again.
 */
export function toDraft(body: string): { text: string; mentions: Mention[] } {
    const mentions = new Map<number, Mention>();
    const text = splitMentions(body)
        .map((segment) => {
            if (segment.kind === 'text') {
                return segment.text;
            }

            mentions.set(segment.mention.id, segment.mention);

            return `@${segment.mention.name}`;
        })
        .join('');

    return { text, mentions: [...mentions.values()] };
}

/**
 * The draft as it's stored: every "@Name" that was picked from the list
 * becomes a mention of that person. Longer names go first so "@Dan Temple"
 * isn't cut short by someone called "Dan".
 */
export function toBody(text: string, mentions: Mention[]): string {
    return [...mentions]
        .sort((a, b) => b.name.length - a.name.length)
        .reduce(
            (body, mention) =>
                body
                    .split(`@${mention.name}`)
                    .join(`@[${mention.name}](user:${mention.id})`),
            text,
        );
}

/**
 * The "@query" being typed just before the caret, if any. A query may hold
 * one space so two-word names can be searched for.
 */
export function mentionQueryAt(
    text: string,
    caret: number,
): { start: number; query: string } | null {
    const match = /(^|\s)@([^\s@]*(?:\s[^\s@]*)?)$/.exec(text.slice(0, caret));

    if (!match) {
        return null;
    }

    return {
        start: match.index + match[1].length,
        query: match[2],
    };
}
