import { useState } from 'react';

/**
 * Local editing state for a field that can also change underneath you
 * (someone else edits it and a live update arrives).
 *
 * While the field has focus your draft is left alone; otherwise it follows
 * the latest saved value. Spread `focusProps` onto the input.
 */
export function useSyncedDraft(value: string) {
    const [draft, setDraft] = useState(value);
    const [saved, setSaved] = useState(value);
    const [editing, setEditing] = useState(false);

    if (value !== saved && !editing) {
        setSaved(value);
        setDraft(value);
    }

    return {
        draft,
        setDraft,
        focusProps: {
            onFocus: () => setEditing(true),
            onBlur: () => setEditing(false),
        },
    };
}
