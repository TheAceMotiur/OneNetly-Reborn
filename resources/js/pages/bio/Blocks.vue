<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, ExternalLink, Plus, Trash2 } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';

type Block = {
    id: number;
    type: string;
    content: string | null;
    media_url: string | null;
    embed_url: string | null;
    is_active: boolean;
};

const props = defineProps<{
    blocks: Block[];
    types: Record<string, string>;
    pages: { username: string; name: string; role: string }[];
    activePage: string;
    publicUrl: string;
    canEdit: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'My page', href: '/bio' },
            { title: 'Blocks', href: '/bio/blocks' },
        ],
    },
});

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

    router.visit(`/bio/blocks?page=${encodeURIComponent(username)}`);
}

const form = reactive({
    type: 'heading',
    content: '',
    media_url: '',
});

const errors = ref<Record<string, string>>({});
const saving = ref(false);

const needsContent = computed(() =>
    ['heading', 'text'].includes(form.type),
);
const needsMedia = computed(() => ['image', 'video'].includes(form.type));

function addBlock(): void {
    router.post(
        `/bio/blocks${pageSuffix.value}`,
        {
            type: form.type,
            content: needsContent.value ? form.content : null,
            media_url: needsMedia.value ? form.media_url : null,
            is_active: true,
        },
        {
            preserveScroll: true,
            onStart: () => {
                saving.value = true;
            },
            onFinish: () => {
                saving.value = false;
            },
            onError: (value) => {
                errors.value = value;
            },
            onSuccess: () => {
                errors.value = {};
                form.content = '';
                form.media_url = '';
            },
        },
    );
}

function toggleBlock(block: Block): void {
    router.patch(
        `/bio/blocks/${block.id}${pageSuffix.value}`,
        {
            type: block.type,
            content: block.content,
            media_url: block.media_url,
            is_active: !block.is_active,
        },
        { preserveScroll: true },
    );
}

function removeBlock(block: Block): void {
    if (!confirm('Delete this block?')) {
        return;
    }

    router.delete(`/bio/blocks/${block.id}${pageSuffix.value}`, {
        preserveScroll: true,
    });
}

function move(index: number, direction: -1 | 1): void {
    const target = index + direction;

    if (target < 0 || target >= props.blocks.length) {
        return;
    }

    const ids = props.blocks.map((block) => block.id);
    [ids[index], ids[target]] = [ids[target], ids[index]];

    router.patch(
        `/bio/blocks/reorder${pageSuffix.value}`,
        { ids },
        { preserveScroll: true },
    );
}

const appName = computed(() => String(usePage().props.name ?? ''));
</script>

<template>
    <Head title="Page blocks" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Page blocks"
                description="Add headings, text, images and videos between your bio and your links"
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
                        View page
                    </a>
                </Button>
            </div>
        </div>

        <p
            v-if="!props.canEdit"
            class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm"
        >
            You have view-only access to this page.
        </p>

        <Card v-if="props.canEdit">
            <CardHeader>
                <CardTitle class="text-base">Add a block</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="addBlock">
                    <div class="grid gap-2">
                        <Label for="block-type">Type</Label>
                        <select
                            id="block-type"
                            v-model="form.type"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring"
                        >
                            <option
                                v-for="(label, value) in props.types"
                                :key="value"
                                :value="value"
                            >
                                {{ label }}
                            </option>
                        </select>
                        <InputError :message="errors.type" />
                    </div>

                    <div v-if="needsContent" class="grid gap-2 sm:col-span-2">
                        <Label for="block-content">
                            {{ form.type === 'heading' ? 'Heading' : 'Text' }}
                        </Label>
                        <textarea
                            id="block-content"
                            v-model="form.content"
                            rows="3"
                            maxlength="2000"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:border-ring"
                        />
                        <InputError :message="errors.content" />
                    </div>

                    <div v-if="needsMedia" class="grid gap-2 sm:col-span-2">
                        <Label for="block-media">
                            {{ form.type === 'image' ? 'Image URL' : 'Video URL' }}
                        </Label>
                        <input
                            id="block-media"
                            v-model="form.media_url"
                            type="url"
                            maxlength="2048"
                            placeholder="https://…"
                            class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring"
                        />
                        <p class="text-xs text-muted-foreground">
                            YouTube and Vimeo links play inside the page.
                        </p>
                        <InputError :message="errors.media_url" />
                    </div>

                    <div class="sm:col-span-2">
                        <Button type="submit" :disabled="saving">
                            <Plus class="size-4" />
                            Add block
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <div class="divide-y rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <div
                v-for="(block, index) in props.blocks"
                :key="block.id"
                class="flex flex-wrap items-start gap-3 p-4"
            >
                <div class="flex flex-col gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-6"
                        :disabled="index === 0 || !props.canEdit"
                        aria-label="Move up"
                        @click="move(index, -1)"
                    >
                        <ArrowUp class="size-3.5" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-6"
                        :disabled="index === props.blocks.length - 1 || !props.canEdit"
                        aria-label="Move down"
                        @click="move(index, 1)"
                    >
                        <ArrowDown class="size-3.5" />
                    </Button>
                </div>

                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge variant="secondary">
                            {{ props.types[block.type] ?? block.type }}
                        </Badge>
                        <Badge :variant="block.is_active ? 'default' : 'secondary'">
                            {{ block.is_active ? 'Visible' : 'Hidden' }}
                        </Badge>
                    </div>

                    <p
                        v-if="block.type === 'heading'"
                        class="truncate text-lg font-semibold"
                    >
                        {{ block.content }}
                    </p>
                    <p v-else-if="block.type === 'text'" class="text-sm whitespace-pre-line">
                        {{ block.content }}
                    </p>
                    <p v-else-if="block.type === 'divider'" class="text-sm text-muted-foreground">
                        A horizontal divider
                    </p>
                    <div v-else class="space-y-1">
                        <p class="truncate text-sm text-muted-foreground">
                            {{ block.media_url }}
                        </p>
                        <img
                            v-if="block.type === 'image' && block.media_url"
                            :src="block.media_url"
                            alt=""
                            class="h-24 w-full max-w-56 rounded-lg border object-cover"
                        />
                        <p v-else-if="block.embed_url" class="text-xs text-muted-foreground">
                            Plays {{ block.embed_url.includes('vimeo') ? 'Vimeo' : 'YouTube' }}
                            video · {{ appName }}
                        </p>
                    </div>
                </div>

                <div v-if="props.canEdit" class="flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="toggleBlock(block)">
                        {{ block.is_active ? 'Hide' : 'Show' }}
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="text-destructive"
                        aria-label="Delete block"
                        @click="removeBlock(block)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>

            <p
                v-if="props.blocks.length === 0"
                class="px-4 py-10 text-center text-sm text-muted-foreground"
            >
                No blocks yet. Your page shows only your bio and links.
            </p>
        </div>
    </div>
</template>
