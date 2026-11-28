<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BarChart3,
    CalendarDays,
    Download,
    ExternalLink,
    Eye,
    Link2,
    Lock,
    MousePointerClick,
    Percent,
} from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type DailyPoint = {
    date: string;
    label: string;
    clicks: number;
};

const props = defineProps<{
    analyticsEnabled: boolean;
    canViewAnalytics: boolean;
    stats: {
        clicksAllTime: number;
        clicksLast30: number;
        clicksThisWeek: number;
        links: number;
        averagePerDay: number;
        viewsLast30: number;
        viewsAll: number;
        clickRate: number;
    };
    daily: DailyPoint[];
    topLinks: Array<{
        id: number;
        title: string;
        clicks: number;
        clicks_week: number;
        is_active: boolean;
    }>;
    topReferrers: Array<{ host: string; clicks: number }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Analytics', href: '/analytics' }],
    },
});

const dailyMax = computed(() =>
    Math.max(1, ...props.daily.map((day) => day.clicks)),
);

const topClicks = computed(() =>
    Math.max(1, ...props.topLinks.map((link) => link.clicks)),
);
</script>

<template>
    <Head title="Analytics" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="Analytics"
                description="See how visitors interact with your page"
            />
            <div class="flex flex-wrap items-center gap-4">
                <a
                    v-if="props.canViewAnalytics && props.analyticsEnabled"
                    href="/analytics/export"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    <Download class="size-3.5" />
                    Export CSV
                </a>
                <Link
                    href="/links"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    Manage links
                    <ExternalLink class="size-3.5" />
                </Link>
            </div>
        </div>

        <div
            v-if="!props.analyticsEnabled"
            class="flex flex-col items-start gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-5"
        >
            <p class="flex items-center gap-2 font-medium">
                <Lock class="size-4" /> Analytics is temporarily disabled
            </p>
            <p class="text-sm text-muted-foreground">
                The site owner has paused click analytics. Your click data is
                still being recorded and will appear here when it is turned
                back on.
            </p>
        </div>

        <div
            v-else-if="!props.canViewAnalytics"
            class="flex flex-col items-start gap-3 rounded-xl border border-amber-500/40 bg-amber-500/10 p-5"
        >
            <p class="flex items-center gap-2 font-medium">
                <Lock class="size-4" /> Analytics is a Pro feature
            </p>
            <p class="text-sm text-muted-foreground">
                Upgrade to a plan with analytics to see the full dashboard below.
                Admins always see everything.
            </p>
            <Link
                href="/pricing"
                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-500"
            >
                View plans
            </Link>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium">Clicks (30 days)</CardTitle>
                    <MousePointerClick class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">{{ props.stats.clicksLast30 }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ props.stats.averagePerDay }} per day on average
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium">All time</CardTitle>
                    <BarChart3 class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">{{ props.stats.clicksAllTime }}</p>
                    <p class="text-xs text-muted-foreground">Total recorded clicks</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium">This week</CardTitle>
                    <CalendarDays class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">{{ props.stats.clicksThisWeek }}</p>
                    <p class="text-xs text-muted-foreground">Last 7 days</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium">Page views (30 days)</CardTitle>
                    <Eye class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ props.stats.viewsLast30 }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ props.stats.viewsAll }} all time
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium">Click rate</CardTitle>
                    <Percent class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ props.stats.clickRate }}%
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Clicks per page view
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium">Links</CardTitle>
                    <Link2 class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">{{ props.stats.links }}</p>
                    <p class="text-xs text-muted-foreground">On your page</p>
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
                        class="group flex h-full flex-1 flex-col items-center justify-end gap-2"
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
                <CardHeader>
                    <CardTitle class="text-base">Top links</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul v-if="props.topLinks.length" class="space-y-4">
                        <li
                            v-for="link in props.topLinks"
                            :key="link.id"
                            class="space-y-1.5"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <span class="truncate text-sm font-medium">
                                    {{ link.title }}
                                </span>
                                <span class="shrink-0 text-sm text-muted-foreground">
                                    {{ link.clicks }}
                                    <span v-if="link.clicks_week" class="text-xs">
                                        (+{{ link.clicks_week }})
                                    </span>
                                </span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full bg-indigo-500"
                                    :style="{
                                        width: `${(link.clicks / topClicks) * 100}%`,
                                        opacity: link.is_active ? 1 : 0.4,
                                    }"
                                />
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        No links yet. Add a link to start collecting clicks.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Top referrers (30 days)</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul v-if="props.topReferrers.length" class="space-y-2 text-sm">
                        <li
                            v-for="referrer in props.topReferrers"
                            :key="referrer.host"
                            class="flex items-center justify-between gap-3"
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
    </div>
</template>
