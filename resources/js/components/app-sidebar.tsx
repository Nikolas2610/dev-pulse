import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    BriefcaseBusiness,
    CalendarClock,
    LayoutGrid,
    MessageSquare,
    Plug,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { AppContextSwitcher } from '@/components/app-context-switcher';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
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
import type { AccessCapabilities, NavItem } from '@/types';

type RoleNavItem = NavItem & { capability: keyof AccessCapabilities };

const mainNavItems: RoleNavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
        capability: 'viewDashboard',
    },
    {
        title: 'Standups',
        href: '/standups',
        icon: MessageSquare,
        capability: 'viewStandups',
    },
    {
        title: 'Templates',
        href: '/templates',
        icon: BookOpen,
        capability: 'manageTemplates',
    },
    {
        title: 'Schedules',
        href: '/schedules',
        icon: CalendarClock,
        capability: 'manageSchedules',
    },
    {
        title: 'Workspaces',
        href: '/workspaces',
        icon: BriefcaseBusiness,
        capability: 'manageWorkspaces',
    },
    {
        title: 'Team Members',
        href: '/team-members',
        icon: Users,
        capability: 'viewTeam',
    },
    {
        title: 'Slack',
        href: '/integrations/slack',
        icon: Plug,
        capability: 'manageIntegrations',
    },
    {
        title: 'Discord',
        href: '/integrations/discord',
        icon: Plug,
        capability: 'manageIntegrations',
    },
];

export function AppSidebar() {
    const capabilities = usePage().props.access.capabilities;
    const visibleNavItems = mainNavItems.filter(
        (item) => capabilities[item.capability],
    );

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <AppContextSwitcher />
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={visibleNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
