import { createContext, useContext } from 'react';
import type {
    CustomField,
    Member,
    PriorityOption,
    Project,
    Status,
    Tag,
    Task,
} from '@/types';

type ProjectContextValue = {
    project: Project;
    statuses: Status[];
    members: Member[];
    priorities: PriorityOption[];
    /** The custom fields the project uses, as far as this viewer may see. */
    customFields: CustomField[];
    /** The project's tags, for the picker. */
    tags: Tag[];
    /** False for clients: they can read, comment and request, but not edit. */
    canEdit: boolean;
    openTask: (task: Task) => void;
};

export const ProjectContext = createContext<ProjectContextValue | null>(null);

export function useProject(): ProjectContextValue {
    const value = useContext(ProjectContext);

    if (!value) {
        throw new Error('useProject must be used inside a ProjectContext');
    }

    return value;
}
