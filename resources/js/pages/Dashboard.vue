<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Check,
    Copy,
    ExternalLink,
    Eye,
    Info,
    Link2,
    MousePointerClick,
    Pencil,
    Percent,
    Plus,
    QrCode,
    TrendingUp,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { dashboard } from '@/routes';
import { edit as bioEdit } from '@/routes/bio';
import { index as linksIndex } from '@/routes/links';

type DashboardProps = {
    profile: {
        username: string;
        display_name: string;
        bio: string | null;
        avatar_url: string | null;
        theme: string;
        template: string;
        is_published: boolean;
        public_url: string;
    };
    stats: {
        total: number;
        active: number;
        clicks: number;
        clicksWeek: number;
        views: number;
        viewsWeek: number;
        clickRate: number;
    };
    daily: {
        date: string;
        label: string;
        clicks: number;
    }[];
    topReferrers: {
        host: string;
        clicks: number;
    }[];
    topLinks: {
        id: number;
        title: string;
        clicks: number;
        clicks_week: number;
        is_active: boolean;
    }[];
    qrUrl: string;
};

const props = defineProps<DashboardProps>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const themeSwatches: Record<string, string> = {
    indigo: 'bg-indigo-500',
    emerald: 'bg-emerald-500',
    rose: 'bg-rose-500',
    amber: 'bg-amber-500',
    sky: 'bg-sky-500',
    slate: 'bg-slate-500',
};

const accent = computed(
    () => themeSwatches[props.profile.theme] ?? themeSwatches.indigo,
);

const initials = computed(() =>
    props.profile.display_name
        .split(' ')
        .map((part) => part.charAt(0))
        .join('')
        .slice(0, 2)
        .toUpperCase(),
);

const topClicks = computed(() =>
    Math.max(1, ...props.topLinks.map((link) => link.clicks)),
);

const dailyMax = computed(() =>
    Math.max(1, ...props.daily.map((day) => day.clicks)),
);

const copyState = ref<'idle' | 'copied' | 'failed'>('idle');

let copyResetTimer: ReturnType<typeof setTimeout> | undefined;

async function copyLink(): Promise<void> {
    const copied = await writeToClipboard(props.profile.public_url);

    copyState.value = copied ? 'copied' : 'failed';

    clearTimeout(copyResetTimer);
    copyResetTimer = setTimeout(() => {
        copyState.value = 'idle';
    }, 2500);
}

/**
 * Copy text to the clipboard, falling back to the legacy approach when the
 * async Clipboard API is unavailable (plain HTTP or older browsers).
 */
async function writeToClipboard(text: string): Promise<boolean> {
    if (window.isSecureContext && navigator.clipboard) {
        try {
            await navigator.clipboard.writeText(text);

            return true;
        } catch {
            // Fall through to the legacy approach below.
        }
    }

    const field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.top = '0';
    field.style.opacity = '0';
    document.body.appendChild(field);
    field.select();
    field.setSelectionRange(0, text.length);

    let copied = false;

    try {
        copied = document.execCommand('copy');
    } catch {
        copied = false;
    }

    field.remove();

    return copied;
}
</script>

<template>
    <Head title="Dashboard" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="Dashboard"
                description="Everything about your bio page in one place"
            />
            <div class="flex flex-wrap items-center gap-2">
                <Dialog>
                    <DialogTrigger as-child>
                        <Button variant="outline">
                            <QrCode class="size-4" />
                            QR code
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-sm">
                        <DialogHeader>
                            <DialogTitle>Scan to open your page</DialogTitle>
                            <DialogDescription>
                                Point a phone camera at the code to open @{{
                                    props.profile.username
                                }}.
                            </DialogDescription>
                        </DialogHeader>
                        <img
                            :src="props.qrUrl"
                            alt="QR code for your bio page"
                            class="mx-auto size-64 rounded-lg border bg-white p-2"
                        />
                        <DialogFooter>
                            <Button variant="outline" as-child>
                                <a :href="`${props.qrUrl}?download=1`">
                                    Download SVG
                                </a>
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
                <Button variant="outline" as-child>
                    <a :href="props.profile.public_url" target="_blank">
                        <ExternalLink class="size-4" />
                        View my page
                    </a>
                </Button>
                <Button as-child>
                    <Link :href="linksIndex()">
                        <Plus class="size-4" />
                        Add link
                    </Link>
                </Button>
            </div>
        </div>

        <p
            v-if="!props.profile.is_published"
            class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm"
        >
            Your bio page is hidden, so visitors cannot see it or your links.
            Publish it in
            <Link
                :href="bioEdit()"
                class="font-medium underline underline-offset-4"
            >
                My page
            </Link>
            to start sharing.
        </p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium">Links</CardTitle>
                    <Link2 class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ props.stats.total }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ props.stats.active }} live right now
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium"
                        >Total clicks</CardTitle
                    >
                    <MousePointerClick class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ props.stats.clicks }}
                    </p>
                    <p class="text-xs text-muted-foreground">All time</p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium"
                        >Last 7 days</CardTitle
                    >
                    <TrendingUp class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ props.stats.clicksWeek }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Clicks this week
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium">Page views</CardTitle>
                    <Eye class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ props.stats.views }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ props.stats.viewsWeek }} this week
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
                        Clicks per page view (7 days)
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium">Page</CardTitle>
                    <Badge
                        :variant="
                            props.profile.is_published ? 'default' : 'secondary'
                        "
                    >
                        {{ props.profile.is_published ? 'Public' : 'Hidden' }}
                    </Badge>
                </CardHeader>
                <CardContent>
                    <p class="truncate text-2xl font-semibold">
                        @{{ props.profile.username }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Your public username
                    </p>
                </CardContent>
            </Card>
        </div>

        <p
            class="flex items-start gap-2 rounded-lg border bg-muted/40 px-4 py-3 text-sm text-muted-foreground"
        >
            <Info class="mt-0.5 size-4 shrink-0" />
            <span>
                Your own visits and clicks are never counted, so nothing
                changes while you test your page. Open your bio page in a
                private window or on another device to see page views and
                clicks here.
            </span>
        </p>

        <Card>
            <CardHeader>
                <CardTitle class="text-base"
                    >Clicks in the last 7 days</CardTitle
                >
            </CardHeader>
            <CardContent>
                <div class="flex h-40 items-end gap-2">
                    <div
                        v-for="day in props.daily"
                        :key="day.date"
                        class="flex h-full flex-1 flex-col items-center justify-end gap-2"
                        :title="`${day.clicks} clicks on ${day.date}`"
                    >
                        <span class="text-xs text-muted-foreground">
                            {{ day.clicks }}
                        </span>
                        <div
                            :class="[accent]"
                            class="w-full rounded-t-md transition-all"
                            :style="{
                                height: `${(day.clicks / dailyMax) * 100}%`,
                                minHeight: day.clicks ? '4px' : '2px',
                                opacity: day.clicks ? 1 : 0.2,
                            }"
                        />
                        <span class="text-xs text-muted-foreground">
                            {{ day.label }}
                        </span>
                    </div>
                </div>

                <div v-if="props.topReferrers.length" class="mt-6 space-y-2">
                    <p
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Top referrers (30 days)
                    </p>
                    <ul class="space-y-1 text-sm">
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
                </div>
                <p v-else class="mt-6 text-xs text-muted-foreground">
                    No referrers recorded yet. Clicks from search engines,
                    social apps and other sites are counted here.
                </p>
            </CardContent>
        </Card>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Your page</CardTitle>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="flex items-center gap-4">
                        <div
                            :class="[accent]"
                            class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full text-lg font-semibold text-white"
                        >
                            <img
                                v-if="props.profile.avatar_url"
                                :src="props.profile.avatar_url"
                                alt=""
                                class="size-full object-cover"
                            />
                            <span v-else>{{ initials }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate font-semibold">
                                {{ props.profile.display_name }}
                            </p>
                            <a
                                :href="props.profile.public_url"
                                target="_blank"
                                class="truncate text-sm text-muted-foreground underline-offset-4 hover:underline"
                            >
                                {{ props.profile.public_url }}
                            </a>
                        </div>
                    </div>

                    <p
                        v-if="props.profile.bio"
                        class="text-sm text-muted-foreground"
                    >
                        {{ props.profile.bio }}
                    </p>
                    <p v-else class="text-sm text-muted-foreground">
                        You have not written a bio yet. A short introduction
                        helps visitors know what to expect.
                    </p>

                    <div class="space-y-2">
                        <div class="flex flex-wrap gap-2">
                            <Button variant="outline" as-child>
                                <Link :href="bioEdit()">
                                    <Pencil class="size-4" />
                                    Edit my page
                                </Link>
                            </Button>
                            <Button variant="outline" @click="copyLink">
                                <Check
                                    v-if="copyState === 'copied'"
                                    class="size-4"
                                />
                                <Copy v-else class="size-4" />
                                {{
                                    copyState === 'copied'
                                        ? 'Copied'
                                        : copyState === 'failed'
                                          ? 'Copy failed'
                                          : 'Copy link'
                                }}
                            </Button>
                        </div>
                        <p
                            v-if="copyState === 'failed'"
                            class="text-xs text-muted-foreground"
                        >
                            Your browser blocked the clipboard. Select the
                            address above to copy it manually.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="flex flex-col">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-base">Top links</CardTitle>
                    <Link
                        :href="linksIndex()"
                        class="inline-flex items-center gap-1 text-sm text-muted-foreground underline-offset-4 hover:underline"
                    >
                        Manage
                        <ArrowUpRight class="size-3.5" />
                    </Link>
                </CardHeader>
                <CardContent class="flex-1">
                    <ul v-if="props.topLinks.length" class="space-y-4">
                        <li
                            v-for="link in props.topLinks"
                            :key="link.id"
                            class="space-y-1.5"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <span class="truncate text-sm font-medium">
                                    {{ link.title }}
                                </span>
                                <span
                                    class="shrink-0 text-sm text-muted-foreground"
                                >
                                    {{ link.clicks }}
                                    <span
                                        v-if="link.clicks_week"
                                        class="text-xs text-muted-foreground"
                                    >
                                        (+{{ link.clicks_week }})
                                    </span>
                                </span>
                            </div>
                            <div
                                class="h-1.5 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    :class="[
                                        accent,
                                        link.is_active ? '' : 'opacity-40',
                                    ]"
                                    class="h-full rounded-full"
                                    :style="{
                                        width: `${(link.clicks / topClicks) * 100}%`,
                                    }"
                                />
                            </div>
                        </li>
                    </ul>
                    <div
                        v-else
                        class="flex h-full flex-col items-center justify-center gap-2 py-6 text-center"
                    >
                        <p class="text-sm text-muted-foreground">
                            No links yet. Add your first one to see it here.
                        </p>
                        <Button size="sm" variant="outline" as-child>
                            <Link :href="linksIndex()">
                                <Plus class="size-4" />
                                Add a link
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
