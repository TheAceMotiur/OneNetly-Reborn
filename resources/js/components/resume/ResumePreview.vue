<script setup lang="ts">
import { computed } from 'vue';
import ResumeDocument from '@/components/resume/ResumeDocument.vue';
import type { ResumeContent } from '@/types/resume';

const props = withDefaults(
    defineProps<{
        content: ResumeContent;
        template?: string;
        accent?: string;
        font?: string;
        width?: number;
        watermark?: boolean;
    }>(),
    {
        template: 'classic',
        accent: '#4f46e5',
        font: 'sans',
        width: 320,
        watermark: false,
    },
);

const scale = computed(() => props.width / 794);
const height = computed(() => Math.round(1123 * scale.value));
</script>

<template>
    <div
        class="relative overflow-hidden rounded-md bg-white"
        :style="{ width: `${width}px`, height: `${height}px` }"
    >
        <div
            class="absolute top-0 left-0 origin-top-left"
            :style="{ transform: `scale(${scale})` }"
        >
            <ResumeDocument
                :content="content"
                :template="template"
                :accent="accent"
                :font="font"
                :watermark="watermark"
            />
        </div>
    </div>
</template>
