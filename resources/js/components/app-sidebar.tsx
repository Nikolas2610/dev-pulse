import { Link } from '@inertiajs/react';
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
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Standups',
        href: '/standups',
        icon: MessageSquare,
    },
    {
        title: 'Templates',
        href: '/templates',
        icon: BookOpen,
    },
    {
        title: 'Schedules',
        href: '/schedules',
        icon: CalendarClock,
    },
    {
        title: 'Workspaces',
        href: '/workspaces',
        icon: BriefcaseBusiness,
    },
    {
        title: 'Team Members',
        href: '/team-members',
        icon: Users,
    },
    {
        title: 'Slack',
        href: '/integrations/slack',
        icon: Plug,
    },
    {
        title: 'Discord',
        href: '/integrations/discord',
        icon: Plug,
    },
];

export function AppSidebar() {
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
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
