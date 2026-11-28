<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Copy,
    ExternalLink,
    LayoutTemplate,
    Pencil,
    Plus,
    Printer,
    Share2,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import ResumePreview from '@/components/resume/ResumePreview.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { ResumePayload, ResumeTemplate } from '@/types/resume';

const props = defineProps<{
    resumes: ResumePayload[];
    templates: ResumeTemplate[];
    accents: Record<string, string>;
    watermark: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Resumes', href: '/resumes' }],
    },
});

const createOpen = ref(false);
const copiedId = ref<number | null>(null);

const createForm = useForm({
    title: 'My resume',
    template: 'classic',
});

const inputClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring';

const templateName = (key: string): string =>
    props.templates.find((template) => template.key === key)?.name ?? key;

const templateAccent = (key: string): string => props.accents[key] ?? '#4f46e5';

const updatedLabel = (value: string | null): string => {
    if (!value) {
        return 'Never edited';
    }

    return `Updated ${new Date(value).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    })}`;
};

const completedCount = computed(
    () => props.resumes.filter((resume) => resume.completeness >= 80).length,
);

function openCreate(template = 'classic'): void {
    createForm.template = template;
    createForm.title =
        template === 'classic'
            ? 'My resume'
            : `${templateName(template)} resume`;
    createOpen.value = true;
}

function create(): void {
    createForm.post('/resumes', {
        onSuccess: () => {
            createOpen.value = false;
            createForm.reset();
        },
    });
}

function duplicate(resume: ResumePayload): void {
    router.post(`/resumes/${resume.id}/duplicate`);
}

function togglePublic(resume: ResumePayload): void {
    router.post(`/resumes/${resume.id}/publish`, {}, { preserveScroll: true });
}

function destroy(resume: ResumePayload): void {
    if (!window.confirm(`Delete “${resume.title}”? This cannot be undone.`)) {
        return;
    }

    router.delete(`/resumes/${resume.id}`);
}

async function copyLink(resume: ResumePayload): Promise<void> {
    if (!resume.public_url) {
        return;
    }

    try {
        await navigator.clipboard.writeText(resume.public_url);
        copiedId.value = resume.id;
        window.setTimeout(() => {
            copiedId.value = null;
        }, 2000);
    } catch {
        copiedId.value = null;
    }
}
</script>

<template>
    <Head title="Resumes" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="Resumes"
                description="Build a professional resume and export it as a PDF in minutes"
            />
            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" as-child>
                    <Link href="/resumes/templates">
                        <LayoutTemplate class="size-4" />
                        Templates
                    </Link>
                </Button>
                <Button @click="openCreate()">
                    <Plus class="size-4" />
                    New resume
                </Button>
            </div>
        </div>

        <div
            v-if="props.watermark"
            class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
        >
            <p>
                Free plan: a small “Made with” credit is printed at the bottom
                of every resume. Paid plans remove it.
            </p>
            <Button variant="outline" size="sm" as-child>
                <Link href="/pricing">Remove branding</Link>
            </Button>
        </div>

        <p
            v-if="props.resumes.length"
            class="rounded-lg border bg-muted/40 px-4 py-3 text-sm text-muted-foreground"
        >
            {{ props.resumes.length }} resume{{
                props.resumes.length === 1 ? '' : 's'
            }}
            · {{ completedCount }} ready to send. Every template prints
            to a clean A4 page, so “Print → Save as PDF” gives you a file you
            can attach to any application.
        </p>

        <div
            v-if="props.resumes.length"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
        >
            <Card
                v-for="resume in props.resumes"
                :key="resume.id"
                class="overflow-hidden"
            >
                <div
                    class="flex justify-center bg-neutral-100 p-4 dark:bg-neutral-900"
                >
                    <div class="shadow-md">
                        <ResumePreview
                            :content="resume.content"
                            :template="resume.template"
                            :accent="resume.accent_hex"
                            :font="resume.font"
                            :width="240"
                            :watermark="resume.watermark"
                        />
                    </div>
                </div>

                <CardContent class="space-y-3 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">
                                {{ resume.title }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ templateName(resume.template) }} ·
                                {{ updatedLabel(resume.updated_at) }}
                            </p>
                        </div>
                        <Badge
                            :variant="resume.is_public ? 'default' : 'secondary'"
                        >
                            {{ resume.is_public ? 'Shared' : 'Private' }}
                        </Badge>
                    </div>

                    <div class="space-y-1">
                        <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full transition-all"
                                :style="{
                                    width: `${resume.completeness}%`,
                                    backgroundColor: resume.accent_hex,
                                }"
                            />
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ resume.completeness }}% complete
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-1.5">
                        <Button size="sm" as-child>
                            <Link :href="`/resumes/${resume.id}`">
                                <Pencil class="size-4" />
                                Edit
                            </Link>
                        </Button>
                        <Button size="sm" variant="outline" as-child>
                            <a :href="resume.print_url" target="_blank">
                                <Printer class="size-4" />
                                PDF
                            </a>
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            type="button"
                            @click="duplicate(resume)"
                        >
                            <Copy class="size-4" />
                            Duplicate
                        </Button>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5">
                        <Button
                            size="sm"
                            variant="ghost"
                            type="button"
                            @click="togglePublic(resume)"
                        >
                            <Share2 class="size-4" />
                            {{ resume.is_public ? 'Stop sharing' : 'Share' }}
                        </Button>
                        <template v-if="resume.public_url">
                            <Button size="sm" variant="ghost" as-child>
                                <a :href="resume.public_url" target="_blank">
                                    <ExternalLink class="size-4" />
                                    View
                                </a>
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                type="button"
                                @click="copyLink(resume)"
                            >
                                <Copy class="size-4" />
                                {{ copiedId === resume.id ? 'Copied' : 'Copy link' }}
                            </Button>
                        </template>
                        <Button
                            size="sm"
                            variant="ghost"
                            type="button"
                            class="text-destructive"
                            @click="destroy(resume)"
                        >
                            <Trash2 class="size-4" />
                            Delete
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card v-else>
            <CardContent class="flex flex-col items-center gap-4 py-14 text-center">
                <div class="rounded-full bg-muted p-4">
                    <LayoutTemplate class="size-6 text-muted-foreground" />
                </div>
                <div class="space-y-1">
                    <p class="text-lg font-semibold">Your first resume starts here</p>
                    <p class="max-w-md text-sm text-muted-foreground">
                        Pick one of {{ props.templates.length }} professional
                        templates, fill in your experience, and print a clean
                        A4 PDF whenever you need it.
                    </p>
                </div>
                <div class="flex flex-wrap justify-center gap-2">
                    <Button as-child>
                        <Link href="/resumes/templates">
                            <LayoutTemplate class="size-4" />
                            Browse templates
                        </Link>
                    </Button>
                    <Button variant="outline" @click="openCreate()">
                        <Plus class="size-4" />
                        Start blank
                    </Button>
                </div>
            </CardContent>
        </Card>

        <Dialog v-model:open="createOpen">
            <DialogTrigger as-child>
                <span />
            </DialogTrigger>
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>New resume</DialogTitle>
                    <DialogDescription>
                        Give it a name and choose a starting layout. You can
                        change both at any time.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="create">
                    <div class="grid gap-2">
                        <Label for="new-resume-title">Resume name</Label>
                        <input
                            id="new-resume-title"
                            v-model="createForm.title"
                            :class="inputClass"
                            maxlength="120"
                            placeholder="Product designer CV"
                        />
                        <p
                            v-if="createForm.errors.title"
                            class="text-xs text-destructive"
                        >
                            {{ createForm.errors.title }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label for="new-resume-template">Template</Label>
                        <select
                            id="new-resume-template"
                            v-model="createForm.template"
                            :class="inputClass"
                        >
                            <option
                                v-for="template in props.templates"
                                :key="template.key"
                                :value="template.key"
                            >
                                {{ template.name }} — {{ template.style }}
                            </option>
                        </select>
                    </div>

                    <DialogFooter>
                        <Button
                            type="submit"
                            :disabled="createForm.processing"
                        >
                            Create resume
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
