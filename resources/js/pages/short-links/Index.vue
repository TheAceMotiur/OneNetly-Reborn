<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BarChart3,
    CalendarClock,
    Check,
    Copy,
    ExternalLink,
    Link2,
    Lock,
    MousePointerClick,
    Pause,
    Play,
    Plus,
    QrCode,
    Search,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import CreateForm from './CreateForm.vue';
import EditLinkForm from './EditLinkForm.vue';

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
    links: ShortLinkItem[];
    stats: {
        links: number;
        live: number;
        clicks: number;
        clicks_week: number;
        visitors_week: number;
    };
    filters: { q: string; status: string };
    statuses: ShortLinkStatus[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Short links', href: '/short-links' }],
    },
});

const statusLabels: Record<ShortLinkStatus, string> = {
    live: 'Live',
    paused: 'Paused',
    expired: 'Expired',
    exhausted: 'Limit reached',
};

const statusVariants: Record<
    ShortLinkStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    live: 'default',
    paused: 'secondary',
    expired: 'destructive',
    exhausted: 'destructive',
};

const filterForm = reactive({
    q: props.filters.q,
    status: props.filters.status,
});

const createOpen = ref(props.links.length === 0);
const copiedId = ref<number | null>(null);
const editingId = ref<number | null>(null);

const atCap = computed(() =>
    props.links.some(
        (link) => link.max_clicks !== null && link.clicks >= link.max_clicks,
    ),
);

function applyFilters(): void {
    router.get(
        '/short-links',
        {
            q: filterForm.q || undefined,
            status: filterForm.status || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function clearFilters(): void {
    filterForm.q = '';
    filterForm.status = '';
    applyFilters();
}

function toggleActive(link: ShortLinkItem): void {
    router.patch(
        `/short-links/${link.id}`,
        {
            title: link.title ?? '',
            destination: link.destination,
            slug: link.slug,
            mobile_destination: link.mobile_destination ?? '',
            expires_at: toLocalInput(link.expires_at),
            max_clicks: link.max_clicks ?? '',
            is_active: !link.is_active,
            utm_source: link.utm.source ?? '',
            utm_medium: link.utm.medium ?? '',
            utm_campaign: link.utm.campaign ?? '',
            utm_term: link.utm.term ?? '',
            utm_content: link.utm.content ?? '',
        },
        { preserveScroll: true },
    );
}

function destroy(link: ShortLinkItem): void {
    if (
        !window.confirm(
            `Delete “${link.title || link.slug}”? Its click history is removed too.`,
        )
    ) {
        return;
    }

    router.delete(`/short-links/${link.id}`, { preserveScroll: true });
}

async function copy(link: ShortLinkItem): Promise<void> {
    try {
        await navigator.clipboard.writeText(link.short_url);
        copiedId.value = link.id;
        window.setTimeout(() => {
            copiedId.value = null;
        }, 2000);
    } catch {
        copiedId.value = null;
    }
}

function toLocalInput(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const pad = (part: number): string => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}
</script>

<template>
    <Head title="Short links" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="Short links"
                description="Turn long URLs into short, tracked links with passwords, expiry dates, click limits, device targeting and campaign parameters"
            />
            <Button type="button" @click="createOpen = !createOpen">
                <Plus class="size-4" />
                {{ createOpen ? 'Close form' : 'New short link' }}
            </Button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Card>
                <CardContent class="flex items-center gap-3 p-4">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-muted">
                        <Link2 class="size-4" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Links</p>
                        <p class="text-xl font-semibold">{{ props.stats.links }}</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-3 p-4">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-muted">
                        <ExternalLink class="size-4" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Live</p>
                        <p class="text-xl font-semibold">{{ props.stats.live }}</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-3 p-4">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-muted">
                        <MousePointerClick class="size-4" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Clicks this week</p>
                        <p class="text-xl font-semibold">
                            {{ props.stats.clicks_week }}
                        </p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-3 p-4">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-muted">
                        <Users class="size-4" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Visitors this week</p>
                        <p class="text-xl font-semibold">
                            {{ props.stats.visitors_week }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card v-if="createOpen">
            <CardContent class="p-5">
                <CreateForm @done="createOpen = false" />
            </CardContent>
        </Card>

        <form class="flex flex-wrap items-end gap-2" @submit.prevent="applyFilters">
            <div class="relative min-w-56 flex-1">
                <Search class="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                <Input
                    v-model="filterForm.q"
                    class="pl-8"
                    placeholder="Search links, slugs or destinations"
                />
            </div>
            <select
                v-model="filterForm.status"
                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
            >
                <option value="">All statuses</option>
                <option v-for="status in props.statuses" :key="status" :value="status">
                    {{ statusLabels[status] }}
                </option>
            </select>
            <Button type="submit" variant="outline">Filter</Button>
            <Button
                v-if="props.filters.q || props.filters.status"
                type="button"
                variant="ghost"
                @click="clearFilters"
            >
                Clear
            </Button>
        </form>

        <div
            v-if="atCap"
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
        >
            One or more links reached their click limit. Raise the limit or
            create a new link to keep the destination reachable.
        </div>

        <section
            v-if="props.links.length"
            class="divide-y rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <div v-for="link in props.links" :key="link.id">
                <div class="flex flex-wrap items-start gap-3 p-4">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted">
                        <Link2 class="size-5 text-muted-foreground" />
                    </div>

                    <div class="min-w-0 flex-1 space-y-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium">
                                {{ link.title || link.slug }}
                            </p>
                            <Badge :variant="statusVariants[link.status]">
                                {{ statusLabels[link.status] }}
                            </Badge>
                            <Badge v-if="link.protected" variant="outline">
                                <Lock class="size-3" />
                                Password
                            </Badge>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <code class="rounded bg-muted px-1.5 py-0.5 text-xs">
                                {{ link.short_url }}
                            </code>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                                @click="copy(link)"
                            >
                                <Check v-if="copiedId === link.id" class="size-3" />
                                <Copy v-else class="size-3" />
                                {{ copiedId === link.id ? 'Copied' : 'Copy' }}
                            </button>
                        </div>

                        <p class="truncate text-xs text-muted-foreground">
                            {{ link.destination }}
                        </p>

                        <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                            <span class="inline-flex items-center gap-1">
                                <MousePointerClick class="size-3" />
                                {{ link.clicks }} clicks · {{ link.clicks_week }} this week
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <Users class="size-3" />
                                {{ link.visitors_week }} visitors this week
                            </span>
                            <span v-if="link.expires_at" class="inline-flex items-center gap-1">
                                <CalendarClock class="size-3" />
                                Expires {{ formatDate(link.expires_at) }}
                            </span>
                            <span v-if="link.max_clicks">
                                Limit {{ link.clicks }}/{{ link.max_clicks }}
                            </span>
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5">
                        <Button size="sm" variant="outline" as-child>
                            <Link :href="`/short-links/${link.id}`">
                                <BarChart3 class="size-4" />
                                Analytics
                            </Link>
                        </Button>
                        <Button size="sm" variant="outline" as-child>
                            <a :href="`/short-links/${link.id}/qr?download=1`">
                                <QrCode class="size-4" />
                                QR
                            </a>
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            type="button"
                            @click="toggleActive(link)"
                        >
                            <component
                                :is="link.is_active ? Pause : Play"
                                class="size-4"
                            />
                            {{ link.is_active ? 'Pause' : 'Resume' }}
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            type="button"
                            @click="editingId = editingId === link.id ? null : link.id"
                        >
                            Edit
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            type="button"
                            class="text-destructive"
                            @click="destroy(link)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>

                <EditLinkForm
                    v-if="editingId === link.id"
                    :link="link"
                    @close="editingId = null"
                />
            </div>
        </section>

        <section v-else class="rounded-xl border bg-muted/30 px-4 py-8 text-center">
            <p class="font-medium">No short links yet</p>
            <p class="mx-auto mt-1 max-w-md text-sm text-muted-foreground">
                Shorten a long URL, then share the short address anywhere. Every
                click is counted, and you can pause, password-protect or expire
                the link at any time.
            </p>
            <Button class="mt-4" type="button" @click="createOpen = true">
                <Plus class="size-4" />
                Create your first short link
            </Button>
        </section>
    </div>
</template>
