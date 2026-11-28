<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Bird,
    Blocks,
    Camera,
    ExternalLink,
    FileText,
    Inbox,
    LayoutGrid,
    Link2,
    Mail,
    MessageCircle,
    MessageSquare,
    Receipt,
    ShieldCheck,
    Tag,
    UserRound,
    Utensils,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { analytics, dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { edit as bioEdit } from '@/routes/bio';
import { index as linksIndex } from '@/routes/links';
import { index as purchasesIndex } from '@/routes/purchases';
import { index as pricingIndex } from '@/routes/pricing';
import type { NavItem } from '@/types';

const page = usePage();
const isAdmin = computed(() => Boolean(page.props.auth.user?.is_admin));
const availableTools = computed<string[]>(() => {
    return (page.props.subscribedTools as string[] | undefined) ?? [];
});

const automationNavItems = computed<NavItem[]>(() => {
    const tools: (NavItem & { tool: string })[] = [
        {
            title: 'WhatsApp Automation',
            href: '/whatsapp',
            icon: MessageCircle,
            tool: 'whatsapp',
        },
        {
            title: 'Facebook Automation',
            href: '/facebook',
            icon: MessageSquare,
            tool: 'facebook',
        },
        {
            title: 'Instagram Automation',
            href: '/instagram',
            icon: Camera,
            tool: 'instagram',
        },
        {
            title: 'Twitter Automation',
            href: '/twitter',
            icon: Bird,
            tool: 'twitter',
        },
        {
            title: 'Email Marketing',
            href: '/email-marketing',
            icon: Mail,
            tool: 'email',
        },
    ];

    return tools
        .filter((item) => availableTools.value.includes(item.tool))
        .map((item) => ({
            title: item.title,
            href: item.href,
            icon: item.icon,
        }));
});

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'My page',
        href: bioEdit(),
        icon: UserRound,
    },
    {
        title: 'Links',
        href: linksIndex(),
        icon: Link2,
    },
    {
        title: 'Short links',
        href: '/short-links',
        icon: ExternalLink,
    },
    {
        title: 'Resumes',
        href: '/resumes',
        icon: FileText,
    },
    {
        title: 'Widgets',
        href: '/widgets',
        icon: Blocks,
        children: [
            { title: 'All widgets', href: '/widgets' },
            {
                title: 'Social & sharing',
                href: '/widgets?category=sharing',
            },
            { title: 'Engagement', href: '/widgets?category=engagement' },
            { title: 'Conversion', href: '/widgets?category=conversion' },
            { title: 'Site utility', href: '/widgets?category=utility' },
        ],
    },
    {
        title: 'Restaurant',
        href: '/restaurant',
        icon: Utensils,
    },
    {
        title: 'Inbox',
        href: '/inbox',
        icon: Inbox,
    },
    ...automationNavItems.value,
    {
        title: 'Browse tools',
        href: pricingIndex(),
        icon: Tag,
    },
    {
        title: 'Analytics',
        href: analytics(),
        icon: BarChart3,
    },
    {
        title: 'Purchases',
        href: purchasesIndex(),
        icon: Receipt,
    },
]);

const adminNavItems = computed<NavItem[]>(() => [
    {
        title: 'Admin panel',
        href: adminDashboard(),
        icon: ShieldCheck,
    },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" label="Workspace" />
            <NavMain
                v-if="isAdmin"
                :items="adminNavItems"
                label="Administration"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
