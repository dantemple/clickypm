export type TeamRole = 'owner' | 'admin' | 'member' | 'client';

export type Team = {
    id: number;
    name: string;
    slug: string;
    isPersonal: boolean;
    role?: TeamRole;
    roleLabel?: string;
    isCurrent?: boolean;
    /** Optional modules switched on, keyed by feature, e.g. { gantt: true }. */
    features?: Record<string, boolean>;
};

export type FeatureOption = {
    value: string;
    label: string;
    description: string;
};

export type TeamMember = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    role: TeamRole;
    role_label: string;
};

export type TeamInvitation = {
    code: string;
    email: string;
    role: TeamRole;
    role_label: string;
    created_at: string;
};

export type TeamInvitationContext = {
    code: string;
    teamName: string;
};

export type DashboardInvitation = {
    code: string;
    inviterName: string;
    team: {
        name: string;
        slug: string;
    };
};

export type TeamPermissions = {
    canUpdateTeam: boolean;
    canDeleteTeam: boolean;
    canAddMember: boolean;
    canUpdateMember: boolean;
    canRemoveMember: boolean;
    canCreateInvitation: boolean;
    canCancelInvitation: boolean;
};

export type RoleOption = {
    value: TeamRole;
    label: string;
};
