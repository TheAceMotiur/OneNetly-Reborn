<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    Copy,
    Loader2,
    Lock,
    Monitor,
    Smartphone,
    Trash2,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type {
    WidgetDefinition,
    WidgetField,
    WidgetMeta,
    WidgetPayload,
} from '@/types/widget';

const props = defineProps<{
    widget: WidgetPayload;
    definition: WidgetDefinition;
    categories: Record<string, string>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Widgets', href: '/widgets' },
            { title: 'Configure widget', href: '/widgets' },
        ],
    },
});

const inputClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring';
const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:border-ring';

/**
 * Inputs work with strings and checkboxes with booleans; the server casts
 * numbers, colours and selects back to their real types.
 */
function initialConfig(): Record<string, string | boolean> {
    const config: Record<string, string | boolean> = {};

    props.definition.fields.forEach((field) => {
        const value = props.widget.config[field.key];
        config[field.key] =
            field.type === 'checkbox' ? Boolean(value) : String(value ?? '');
    });

    return config;
}

const form = useForm({
    name: props.widget.name,
    config: initialConfig(),
});

const previewHtml = ref(props.widget.preview);
const previewLoading = ref(false);
const previewDevice = ref<'desktop' | 'mobile'>('desktop');
const copied = ref(false);

const meta: WidgetMeta = props.widget.definition;

function setConfig(key: string, value: string | boolean): void {
    form.config[key] = value;
}

/**
 * The page list only matters when the widget is limited to or kept off
 * listed pages, so it stays disabled otherwise.
 */
function isInactive(field: WidgetField): boolean {
    return (
        field.key === 'behaviour_paths' &&
        String(form.config.behaviour_pages ?? 'everywhere') === 'everywhere'
    );
}

const csrfToken = (): string =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

async function refreshPreview(): Promise<void> {
    previewLoading.value = true;

    try {
        const response = await fetch('/widgets/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                type: props.widget.type,
                config: form.config,
            }),
        });

        if (!response.ok) {
            return;
        }

        const data = (await response.json()) as {
            preview: string;
        };

        previewHtml.value = data.preview;
    } catch {
        // Keep the last working preview when the request fails.
    } finally {
        previewLoading.value = false;
    }
}

let previewTimer: ReturnType<typeof setTimeout> | undefined;

watch(
    form.config,
    () => {
        if (previewTimer) {
            window.clearTimeout(previewTimer);
        }

        previewTimer = window.setTimeout(() => {
            void refreshPreview();
        }, 400);
    },
    { deep: true },
);

function save(): void {
    form.patch(`/widgets/${props.widget.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            previewHtml.value = props.widget.preview;
        },
    });
}

function destroy(): void {
    if (!window.confirm(`Delete “${props.widget.name}”?`)) {
        return;
    }

    router.delete(`/widgets/${props.widget.id}`);
}

async function copyCode(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.widget.snippet);
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
    <Head :title="`${props.widget.name} — Widget`" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-wrap items-center gap-2 rounded-xl border bg-card p-3">
            <Button variant="ghost" size="sm" as-child>
                <Link href="/widgets">
                    <ArrowLeft class="size-4" />
                    All widgets
                </Link>
            </Button>

            <input
                v-model="form.name"
                :class="[inputClass, 'min-w-40 flex-1 font-medium']"
                maxlength="80"
                aria-label="Widget name"
                placeholder="Widget name"
            />

            <Badge variant="secondary">{{ meta.category_label }}</Badge>

            <span
                v-if="form.config.behaviour_paused"
                class="text-xs font-medium text-amber-600 dark:text-amber-400"
            >
                Paused
            </span>

            <span
                v-if="form.isDirty"
                class="text-xs font-medium text-amber-600 dark:text-amber-400"
            >
                Unsaved changes
            </span>
            <span
                v-else-if="form.recentlySuccessful"
                class="flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400"
            >
                <Check class="size-3.5" />
                Saved
            </span>

            <Button :disabled="form.processing" @click="save">
                <Check class="size-4" />
                {{ form.processing ? 'Saving…' : 'Save widget' }}
            </Button>
            <Button variant="ghost" class="text-destructive" @click="destroy">
                <Trash2 class="size-4" />
                Delete
            </Button>
        </div>

        <div
            v-if="Object.keys(form.errors).length"
            class="rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm"
        >
            <p class="font-medium">Please check these settings:</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5 text-xs">
                <li
                    v-for="(message, field) in form.errors"
                    :key="field"
                >
                    {{ message }}
                </li>
            </ul>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_460px]">
            <div class="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">
                            {{ meta.name }} settings
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            {{ meta.description }}
                        </p>

                        <div
                            v-for="(field, index) in props.definition.fields"
                            :key="field.key"
                            class="grid gap-2"
                            :class="{ 'opacity-60': isInactive(field) }"
                        >
                            <div
                                v-if="
                                    field.group &&
                                    props.definition.fields[index - 1]?.group !==
                                        field.group
                                "
                                class="mt-2 space-y-1 border-t pt-4"
                            >
                                <p class="text-sm font-semibold">
                                    {{ field.group }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Checked in the visitor's browser before the
                                    widget appears. The preview on the right
                                    always shows the widget so you can keep
                                    editing it.
                                </p>
                            </div>

                            <label
                                :for="`field-${field.key}`"
                                class="text-sm font-medium"
                            >
                                {{ field.label }}
                            </label>

                            <input
                                v-if="field.type === 'text'"
                                :id="`field-${field.key}`"
                                type="text"
                                :class="inputClass"
                                :value="String(form.config[field.key] ?? '')"
                                :placeholder="field.placeholder"
                                :maxlength="field.max"
                                :disabled="isInactive(field)"
                                @input="
                                    setConfig(
                                        field.key,
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    )
                                "
                            />

                            <textarea
                                v-else-if="field.type === 'textarea'"
                                :id="`field-${field.key}`"
                                :class="textareaClass"
                                rows="3"
                                :value="String(form.config[field.key] ?? '')"
                                :placeholder="field.placeholder"
                                :maxlength="field.max"
                                @input="
                                    setConfig(
                                        field.key,
                                        ($event.target as HTMLTextAreaElement)
                                            .value,
                                    )
                                "
                            />

                            <select
                                v-else-if="field.type === 'select'"
                                :id="`field-${field.key}`"
                                :class="inputClass"
                                :value="String(form.config[field.key] ?? '')"
                                @change="
                                    setConfig(
                                        field.key,
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    )
                                "
                            >
                                <option
                                    v-for="(optionLabel, optionValue) in field.options ??
                                    {}"
                                    :key="optionValue"
                                    :value="optionValue"
                                >
                                    {{ optionLabel }}
                                </option>
                            </select>

                            <div
                                v-else-if="field.type === 'color'"
                                class="flex items-center gap-2"
                            >
                                <input
                                    :id="`field-${field.key}`"
                                    type="color"
                                    class="h-9 w-14 cursor-pointer rounded-md border border-input bg-transparent p-1"
                                    :value="String(form.config[field.key] ?? '')"
                                    @input="
                                        setConfig(
                                            field.key,
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        )
                                    "
                                />
                                <input
                                    type="text"
                                    class="h-9 w-28 rounded-md border border-input bg-transparent px-3 font-mono text-xs"
                                    maxlength="7"
                                    :value="String(form.config[field.key] ?? '')"
                                    @input="
                                        setConfig(
                                            field.key,
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        )
                                    "
                                />
                            </div>

                            <input
                                v-else-if="field.type === 'number'"
                                :id="`field-${field.key}`"
                                type="number"
                                :class="inputClass"
                                :min="field.min"
                                :max="field.max"
                                :value="String(form.config[field.key] ?? '')"
                                @input="
                                    setConfig(
                                        field.key,
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    )
                                "
                            />

                            <label
                                v-else-if="field.type === 'checkbox'"
                                class="flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <input
                                    type="checkbox"
                                    class="size-4 rounded border-input"
                                    :checked="Boolean(form.config[field.key])"
                                    @change="
                                        setConfig(
                                            field.key,
                                            ($event.target as HTMLInputElement)
                                                .checked,
                                        )
                                    "
                                />
                                {{ field.label }}
                            </label>

                            <p
                                v-if="field.help"
                                class="text-xs text-muted-foreground"
                            >
                                {{ field.help }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">How to install</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm text-muted-foreground">
                        <p>
                            1. Copy the embed code on the right — a single
                            <span class="font-medium text-foreground">
                                script
                            </span>
                            line.
                        </p>
                        <p>
                            2. Paste it into your website —
                            <span class="font-medium text-foreground">{{
                                meta.placement.toLowerCase()
                            }}</span>
                            . In WordPress that is usually the footer template
                            or a “Custom HTML” block.
                        </p>
                        <p>
                            3. Save and publish your site. The widget is served
                            from us, so later changes here go live without
                            editing the code again.
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-4 lg:sticky lg:top-4 lg:self-start">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-medium">Live preview</p>
                        <span
                            v-if="previewLoading"
                            class="flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <Loader2 class="size-3.5 animate-spin" />
                            Updating
                        </span>
                    </div>

                    <div class="flex items-center gap-0.5 rounded-lg border p-0.5">
                        <Button
                            type="button"
                            size="sm"
                            class="h-7 gap-1.5 px-2 text-xs"
                            :variant="
                                previewDevice === 'desktop'
                                    ? 'secondary'
                                    : 'ghost'
                            "
                            @click="previewDevice = 'desktop'"
                        >
                            <Monitor class="size-3.5" />
                            Desktop
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            class="h-7 gap-1.5 px-2 text-xs"
                            :variant="
                                previewDevice === 'mobile'
                                    ? 'secondary'
                                    : 'ghost'
                            "
                            @click="previewDevice = 'mobile'"
                        >
                            <Smartphone class="size-3.5" />
                            Mobile
                        </Button>
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-xl border bg-card shadow-sm"
                >
                    <div
                        class="flex items-center gap-2 border-b bg-muted/40 px-3 py-2"
                    >
                        <span class="flex items-center gap-1.5">
                            <span class="size-2 rounded-full bg-red-400/70" />
                            <span class="size-2 rounded-full bg-amber-400/70" />
                            <span class="size-2 rounded-full bg-emerald-400/70" />
                        </span>
                        <span
                            class="mx-auto flex max-w-[75%] items-center gap-1.5 truncate rounded-md border bg-background px-2.5 py-1 font-mono text-[10.5px] text-muted-foreground"
                        >
                            <Lock class="size-3 shrink-0" />
                            your-website.com
                        </span>
                        <span class="w-9 shrink-0" aria-hidden="true" />
                    </div>

                    <div class="flex justify-center bg-muted/30 p-3">
                        <iframe
                            :srcdoc="previewHtml"
                            title="Widget preview"
                            sandbox="allow-scripts allow-popups allow-forms"
                            class="h-[520px] rounded-lg border bg-white shadow-sm transition-[width] duration-200"
                            :class="
                                previewDevice === 'desktop'
                                    ? 'w-full'
                                    : 'w-[375px] max-w-full'
                            "
                        />
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-medium">Embed code</p>
                        <Button
                            size="sm"
                            variant="outline"
                            type="button"
                            @click="copyCode"
                        >
                            <Copy class="size-4" />
                            {{ copied ? 'Copied' : 'Copy code' }}
                        </Button>
                    </div>

                    <pre
                        class="max-h-64 overflow-auto rounded-xl bg-slate-950 p-4 text-[11px] leading-relaxed text-slate-100"
                    ><code>{{ props.widget.snippet }}</code></pre>

                    <p class="text-xs text-muted-foreground">
                        One line of code, loaded from our servers. Save your
                        settings and the embedded widgets update straight away.
                    </p>

                    <p
                        v-if="props.widget.watermark"
                        class="text-xs text-muted-foreground"
                    >
                        Your free plan adds a small “Made with” credit to this
                        widget. Upgrading removes it — re-copy the code after
                        subscribing.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
