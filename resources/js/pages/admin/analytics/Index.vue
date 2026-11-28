<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps<{
    stats: {
        users: number;
        profiles: number;
        publishedProfiles: number;
        links: number;
        customDomains: number;
        clicksAllTime: number;
        clicksLast30: number;
        viewsAllTime: number;
        viewsLast30: number;
        pendingPurchases: number;
        completedPurchases: number;
        revenue: string;
    };
    daily: Array<{ date: string; label: string; clicks: number }>;
    topProfiles: Array<{
        id: number;
        username: string;
        owner: string | null;
        custom_domain: string | null;
        links_count: number;
        clicks: number;
    }>;
    topLinks: Array<{
        id: number;
        title: string;
        username: string | null;
        clicks: number;
    }>;
    topReferrers: Array<{ host: string; clicks: number }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Analytics', href: '/admin/analytics' },
        ],
    },
});

const dailyMax = computed(() =>
    Math.max(1, ...props.daily.map((day) => day.clicks)),
);

const cards = computed(() => [
    { label: 'Users', value: props.stats.users, hint: `${props.stats.publishedProfiles} published pages` },
    { label: 'Pages', value: props.stats.profiles, hint: `${props.stats.customDomains} with custom domains` },
    { label: 'Links', value: props.stats.links, hint: 'Across all pages' },
    { label: 'Clicks (30 days)', value: props.stats.clicksLast30, hint: `${props.stats.clicksAllTime} all time` },
    { label: 'Page views (30 days)', value: props.stats.viewsLast30, hint: `${props.stats.viewsAllTime} all time` },
    { label: 'Pending purchases', value: props.stats.pendingPurchases, hint: 'Awaiting review' },
    { label: 'Completed purchases', value: props.stats.completedPurchases, hint: props.stats.revenue },
]);
</script>

<template>
    <Head title="Admin analytics" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <Heading
            title="Platform analytics"
            description="Traffic, links and revenue across every account"
        />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card v-for="card in cards" :key="card.label">
                <CardHeader>
                    <CardTitle class="text-sm font-medium">{{ card.label }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">{{ card.value }}</p>
                    <p class="text-xs text-muted-foreground">{{ card.hint }}</p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Clicks in the last 30 days</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex h-48 items-end gap-1">
                    <div
                        v-for="day in props.daily"
                        :key="day.date"
                        class="group flex h-full flex-1 flex-col items-center justify-end"
                        :title="`${day.clicks} clicks on ${day.date}`"
                    >
                        <div
                            class="w-full rounded-t bg-indigo-500 transition-all group-hover:bg-indigo-600"
                            :style="{
                                height: `${(day.clicks / dailyMax) * 100}%`,
                                minHeight: day.clicks ? '4px' : '2px',
                                opacity: day.clicks ? 1 : 0.15,
                            }"
                        />
                    </div>
                </div>
                <div class="mt-2 flex justify-between text-xs text-muted-foreground">
                    <span>{{ props.daily[0]?.label }}</span>
                    <span>{{ props.daily[props.daily.length - 1]?.label }}</span>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-base">Top pages</CardTitle>
                    <Link
                        href="/admin/profiles"
                        class="text-sm text-muted-foreground underline-offset-4 hover:underline"
                    >
                        Manage
                    </Link>
                </CardHeader>
                <CardContent>
                    <table class="w-full text-sm">
                        <tbody>
                            <tr
                                v-for="profile in props.topProfiles"
                                :key="profile.id"
                                class="border-b last:border-0"
                            >
                                <td class="py-2">
                                    @{{ profile.username }}
                                    <p
                                        v-if="profile.custom_domain"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ profile.custom_domain }}
                                    </p>
                                </td>
                                <td class="py-2 text-muted-foreground">
                                    {{ profile.links_count }} links
                                </td>
                                <td class="py-2 text-right font-medium">
                                    {{ profile.clicks }}
                                </td>
                            </tr>
                            <tr v-if="props.topProfiles.length === 0">
                                <td class="py-4 text-center text-muted-foreground">
                                    No pages yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Top links</CardTitle>
                </CardHeader>
                <CardContent>
                    <table class="w-full text-sm">
                        <tbody>
                            <tr
                                v-for="link in props.topLinks"
                                :key="link.id"
                                class="border-b last:border-0"
                            >
                                <td class="py-2">
                                    {{ link.title }}
                                    <p class="text-xs text-muted-foreground">
                                        @{{ link.username ?? 'unknown' }}
                                    </p>
                                </td>
                                <td class="py-2 text-right font-medium">
                                    {{ link.clicks }}
                                </td>
                            </tr>
                            <tr v-if="props.topLinks.length === 0">
                                <td class="py-4 text-center text-muted-foreground">
                                    No links yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Top referrers (30 days)</CardTitle>
            </CardHeader>
            <CardContent>
                <ul v-if="props.topReferrers.length" class="grid gap-2 text-sm sm:grid-cols-2">
                    <li
                        v-for="referrer in props.topReferrers"
                        :key="referrer.host"
                        class="flex items-center justify-between gap-3 rounded-md border px-3 py-2"
                    >
                        <span class="truncate">{{ referrer.host }}</span>
                        <span class="shrink-0 text-muted-foreground">
                            {{ referrer.clicks }}
                        </span>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">
                    No referrers recorded yet.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
