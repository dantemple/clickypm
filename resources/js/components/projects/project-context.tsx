import { createContext, useContext } from 'react';
import type { Member, PriorityOption, Project, Status, Task } from '@/types';

type ProjectContextValue = {
    project: Project;
    statuses: Status[];
    members: Member[];
    priorities: PriorityOption[];
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
