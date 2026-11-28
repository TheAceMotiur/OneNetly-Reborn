<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, LayoutTemplate } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import ResumePreview from '@/components/resume/ResumePreview.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { ResumeContent, ResumeTemplate } from '@/types/resume';

const props = defineProps<{
    templates: ResumeTemplate[];
    sample: ResumeContent;
    accents: Record<string, string>;
    defaultAccent: string;
    total: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Resumes', href: '/resumes' },
            { title: 'Templates', href: '/resumes/templates' },
        ],
    },
});

const creating = ref<string | null>(null);

const form = useForm({
    title: 'My resume',
    template: 'classic',
});

const previewAccents: Record<string, string> = {
    classic: props.accents.indigo ?? props.defaultAccent,
    modern: props.accents.sky ?? props.defaultAccent,
    executive: props.accents.slate ?? props.defaultAccent,
    minimal: props.accents.teal ?? props.defaultAccent,
    sidebar: props.accents.violet ?? props.defaultAccent,
    technical: props.accents.emerald ?? props.defaultAccent,
};

const previewFont = (template: string): string =>
    template === 'executive' || template === 'classic' ? 'serif' : 'sans';

function useTemplate(template: ResumeTemplate): void {
    creating.value = template.key;
    form.title = `${template.name} resume`;
    form.template = template.key;
    form.post('/resumes', {
        onFinish: () => {
            creating.value = null;
        },
    });
}
</script>

<template>
    <Head title="Resume templates" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="Resume templates"
                description="Six recruiter-friendly layouts. Every one prints to a clean A4 PDF and most are ATS friendly."
            />
            <Button variant="outline" as-child>
                <Link href="/resumes">
                    <ArrowLeft class="size-4" />
                    {{ props.total > 0 ? 'My resumes' : 'Back to resumes' }}
                </Link>
            </Button>
        </div>

        <p
            class="rounded-lg border bg-muted/40 px-4 py-3 text-sm text-muted-foreground"
        >
            Pick a layout to start a new resume. You can switch templates,
            accent colour and typeface at any time inside the builder — your
            content stays exactly the same.
        </p>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <Card
                v-for="template in props.templates"
                :key="template.key"
                class="overflow-hidden"
            >
                <div
                    class="flex justify-center bg-neutral-100 p-4 dark:bg-neutral-900"
                >
                    <div class="shadow-md">
                        <ResumePreview
                            :content="props.sample"
                            :template="template.key"
                            :accent="previewAccents[template.key] ?? props.defaultAccent"
                            :font="previewFont(template.key)"
                            :width="250"
                        />
                    </div>
                </div>

                <CardContent class="space-y-3 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-semibold">{{ template.name }}</p>
                        <Badge v-if="template.ats" variant="secondary">
                            <Check class="size-3" />
                            ATS friendly
                        </Badge>
                        <Badge v-else variant="outline">Visual</Badge>
                    </div>

                    <p class="min-h-16 text-sm text-muted-foreground">
                        {{ template.description }}
                    </p>

                    <div class="flex flex-wrap gap-1">
                        <Badge variant="outline">{{ template.style }}</Badge>
                        <Badge
                            v-for="tag in template.tags"
                            :key="tag"
                            variant="outline"
                        >
                            {{ tag }}
                        </Badge>
                    </div>

                    <Button
                        class="w-full"
                        :disabled="creating !== null"
                        @click="useTemplate(template)"
                    >
                        <LayoutTemplate class="size-4" />
                        {{
                            creating === template.key
                                ? 'Creating…'
                                : 'Use this template'
                        }}
                    </Button>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
