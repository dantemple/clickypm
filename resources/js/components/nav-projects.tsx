import { Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import CreateProjectModal from '@/components/projects/create-project-modal';
import {
    SidebarGroup,
    SidebarGroupAction,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { show } from '@/routes/projects';

export function NavProjects() {
    const page = usePage();
    const projects = page.props.projects ?? [];
    const isClient = page.props.currentTeam?.role === 'client';

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Projects</SidebarGroupLabel>
            {!isClient && (
                <CreateProjectModal>
                    <SidebarGroupAction title="New project">
                        <Plus /> <span className="sr-only">New project</span>
                    </SidebarGroupAction>
                </CreateProjectModal>
            )}
            <SidebarMenu>
                {projects.map((project) => {
                    const href = show.url({ project: project.id });

                    return (
                        <SidebarMenuItem key={project.id}>
                            <SidebarMenuButton
                                asChild
                                isActive={page.url.startsWith(href)}
                                tooltip={{ children: project.name }}
                            >
                                <Link href={href} prefetch>
                                    <span
                                        className="size-3 shrink-0 rounded-sm"
                                        style={{
                                            backgroundColor: project.color,
                                        }}
                                    />
                                    <span>{project.name}</span>
                                </Link>
                            </SidebarMenuButton>
                            {project.requests_count > 0 && (
                                <SidebarMenuBadge
                                    className="bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300"
                                    title="Client requests to triage"
                                >
                                    {project.requests_count}
                                </SidebarMenuBadge>
                            )}
                        </SidebarMenuItem>
                    );
                })}
                {projects.length === 0 && !isClient && (
                    <SidebarMenuItem>
                        <CreateProjectModal>
                            <SidebarMenuButton className="text-muted-foreground">
                                <Plus />
                                <span>Create your first project</span>
                            </SidebarMenuButton>
                        </CreateProjectModal>
                    </SidebarMenuItem>
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}
