import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, Trash2 } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { SearchButton } from '@/components/command-palette';
import { NavMain } from '@/components/nav-main';
import { NavProjects } from '@/components/nav-projects';
import { NavUser } from '@/components/nav-user';
import { TeamSwitcher } from '@/components/team-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as trashIndex } from '@/routes/trash';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const dashboardUrl = page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug)
        : '/';

    const currentTeam = page.props.currentTeam;
    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl,
            icon: LayoutGrid,
        },
        // Clients can't delete anything, so they have no trash to look at.
        ...(currentTeam && currentTeam.role !== 'client'
            ? [
                  {
                      title: 'Trash',
                      href: trashIndex(currentTeam.slug),
                      icon: Trash2,
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <TeamSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
                {currentTeam && (
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SearchButton />
                        </SidebarMenuItem>
                    </SidebarMenu>
                )}
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                <NavProjects />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
