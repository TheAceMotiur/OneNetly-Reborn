<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from '@lucide/vue';
import ResumeDocument from '@/components/resume/ResumeDocument.vue';
import { Button } from '@/components/ui/button';
import type { ResumePayload } from '@/types/resume';

const props = defineProps<{
    resume: ResumePayload;
    backUrl: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [],
    },
});

function print(): void {
    window.print();
}
</script>

<template>
    <Head :title="resume.title">
        <meta name="robots" content="noindex, nofollow" head-key="robots" />
    </Head>

    <div class="min-h-screen bg-neutral-200 px-4 py-6 print:bg-white print:p-0">
        <div
            class="mx-auto mb-4 flex w-full max-w-[794px] flex-wrap items-center justify-between gap-2 print:hidden"
        >
            <div class="min-w-0">
                <p class="truncate text-sm font-medium">{{ props.resume.title }}</p>
                <p class="text-xs text-muted-foreground">
                    {{ props.resume.completeness }}% complete · professional
                    resume
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button v-if="props.backUrl" variant="outline" size="sm" as-child>
                    <a :href="props.backUrl">
                        <ArrowLeft class="size-4" />
                        Back to editor
                    </a>
                </Button>
                <Button size="sm" @click="print">
                    <Printer class="size-4" />
                    Print / Save as PDF
                </Button>
            </div>
        </div>

        <div class="overflow-x-auto print:overflow-visible">
            <div class="mx-auto w-[794px]">
                <ResumeDocument
                    :content="props.resume.content"
                    :template="props.resume.template"
                    :accent="props.resume.accent_hex"
                    :font="props.resume.font"
                    :watermark="props.resume.watermark"
                />
            </div>
        </div>

        <p
            class="mx-auto mt-4 max-w-[794px] text-center text-xs text-muted-foreground print:hidden"
        >
            Tip: choose “Save as PDF” as the destination in the print dialog to
            download this resume as a file.
        </p>
    </div>
</template>

<style>
@media print {
    @page {
        size: A4;
        margin: 0;
    }

    html,
    body {
        background: #fff !important;
    }

    .resume-sheet {
        width: 210mm !important;
        min-height: 297mm !important;
        margin: 0 !important;
        box-shadow: none !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
