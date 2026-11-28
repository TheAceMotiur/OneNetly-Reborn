<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    Beaker,
    Calendar,
    Copy,
    ExternalLink,
    Info,
    MousePointerClick,
    Pencil,
    Plus,
    Tags,
    Trash2,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { linkIcon, linkIconLabels } from '@/lib/link-icons';
import {
    destroy as destroyLink,
    duplicate as duplicateLink,
    index as linksIndex,
    reorder,
    store,
    update,
} from '@/routes/links';

type LinkVariant = {
    id: number;
    label: string;
    url: string;
    weight: number;
    clicks: number;
    clicks_week: number;
    is_active: boolean;
};

type LinkItem = {
    id: number;
    title: string;
    url: string;
    icon: string;
    is_active: boolean;
    status: 'live' | 'paused' | 'scheduled' | 'expired';
    clicks: number;
    clicks_week: number;
    starts_at: string | null;
    ends_at: string | null;
    variants: LinkVariant[];
};

const props = defineProps<{
    links: LinkItem[];
    icons: string[];
    stats: {
        total: number;
        active: number;
        clicks: number;
        clicksWeek: number;
    };
    publicUrl: string;
    isPublished: boolean;
    pages: { username: string; name: string; role: string }[];
    activePage: string;
    canEdit: boolean;
    limits: {
        used: number;
        limit: number | null;
        plan: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Links',
                href: linksIndex(),
            },
        ],
    },
});

const atLimit = computed(
    () =>
        props.limits.limit !== null &&
        props.limits.used >= props.limits.limit,
);

const ownUsername = computed(
    () => props.pages.find((page) => page.role === 'owner')?.username ?? '',
);

const pageSuffix = computed(() =>
    props.activePage === ownUsername.value
        ? ''
        : `?page=${encodeURIComponent(props.activePage)}`,
);

function switchPage(event: Event): void {
    const username = (event.target as HTMLSelectElement).value;

    router.visit(`/links?page=${encodeURIComponent(username)}`);
}

const statusLabels: Record<LinkItem['status'], string> = {
    live: 'Live',
    paused: 'Paused',
    scheduled: 'Scheduled',
    expired: 'Expired',
};

const inputClass =
    'flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm';

function toLocalInput(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const pad = (part: number): string => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

const newLink = reactive({
    title: '',
    url: '',
    icon: '',
    starts_at: '',
    ends_at: '',
});

const createErrors = ref<Record<string, string>>({});
const creating = ref(false);

const editingId = ref<number | null>(null);

const draft = reactive({
    title: '',
    url: '',
    icon: '',
    starts_at: '',
    ends_at: '',
});

const editErrors = ref<Record<string, string>>({});
const saving = ref(false);

const variantsLinkId = ref<number | null>(null);
const variantDraft = reactive({ label: '', url: '', weight: 50 });
const variantErrors = ref<Record<string, string>>({});
const savingVariant = ref(false);

const utmLinkId = ref<number | null>(null);
const utmDraft = reactive({
    source: '',
    medium: '',
    campaign: '',
    term: '',
    content: '',
});

function variantsManagement(link: LinkItem): void {
    variantsLinkId.value = variantsLinkId.value === link.id ? null : link.id;
    utmLinkId.value = null;
    variantErrors.value = {};
}

function utmBuilder(link: LinkItem): void {
    utmLinkId.value = utmLinkId.value === link.id ? null : link.id;
    variantsLinkId.value = null;
}

function utmUrl(link: LinkItem): string {
    try {
        const url = new URL(link.url);

        const params: [string, string][] = [
            ['utm_source', utmDraft.source],
            ['utm_medium', utmDraft.medium],
            ['utm_campaign', utmDraft.campaign],
            ['utm_term', utmDraft.term],
            ['utm_content', utmDraft.content],
        ];

        params.forEach(([key, value]) => {
            if (value.trim() !== '') {
                url.searchParams.set(key, value.trim());
            }
        });

        return url.toString();
    } catch {
        return link.url;
    }
}

async function copyUtm(link: LinkItem): Promise<void> {
    try {
        await navigator.clipboard.writeText(utmUrl(link));
    } catch {
        // Clipboard access can be blocked.
    }
}

function applyUtm(link: LinkItem): void {
    router.patch(
        `${update.url(link.id)}${pageSuffix.value}`,
        {
            title: link.title,
            url: utmUrl(link),
            icon: link.icon,
            is_active: link.is_active,
            starts_at: link.starts_at,
            ends_at: link.ends_at,
        },
        { preserveScroll: true },
    );
}

function addVariant(link: LinkItem): void {
    router.post(
        `/links/${link.id}/variants${pageSuffix.value}`,
        {
            label: variantDraft.label,
            url: variantDraft.url,
            weight: variantDraft.weight,
        },
        {
            preserveScroll: true,
            onStart: () => {
                savingVariant.value = true;
            },
            onFinish: () => {
                savingVariant.value = false;
            },
            onError: (errors) => {
                variantErrors.value = errors;
            },
            onSuccess: () => {
                variantErrors.value = {};
                variantDraft.label = '';
                variantDraft.url = '';
                variantDraft.weight = 50;
            },
        },
    );
}

function toggleVariant(link: LinkItem, variant: LinkVariant): void {
    router.patch(
        `/links/${link.id}/variants/${variant.id}${pageSuffix.value}`,
        {
            label: variant.label,
            url: variant.url,
            weight: variant.weight,
            is_active: !variant.is_active,
        },
        { preserveScroll: true },
    );
}

function removeVariant(link: LinkItem, variant: LinkVariant): void {
    if (!confirm(`Remove variant "${variant.label}"?`)) {
        return;
    }

    router.delete(
        `/links/${link.id}/variants/${variant.id}${pageSuffix.value}`,
        { preserveScroll: true },
    );
}

function addLink(): void {
    router.post(
        `${store.url()}${pageSuffix.value}`,
        {
            title: newLink.title,
            url: newLink.url,
            icon: newLink.icon || null,
            starts_at: newLink.starts_at || null,
            ends_at: newLink.ends_at || null,
        },
        {
            preserveScroll: true,
            onStart: () => {
                creating.value = true;
            },
            onFinish: () => {
                creating.value = false;
            },
            onError: (errors) => {
                createErrors.value = errors;
            },
            onSuccess: () => {
                createErrors.value = {};
                newLink.title = '';
                newLink.url = '';
                newLink.icon = '';
                newLink.starts_at = '';
                newLink.ends_at = '';
            },
        },
    );
}

function startEditing(link: LinkItem): void {
    editingId.value = link.id;
    draft.title = link.title;
    draft.url = link.url;
    draft.icon = link.icon;
    draft.starts_at = toLocalInput(link.starts_at);
    draft.ends_at = toLocalInput(link.ends_at);
    editErrors.value = {};
}

function cancelEditing(): void {
    editingId.value = null;
    editErrors.value = {};
}

function saveEditing(link: LinkItem): void {
    router.patch(
        `${update.url(link.id)}${pageSuffix.value}`,
        {
            title: draft.title,
            url: draft.url,
            icon: draft.icon || null,
            is_active: link.is_active,
            starts_at: draft.starts_at || null,
            ends_at: draft.ends_at || null,
        },
        {
            preserveScroll: true,
            onStart: () => {
                saving.value = true;
            },
            onFinish: () => {
                saving.value = false;
            },
            onError: (errors) => {
                editErrors.value = errors;
            },
            onSuccess: () => {
                editErrors.value = {};
                editingId.value = null;
            },
        },
    );
}

function toggleActive(link: LinkItem): void {
    router.patch(
        `${update.url(link.id)}${pageSuffix.value}`,
        {
            title: link.title,
            url: link.url,
            icon: link.icon,
            is_active: !link.is_active,
            starts_at: link.starts_at,
            ends_at: link.ends_at,
        },
        { preserveScroll: true },
    );
}

function move(index: number, direction: -1 | 1): void {
    const target = index + direction;

    if (target < 0 || target >= props.links.length) {
        return;
    }

    const ids = props.links.map((link) => link.id);
    [ids[index], ids[target]] = [ids[target], ids[index]];

    router.patch(
        `${reorder.url()}${pageSuffix.value}`,
        { ids },
        { preserveScroll: true },
    );
}

function duplicate(link: LinkItem): void {
    router.post(
        `${duplicateLink.url(link.id)}${pageSuffix.value}`,
        {},
        { preserveScroll: true },
    );
}

function removeLink(link: LinkItem): void {
    if (!confirm(`Delete "${link.title}"?`)) {
        return;
    }

    router.delete(`${destroyLink.url(link.id)}${pageSuffix.value}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Links" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Links"
                description="Add, schedule, reorder and pause the links on your bio page"
            />
            <div class="flex flex-wrap items-center gap-2">
                <select
                    v-if="props.pages.length > 1"
                    :value="props.activePage"
                    class="h-9 max-w-56 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring"
                    aria-label="Switch page"
                    @change="switchPage"
                >
                    <option
                        v-for="page in props.pages"
                        :key="page.username"
                        :value="page.username"
                    >
                        {{ page.name }} ({{ page.role }})
                    </option>
                </select>
                <Button variant="outline" as-child>
                    <a :href="props.publicUrl" target="_blank">
                        <ExternalLink class="size-4" />
                        View my page
                    </a>
                </Button>
            </div>
        </div>

        <p
            v-if="!props.isPublished"
            class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm"
        >
            Your bio page is hidden. Publish it in
            <a href="/bio" class="font-medium underline underline-offset-4">
                My page
            </a>
            to share these links.
        </p>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm font-medium"
                        >Total links</CardTitle
                    >
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ props.stats.total }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm font-medium"
                        >Active links</CardTitle
                    >
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ props.stats.active }}
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
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-medium"
                        >Last 7 days</CardTitle
                    >
                    <Calendar class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ props.stats.clicksWeek }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <p
            class="flex items-start gap-2 rounded-lg border bg-muted/40 px-4 py-3 text-sm text-muted-foreground"
        >
            <Info class="mt-0.5 size-4 shrink-0" />
            <span>
                Your own clicks are never counted. To check the counters, open
                your bio page in a private window or on another device and tap a
                link there.
            </span>
        </p>

        <Card v-if="props.canEdit">
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle class="text-base">Add a link</CardTitle>
                <span class="text-xs text-muted-foreground">
                    {{ props.limits.used }} /
                    {{ props.limits.limit ?? '∞' }} links
                    <template v-if="props.limits.plan">
                        · {{ props.limits.plan }} plan
                    </template>
                </span>
            </CardHeader>
            <CardContent>
                <form
                    class="grid gap-4 sm:grid-cols-2"
                    @submit.prevent="addLink"
                >
                    <div class="grid gap-2">
                        <Label for="new-title">Title</Label>
                        <input
                            id="new-title"
                            v-model="newLink.title"
                            :class="inputClass"
                            placeholder="My portfolio"
                        />
                        <InputError :message="createErrors.title" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="new-url">URL</Label>
                        <input
                            id="new-url"
                            v-model="newLink.url"
                            :class="inputClass"
                            placeholder="https://example.com"
                        />
                        <InputError :message="createErrors.url" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="new-icon">Icon</Label>
                        <select
                            id="new-icon"
                            v-model="newLink.icon"
                            :class="inputClass"
                        >
                            <option value="">Detect automatically</option>
                            <option
                                v-for="icon in props.icons"
                                :key="icon"
                                :value="icon"
                            >
                                {{ linkIconLabels[icon] ?? icon }}
                            </option>
                        </select>
                        <InputError :message="createErrors.icon" />
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="new-starts">Show from</Label>
                            <input
                                id="new-starts"
                                v-model="newLink.starts_at"
                                type="datetime-local"
                                :class="inputClass"
                            />
                            <InputError :message="createErrors.starts_at" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="new-ends">Show until</Label>
                            <input
                                id="new-ends"
                                v-model="newLink.ends_at"
                                type="datetime-local"
                                :class="inputClass"
                            />
                            <InputError :message="createErrors.ends_at" />
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 sm:col-span-2">
                        <Button
                            type="submit"
                            :disabled="creating || atLimit"
                        >
                            <Plus class="size-4" />
                            {{ atLimit ? 'Link limit reached' : 'Add link' }}
                        </Button>
                        <p class="text-xs text-muted-foreground">
                            Leave the schedule empty to keep the link live all
                            the time.
                        </p>
                        <p
                            v-if="atLimit"
                            class="w-full rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs"
                        >
                            Your plan includes {{ props.limits.limit }} links.
                            <a
                                href="/pricing"
                                class="font-medium underline underline-offset-4"
                            >
                                Upgrade for more
                            </a>
                        </p>
                        <p v-if="createErrors.limit" class="w-full text-xs text-red-600">
                            {{ createErrors.limit }}
                        </p>
                    </div>
                </form>
            </CardContent>
        </Card>

        <div
            class="divide-y rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <div
                v-for="(link, index) in props.links"
                :key="link.id"
                class="p-4"
            >
                <div v-if="editingId === link.id" class="space-y-3">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1">
                            <Label :for="`edit-title-${link.id}`">Title</Label>
                            <input
                                :id="`edit-title-${link.id}`"
                                v-model="draft.title"
                                :class="inputClass"
                            />
                            <InputError :message="editErrors.title" />
                        </div>
                        <div class="grid gap-1">
                            <Label :for="`edit-url-${link.id}`">URL</Label>
                            <input
                                :id="`edit-url-${link.id}`"
                                v-model="draft.url"
                                :class="inputClass"
                            />
                            <InputError :message="editErrors.url" />
                        </div>
                        <div class="grid gap-1">
                            <Label :for="`edit-icon-${link.id}`">Icon</Label>
                            <select
                                :id="`edit-icon-${link.id}`"
                                v-model="draft.icon"
                                :class="inputClass"
                            >
                                <option
                                    v-for="icon in props.icons"
                                    :key="icon"
                                    :value="icon"
                                >
                                    {{ linkIconLabels[icon] ?? icon }}
                                </option>
                            </select>
                            <InputError :message="editErrors.icon" />
                        </div>
                        <div class="grid gap-1 sm:grid-cols-2">
                            <div class="grid gap-1">
                                <Label :for="`edit-starts-${link.id}`"
                                    >Show from</Label
                                >
                                <input
                                    :id="`edit-starts-${link.id}`"
                                    v-model="draft.starts_at"
                                    type="datetime-local"
                                    :class="inputClass"
                                />
                                <InputError :message="editErrors.starts_at" />
                            </div>
                            <div class="grid gap-1">
                                <Label :for="`edit-ends-${link.id}`"
                                    >Show until</Label
                                >
                                <input
                                    :id="`edit-ends-${link.id}`"
                                    v-model="draft.ends_at"
                                    type="datetime-local"
                                    :class="inputClass"
                                />
                                <InputError :message="editErrors.ends_at" />
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            size="sm"
                            :disabled="saving"
                            @click="saveEditing(link)"
                        >
                            Save
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            @click="cancelEditing"
                        >
                            Cancel
                        </Button>
                    </div>
                </div>

                <div v-else class="flex flex-wrap items-center gap-3">
                    <div class="flex flex-col gap-1">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-6"
                            :disabled="index === 0"
                            aria-label="Move up"
                            @click="move(index, -1)"
                        >
                            <ArrowUp class="size-3.5" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-6"
                            :disabled="index === props.links.length - 1"
                            aria-label="Move down"
                            @click="move(index, 1)"
                        >
                            <ArrowDown class="size-3.5" />
                        </Button>
                    </div>

                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-full border bg-muted/40"
                    >
                        <component :is="linkIcon(link.icon)" class="size-4" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium">{{ link.title }}</p>
                            <Badge
                                :variant="
                                    link.status === 'live'
                                        ? 'default'
                                        : 'secondary'
                                "
                            >
                                {{ statusLabels[link.status] }}
                            </Badge>
                        </div>
                        <a
                            :href="link.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="block truncate text-sm text-muted-foreground underline-offset-4 hover:underline"
                        >
                            {{ link.url }}
                        </a>
                    </div>

                    <div
                        class="flex items-center gap-1 text-sm text-muted-foreground"
                        :title="`${link.clicks} clicks in total, ${link.clicks_week} in the last 7 days`"
                    >
                        <MousePointerClick class="size-4" />
                        {{ link.clicks }}
                        <span v-if="link.clicks_week" class="text-xs">
                            (+{{ link.clicks_week }})
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Badge
                            v-if="link.variants.length"
                            variant="secondary"
                        >
                            A/B · {{ link.variants.length }}
                        </Badge>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="toggleActive(link)"
                        >
                            {{ link.is_active ? 'Pause' : 'Activate' }}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="startEditing(link)"
                        >
                            <Pencil class="size-4" />
                            Edit
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="variantsManagement(link)"
                        >
                            <Beaker class="size-4" />
                            A/B
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="utmBuilder(link)"
                        >
                            <Tags class="size-4" />
                            UTM
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            aria-label="Duplicate link"
                            @click="duplicate(link)"
                        >
                            <Copy class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-destructive"
                            @click="removeLink(link)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>

                <div
                    v-if="utmLinkId === link.id"
                    class="mt-4 space-y-3 rounded-lg border bg-muted/20 p-4"
                >
                    <div class="flex items-center gap-2 text-sm font-semibold">
                        <Tags class="size-4" />
                        UTM builder
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="grid gap-1">
                            <Label :for="`utm-source-${link.id}`">Source</Label>
                            <input
                                :id="`utm-source-${link.id}`"
                                v-model="utmDraft.source"
                                :class="inputClass"
                                placeholder="newsletter"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label :for="`utm-medium-${link.id}`">Medium</Label>
                            <input
                                :id="`utm-medium-${link.id}`"
                                v-model="utmDraft.medium"
                                :class="inputClass"
                                placeholder="email"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label :for="`utm-campaign-${link.id}`">Campaign</Label>
                            <input
                                :id="`utm-campaign-${link.id}`"
                                v-model="utmDraft.campaign"
                                :class="inputClass"
                                placeholder="spring-launch"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label :for="`utm-term-${link.id}`">Term</Label>
                            <input
                                :id="`utm-term-${link.id}`"
                                v-model="utmDraft.term"
                                :class="inputClass"
                                placeholder="portfolio"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label :for="`utm-content-${link.id}`">Content</Label>
                            <input
                                :id="`utm-content-${link.id}`"
                                v-model="utmDraft.content"
                                :class="inputClass"
                                placeholder="button-a"
                            />
                        </div>
                    </div>
                    <p class="truncate rounded-md border bg-background px-3 py-2 font-mono text-xs">
                        {{ utmUrl(link) }}
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button size="sm" @click="applyUtm(link)">
                            Save on this link
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="copyUtm(link)"
                        >
                            <Copy class="size-4" />
                            Copy tagged URL
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="utmLinkId = null"
                        >
                            Close
                        </Button>
                    </div>
                </div>

                <div
                    v-if="variantsLinkId === link.id"
                    class="mt-4 space-y-3 rounded-lg border bg-muted/20 p-4"
                >
                    <div class="flex items-center gap-2 text-sm font-semibold">
                        <Beaker class="size-4" />
                        A/B test
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Visitors are split by weight between the active variant
                        URLs. Leave every variant paused to use the link URL.
                    </p>

                    <div
                        v-for="variant in link.variants"
                        :key="variant.id"
                        class="flex flex-wrap items-center gap-2 rounded-md border bg-background px-3 py-2 text-sm"
                    >
                        <span class="font-medium">{{ variant.label }}</span>
                        <span class="text-xs text-muted-foreground">
                            weight {{ variant.weight }} · {{ variant.clicks }} clicks
                        </span>
                        <a
                            :href="variant.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="min-w-0 flex-1 truncate text-xs text-muted-foreground underline-offset-4 hover:underline"
                        >
                            {{ variant.url }}
                        </a>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="toggleVariant(link, variant)"
                        >
                            {{ variant.is_active ? 'Pause' : 'Activate' }}
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-destructive"
                            @click="removeVariant(link, variant)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>

                    <form
                        class="grid gap-2 sm:grid-cols-[1fr_1.4fr_100px_auto] sm:items-end"
                        @submit.prevent="addVariant(link)"
                    >
                        <div class="grid gap-1">
                            <Label :for="`variant-label-${link.id}`">Name</Label>
                            <input
                                :id="`variant-label-${link.id}`"
                                v-model="variantDraft.label"
                                :class="inputClass"
                                placeholder="Landing page"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label :for="`variant-url-${link.id}`">URL</Label>
                            <input
                                :id="`variant-url-${link.id}`"
                                v-model="variantDraft.url"
                                :class="inputClass"
                                placeholder="https://…"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label :for="`variant-weight-${link.id}`">Weight</Label>
                            <input
                                :id="`variant-weight-${link.id}`"
                                v-model.number="variantDraft.weight"
                                type="number"
                                min="1"
                                max="100"
                                :class="inputClass"
                            />
                        </div>
                        <Button type="submit" :disabled="savingVariant">
                            <Plus class="size-4" />
                            Add
                        </Button>
                    </form>
                    <p v-if="variantErrors.label" class="text-xs text-red-600">
                        {{ variantErrors.label }}
                    </p>
                    <p v-if="variantErrors.url" class="text-xs text-red-600">
                        {{ variantErrors.url }}
                    </p>
                </div>
            </div>

            <p
                v-if="props.links.length === 0"
                class="px-4 py-10 text-center text-sm text-muted-foreground"
            >
                You have no links yet. Add your first one above.
            </p>
        </div>
    </div>
</template>
