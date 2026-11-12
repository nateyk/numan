import { NavFooter } from '@/components/nav-footer';
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
    SidebarRail,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { WorkspaceSwitcher } from '@/components/workspace-switcher';
import { useTranslations } from '@/hooks/use-translations';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    BookOpen,
    Heart,
    LayoutGrid,
    Package,
    Search,
    Settings,
    ShieldAlert,
    Truck,
    UserRound,
} from 'lucide-react';
import { useMemo } from 'react';

const getMainNavItems = (
    t: (key: string, fallback: string) => string,
    isSuperadmin: boolean = false,
    interestsEnabled: boolean = false,
    isIndividual: boolean = false,
    workspaceUiEnabled: boolean = true,
): NavItem[] => {
    const items = [
        ...(isIndividual && workspaceUiEnabled
            ? []
            : [
                  {
                      title: t('navigation.dashboard', 'Dashboard'),
                      href: '/dashboard',
                      icon: LayoutGrid,
                  },
              ]),
        {
            title: t('navigation.my_freight', 'My Freight'),
            href: '/my-freight',
            icon: Package,
        },
        {
            title: t('navigation.discover_freight', 'Discover Freight'),
            href: '/freight',
            icon: Search,
        },
        {
            title: t('navigation.my_profile', 'My Profile'),
            href: '/public-profile',
            icon: UserRound,
        },
        ...(interestsEnabled
            ? [
                  {
                      title: t('navigation.my_interests', 'My Interests'),
                      href: '/my-interests',
                      icon: Heart,
                  },
              ]
            : []),
        {
            title: t('navigation.usage', 'Usage'),
            href: '/usage',
            icon: BarChart3,
        },
        ...(!workspaceUiEnabled || isIndividual
            ? []
            : [
                  {
                      title: t(
                          'navigation.workspace_settings',
                          'Workspace Settings',
                      ),
                      href: '/workspaces/settings',
                      icon: Settings,
                  },
              ]),
    ];

    if (isSuperadmin) {
        items.push({
            title: t('navigation.admin', 'Superadmin Panel'),
            href: '/admin/dashboard',
            icon: ShieldAlert,
        });
    }

    return items;
};

const getFooterNavItems = (
    t: (key: string, fallback: string) => string,
): NavItem[] => [
    {
        title: t('navigation.help_center', 'Help Center'),
        href: '/help',
        icon: BookOpen,
        external: false,
    },
];

/**
 * The individual header: a brand mark instead of a workspace switcher.
 *
 * An individual still has a personal workspace underneath, but a switcher
 * implying a list of teams would be a lie. The mark links to the public
 * board, which is where an individual actually works.
 */
function IndividualBrand() {
    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg" asChild>
                    <Link href="/freight">
                        <span className="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                            <Truck className="size-4" />
                        </span>
                        <span className="grid flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden">
                            <span className="truncate font-semibold">
                                Numan Freight
                            </span>
                            <span className="truncate text-xs text-muted-foreground">
                                Personal account
                            </span>
                        </span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}

export function AppSidebar() {
    const { t, i18n } = useTranslations();
    const { auth, marketplace, is_individual, workspace_ui_enabled } = usePage<SharedData>().props;
    const isSuperadmin = auth.user.is_superadmin === true;
    const interestsEnabled = marketplace?.interests_enabled ?? false;

    const mainNavItems = useMemo(
        () => getMainNavItems(t, isSuperadmin, interestsEnabled, is_individual, workspace_ui_enabled),
        [t, isSuperadmin, interestsEnabled, is_individual, workspace_ui_enabled],
    );
    const footerNavItems = useMemo(() => getFooterNavItems(t), [t]);

    // Set sidebar to right side for RTL languages
    const RTL_LANGUAGES = ['ar', 'he', 'fa', 'ur'];
    const isRTL = RTL_LANGUAGES.includes(i18n.language);
    const sidebarSide = isRTL ? 'right' : 'left';

    return (
        <Sidebar collapsible="icon" variant="inset" side={sidebarSide}>
            <SidebarHeader>
                {!workspace_ui_enabled || is_individual ? <IndividualBrand /> : <WorkspaceSwitcher />}
            </SidebarHeader>
            <SidebarSeparator />

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}
