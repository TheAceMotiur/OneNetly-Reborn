<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    Copy,
    Globe2,
    Lock,
    MonitorSmartphone,
    MousePointerClick,
    QrCode,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type ShortLinkStatus = 'live' | 'paused' | 'expired' | 'exhausted';

type ShortLinkItem = {
    id: number;
    title: string | null;
    slug: string;
    short_url: string;
    destination: string;
    mobile_destination: string | null;
    protected: boolean;
    expires_at: string | null;
    max_clicks: number | null;
    clicks: number;
    clicks_week: number;
    visitors_week: number;
    status: ShortLinkStatus;
    is_active: boolean;
    last_clicked_at: string | null;
    created_at: string | null;
    utm: {
        source: string | null;
        medium: string | null;
        campaign: string | null;
        term: string | null;
        content: string | null;
    };
};

const props = defineProps<{
    link: ShortLinkItem;
    totals: {
        clicks: number;
        visitors: number;
        today: number;
        week: number;
    };
    series: { date: string; clicks: number }[];
    referrers: { source: string; clicks: number }[];
    devices: { device: string; clicks: number }[];
    countries: { country: string; clicks: number }[];
    recent: {
        id: number;
        clicked_at: string;
        referrer: string | null;
        device: string | null;
        country: string | null;
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Short links', href: '/short-links' },
            { title: 'Analytics', href: '#' },
        ],
    },
});

const copied = ref(false);

const statusLabels: Record<ShortLinkStatus, string> = {
    live: 'Live',
    paused: 'Paused',
    expired: 'Expired',
    exhausted: 'Limit reached',
};

const maxClicks = computed(() =>
    Math.max(1, ...props.series.map((point) => point.clicks)),
);

const deviceTotal = computed(() =>
    props.devices.reduce((total, row) => total + row.clicks, 0),
);

const countryTotal = computed(() =>
    props.countries.reduce((total, row) => total + row.clicks, 0),
);

const referrerTotal = computed(() =>
    props.referrers.reduce((total, row) => total + row.clicks, 0),
);

function barHeight(clicks: number): string {
    return `${Math.max(2, Math.round((clicks / maxClicks.value) * 100))}%`;
}

function percent(clicks: number, total: number): string {
    return `${total > 0 ? Math.round((clicks / total) * 100) : 0}%`;
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

async function copy(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.link.short_url);
        copied.value = true;
        window.setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        copied.value = false;
    }
}
</script>

<template>
    <Head :title="`Analytics · ${props.link.title || props.link.slug}`" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-2">
                <Button variant="ghost" size="sm" as-child class="-ml-2">
                    <Link href="/short-links">
                        <ArrowLeft class="size-4" />
                        All short links
                    </Link>
                </Button>
                <Heading
                    :title="props.link.title || props.link.slug"
                    :description="props.link.destination"
                />
            </div>
            <Badge
                :variant="props.link.status === 'live' ? 'default' : 'secondary'"
            >
                {{ statusLabels[props.link.status] }}
            </Badge>
        </div>

        <div class="flex flex-wrap items-center gap-2 rounded-xl border bg-muted/30 p-3">
            <code class="min-w-0 flex-1 truncate text-sm">
                {{ props.link.short_url }}
            </code>
            <Badge v-if="props.link.protected" variant="outline">
                <Lock class="size-3" />
                Password
            </Badge>
            <Button size="sm" variant="outline" type="button" @click="copy">
                <Check v-if="copied" class="size-4" />
                <Copy v-else class="size-4" />
                {{ copied ? 'Copied' : 'Copy' }}
            </Button>
            <Button size="sm" variant="outline" as-child>
                <a :href="`/short-links/${props.link.id}/qr?download=1`">
                    <QrCode class="size-4" />
                    QR code
                </a>
            </Button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Card>
                <CardContent class="flex items-center gap-3 p-4">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-muted">
                        <MousePointerClick class="size-4" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Total clicks</p>
                        <p class="text-xl font-semibold">{{ props.totals.clicks }}</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-3 p-4">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-muted">
                        <Users class="size-4" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Unique visitors</p>
                        <p class="text-xl font-semibold">
                            {{ props.totals.visitors }}
                        </p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-3 p-4">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-muted">
                        <MonitorSmartphone class="size-4" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Today</p>
                        <p class="text-xl font-semibold">{{ props.totals.today }}</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-3 p-4">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-muted">
                        <Globe2 class="size-4" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Last 7 days</p>
                        <p class="text-xl font-semibold">{{ props.totals.week }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardContent class="space-y-3 p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold">Clicks in the last 30 days</h2>
                    <p class="text-xs text-muted-foreground">
                        {{ props.totals.week }} in the last 7 days
                    </p>
                </div>

                <div class="flex h-44 items-end gap-1">
                    <div
                        v-for="point in props.series"
                        :key="point.date"
                        class="group flex h-full flex-1 items-end"
                        :title="`${point.date}: ${point.clicks} clicks`"
                    >
                        <div
                            class="w-full rounded-t bg-primary/70 transition-colors group-hover:bg-primary"
                            :class="{ 'opacity-40': point.clicks === 0 }"
                            :style="{ height: barHeight(point.clicks) }"
                        />
                    </div>
                </div>

                <div class="flex justify-between text-xs text-muted-foreground">
                    <span>{{ formatDate(props.series[0]?.date ?? '') }}</span>
                    <span>
                        {{ formatDate(props.series[props.series.length - 1]?.date ?? '') }}
                    </span>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-4 lg:grid-cols-3">
            <Card>
                <CardContent class="space-y-4 p-5">
                    <h2 class="text-sm font-semibold">Top sources</h2>
                    <p v-if="!props.referrers.length" class="text-sm text-muted-foreground">
                        No referrers recorded yet.
                    </p>
                    <div v-for="row in props.referrers" :key="row.source" class="space-y-1">
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="truncate">{{ row.source }}</span>
                            <span class="text-muted-foreground">{{ row.clicks }}</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded bg-muted">
                            <div
                                class="h-full rounded bg-primary"
                                :style="{ width: percent(row.clicks, referrerTotal) }"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="space-y-4 p-5">
                    <h2 class="text-sm font-semibold">Devices</h2>
                    <p v-if="!props.devices.length" class="text-sm text-muted-foreground">
                        No clicks recorded yet.
                    </p>
                    <div v-for="row in props.devices" :key="row.device" class="space-y-1">
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="capitalize">{{ row.device }}</span>
                            <span class="text-muted-foreground">{{ row.clicks }}</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded bg-muted">
                            <div
                                class="h-full rounded bg-primary"
                                :style="{ width: percent(row.clicks, deviceTotal) }"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="space-y-4 p-5">
                    <h2 class="text-sm font-semibold">Countries</h2>
                    <p v-if="!props.countries.length" class="text-sm text-muted-foreground">
                        Country data appears when your CDN forwards it.
                    </p>
                    <div v-for="row in props.countries" :key="row.country" class="space-y-1">
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span>{{ row.country }}</span>
                            <span class="text-muted-foreground">{{ row.clicks }}</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded bg-muted">
                            <div
                                class="h-full rounded bg-primary"
                                :style="{ width: percent(row.clicks, countryTotal) }"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardContent class="p-0">
                <h2 class="border-b p-5 text-sm font-semibold">Recent clicks</h2>
                <p v-if="!props.recent.length" class="p-5 text-sm text-muted-foreground">
                    Nobody has opened this link yet.
                </p>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b text-left text-xs text-muted-foreground">
                            <tr>
                                <th class="px-5 py-2 font-medium">When</th>
                                <th class="px-5 py-2 font-medium">Source</th>
                                <th class="px-5 py-2 font-medium">Device</th>
                                <th class="px-5 py-2 font-medium">Country</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="click in props.recent"
                                :key="click.id"
                                class="border-b last:border-b-0"
                            >
                                <td class="px-5 py-2 whitespace-nowrap">
                                    {{ formatDateTime(click.clicked_at) }}
                                </td>
                                <td class="max-w-64 truncate px-5 py-2 text-muted-foreground">
                                    {{ click.referrer || 'Direct' }}
                                </td>
                                <td class="px-5 py-2 capitalize">
                                    {{ click.device || 'unknown' }}
                                </td>
                                <td class="px-5 py-2">{{ click.country || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
