import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    BriefcaseBusiness,
    CalendarClock,
    ClipboardList,
    GitPullRequest,
    ListTodo,
    LayoutGrid,
    MessageSquare,
    Activity,
    ChartNoAxesCombined,
    Plug,
    Users,
} from 'lucide-react';
import { AppContextSwitcher } from '@/components/app-context-switcher';
import AppLogo from '@/components/app-logo';
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
        title: 'Reports',
        href: '/reports',
        icon: ChartNoAxesCombined,
        capability: 'viewDashboard',
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
        title: 'Automation Health',
        href: '/automation-health',
        icon: Activity,
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
        title: 'Team Standups',
        href: '/team-standups',
        icon: ClipboardList,
        capability: 'viewTeam',
    },
    {
        title: 'Integrations',
        href: '/integrations/slack',
        icon: Plug,
        capability: 'manageIntegrations',
        children: [
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
            {
                title: 'Jira',
                href: '/integrations/jira',
                icon: ListTodo,
            },
            {
                title: 'Bitbucket',
                href: '/integrations/bitbucket',
                icon: GitPullRequest,
            },
        ],
    },
];

export function AppSidebar() {
    const { access, features } = usePage().props;
    const visibleNavItems = mainNavItems
        .filter((item) => access.capabilities[item.capability])
        .map((item) => ({
            ...item,
            children: item.children?.filter(
                (child) =>
                    features.atlassianIntegrations ||
                    !['Jira', 'Bitbucket'].includes(child.title),
            ),
        }));

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
