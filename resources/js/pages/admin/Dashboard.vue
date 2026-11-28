<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Globe,
    Link2,
    MousePointerClick,
    ShieldCheck,
    UserCheck,
    UserPlus,
    Users,
} from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes/admin';
import { index as profilesIndex } from '@/routes/admin/profiles';
import { index as usersIndex } from '@/routes/admin/users';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    email_verified_at: string | null;
    created_at: string;
};

type AdminProfile = {
    id: number;
    username: string;
    display_name: string | null;
    is_published: boolean;
    links_count: number;
    links_sum_clicks: number | null;
    user: {
        id: number;
        name: string;
    };
};

defineProps<{
    stats: {
        totalUsers: number;
        verifiedUsers: number;
        adminUsers: number;
        newUsersThisWeek: number;
        totalProfiles: number;
        publishedProfiles: number;
        totalLinks: number;
        clicksThisWeek: number;
    };
    recentUsers: AdminUser[];
    topProfiles: AdminProfile[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Admin" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <Heading
            title="Admin panel"
            description="Overview of the accounts registered in this application"
        />

        <div class="grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium"
                        >Total users</CardTitle
                    >
                    <Users class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">{{ stats.totalUsers }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium">Verified</CardTitle>
                    <UserCheck class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ stats.verifiedUsers }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium"
                        >Administrators</CardTitle
                    >
                    <ShieldCheck class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">{{ stats.adminUsers }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium"
                        >New this week</CardTitle
                    >
                    <UserPlus class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ stats.newUsersThisWeek }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium">Bio pages</CardTitle>
                    <Globe class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ stats.totalProfiles }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ stats.publishedProfiles }} published
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium">Links</CardTitle>
                    <Link2 class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">{{ stats.totalLinks }}</p>
                    <p class="text-xs text-muted-foreground">
                        Across all pages
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium">Clicks</CardTitle>
                    <MousePointerClick class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ stats.clicksThisWeek }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        In the last 7 days
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium">Users</CardTitle>
                    <Users class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">{{ stats.totalUsers }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ stats.newUsersThisWeek }} new this week
                    </p>
                </CardContent>
            </Card>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <div
                class="flex items-center justify-between border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
            >
                <h2 class="text-sm font-medium">Top pages</h2>
                <Link
                    :href="profilesIndex()"
                    class="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    Manage pages
                </Link>
            </div>

            <table class="w-full text-sm">
                <thead
                    class="text-left text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-2 font-medium">Page</th>
                        <th class="px-4 py-2 font-medium">Owner</th>
                        <th class="px-4 py-2 font-medium">Links</th>
                        <th class="px-4 py-2 font-medium">Clicks</th>
                        <th class="px-4 py-2 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="profile in topProfiles"
                        :key="profile.id"
                        class="border-t border-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-2">@{{ profile.username }}</td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ profile.user.name }}
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ profile.links_count }}
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ profile.links_sum_clicks ?? 0 }}
                        </td>
                        <td class="px-4 py-2">
                            <Badge
                                :variant="
                                    profile.is_published
                                        ? 'default'
                                        : 'secondary'
                                "
                            >
                                {{ profile.is_published ? 'Public' : 'Hidden' }}
                            </Badge>
                        </td>
                    </tr>
                    <tr v-if="topProfiles.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-6 text-center text-muted-foreground"
                        >
                            No bio pages yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <div
                class="flex items-center justify-between border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
            >
                <h2 class="text-sm font-medium">Recent users</h2>
                <Link
                    :href="usersIndex()"
                    class="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    Manage users
                </Link>
            </div>

            <table class="w-full text-sm">
                <thead
                    class="text-left text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-2 font-medium">Name</th>
                        <th class="px-4 py-2 font-medium">Email</th>
                        <th class="px-4 py-2 font-medium">Role</th>
                        <th class="px-4 py-2 font-medium">Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in recentUsers"
                        :key="user.id"
                        class="border-t border-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-2">{{ user.name }}</td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ user.email }}
                        </td>
                        <td class="px-4 py-2">
                            <Badge
                                :variant="
                                    user.is_admin ? 'default' : 'secondary'
                                "
                            >
                                {{ user.is_admin ? 'Admin' : 'User' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ new Date(user.created_at).toLocaleDateString() }}
                        </td>
                    </tr>
                    <tr v-if="recentUsers.length === 0">
                        <td
                            colspan="4"
                            class="px-4 py-6 text-center text-muted-foreground"
                        >
                            No users yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
