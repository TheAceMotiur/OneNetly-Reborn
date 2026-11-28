<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowUp,
    Blocks,
    Check,
    Cookie,
    Copy,
    Gauge,
    Megaphone,
    MessageCircle,
    Pencil,
    Plus,
    Share2,
    ShieldAlert,
    Sparkles,
    Timer,
    Trash2,
} from '@lucide/vue';
import type { Component } from 'vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { WidgetDefinition, WidgetPayload } from '@/types/widget';

const props = defineProps<{
    widgets: WidgetPayload[];
    catalog: WidgetDefinition[];
    categories: Record<string, string>;
    activeCategory: string | null;
    total: number;
    watermark: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Widgets', href: '/widgets' }],
    },
});

const icons: Record<string, Component> = {
    share: Share2,
    shield: ShieldAlert,
    cookie: Cookie,
    chat: MessageCircle,
    megaphone: Megaphone,
    timer: Timer,
    'arrow-up': ArrowUp,
    progress: Gauge,
    sparkles: Sparkles,
};

const iconFor = (key: string): Component => icons[key] ?? Sparkles;

const adding = ref<string | null>(null);
const copiedId = ref<number | null>(null);

const categoryUrl = (key: string | null): string =>
    key === null ? '/widgets' : `/widgets?category=${key}`;

const updatedLabel = (value: string | null): string => {
    if (!value) {
        return 'Not edited yet';
    }

    return `Updated ${new Date(value).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    })}`;
};

function addWidget(definition: WidgetDefinition): void {
    adding.value = definition.key;
    router.post(
        '/widgets',
        { type: definition.key },
        {
            preserveScroll: true,
            onFinish: () => {
                adding.value = null;
            },
        },
    );
}

function duplicate(widget: WidgetPayload): void {
    router.post('/widgets', { type: widget.type, name: `${widget.name} (copy)` });
}

function destroy(widget: WidgetPayload): void {
    if (!window.confirm(`Delete “${widget.name}”? The embed code stops being useful.`)) {
        return;
    }

    router.delete(`/widgets/${widget.id}`);
}

async function copyCode(widget: WidgetPayload): Promise<void> {
    try {
        await navigator.clipboard.writeText(widget.snippet);
        copiedId.value = widget.id;
        window.setTimeout(() => {
            copiedId.value = null;
        }, 2000);
    } catch {
        copiedId.value = null;
    }
}
</script>

<template>
    <Head title="Widgets" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="Widgets"
                description="Copy-paste tools for your own website: share buttons, ad blocker checks, cookie consent and more"
            />
            <Badge variant="secondary">
                {{ props.total }} saved
            </Badge>
        </div>

        <div
            v-if="props.watermark"
            class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
        >
            <p>
                Free plan: every widget carries a small “Made with” credit line.
                Paid plans remove it from the embed code.
            </p>
            <Button variant="outline" size="sm" as-child>
                <Link href="/pricing">Remove branding</Link>
            </Button>
        </div>

        <div class="flex flex-wrap gap-2">
            <Link
                :href="categoryUrl(null)"
                class="rounded-full border px-3 py-1.5 text-sm font-medium transition"
                :class="
                    props.activeCategory === null
                        ? 'border-foreground bg-foreground text-background'
                        : 'border-border text-muted-foreground hover:text-foreground'
                "
            >
                All widget types
            </Link>
            <Link
                v-for="(label, key) in props.categories"
                :key="key"
                :href="categoryUrl(key)"
                class="rounded-full border px-3 py-1.5 text-sm font-medium transition"
                :class="
                    props.activeCategory === key
                        ? 'border-foreground bg-foreground text-background'
                        : 'border-border text-muted-foreground hover:text-foreground'
                "
            >
                {{ label }}
            </Link>
        </div>

        <section v-if="props.widgets.length" class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold tracking-wide uppercase">
                    My widgets
                </h2>
                <p class="text-xs text-muted-foreground">
                    Edit the settings, then copy the code again
                </p>
            </div>

            <div class="divide-y rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <div
                    v-for="widget in props.widgets"
                    :key="widget.id"
                    class="flex flex-wrap items-center gap-3 p-4"
                >
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted"
                    >
                        <component
                            :is="iconFor(widget.definition.icon)"
                            class="size-5 text-muted-foreground"
                        />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium">
                                {{ widget.name }}
                            </p>
                            <Badge variant="secondary">
                                {{ widget.definition.category_label }}
                            </Badge>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ widget.definition.name }} ·
                            {{ updatedLabel(widget.updated_at) }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5">
                        <Button size="sm" as-child>
                            <Link :href="`/widgets/${widget.id}`">
                                <Pencil class="size-4" />
                                Edit
                            </Link>
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            type="button"
                            @click="copyCode(widget)"
                        >
                            <Copy class="size-4" />
                            {{ copiedId === widget.id ? 'Copied' : 'Copy code' }}
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            type="button"
                            @click="duplicate(widget)"
                        >
                            <Plus class="size-4" />
                            Duplicate
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            type="button"
                            class="text-destructive"
                            @click="destroy(widget)"
                        >
                            <Trash2 class="size-4" />
                            Delete
                        </Button>
                    </div>
                </div>
            </div>
        </section>

        <section v-else class="rounded-xl border bg-muted/30 px-4 py-5 text-sm">
            <p class="font-medium">You have no widgets yet</p>
            <p class="mt-1 text-muted-foreground">
                Pick one below, adjust its settings, and paste the generated
                code into your website. Widgets work on any site — WordPress,
                Webflow, Shopify, plain HTML.
            </p>
        </section>

        <section class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold tracking-wide uppercase">
                    {{
                        props.activeCategory
                            ? props.categories[props.activeCategory]
                            : 'All widget types'
                    }}
                </h2>
                <p class="text-xs text-muted-foreground">
                    {{ props.catalog.length }} widgets
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Card
                    v-for="definition in props.catalog"
                    :key="definition.key"
                    class="flex flex-col"
                >
                    <CardContent class="flex flex-1 flex-col gap-3 p-5">
                        <div class="flex items-start justify-between gap-2">
                            <div
                                class="flex size-10 items-center justify-center rounded-lg bg-muted"
                            >
                                <component
                                    :is="iconFor(definition.icon)"
                                    class="size-5"
                                />
                            </div>
                            <Badge variant="outline">
                                {{ definition.category_label }}
                            </Badge>
                        </div>

                        <div class="space-y-1.5">
                            <p class="font-semibold">{{ definition.name }}</p>
                            <p class="text-sm leading-relaxed text-muted-foreground">
                                {{ definition.description }}
                            </p>
                        </div>

                        <div class="mt-auto space-y-3">
                            <div class="flex flex-wrap gap-1.5">
                                <Badge variant="secondary">
                                    {{ definition.fields.length }} settings
                                </Badge>
                                <Badge variant="outline">
                                    {{ definition.placement }}
                                </Badge>
                            </div>

                            <Button
                                class="w-full"
                                type="button"
                                :disabled="adding !== null"
                                @click="addWidget(definition)"
                            >
                                <Blocks class="size-4" />
                                {{
                                    adding === definition.key
                                        ? 'Adding…'
                                        : 'Add widget'
                                }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </section>

        <p
            class="flex items-start gap-2 rounded-lg border bg-muted/40 px-4 py-3 text-xs text-muted-foreground"
        >
            <Check class="mt-0.5 size-4 shrink-0" />
            Every widget is a single
            <span class="font-medium text-foreground">script</span> line served
            from us. Paste it once, then change the settings here whenever you
            like — the embedded widget updates without touching your site's
            code again.
        </p>
    </div>
</template>
