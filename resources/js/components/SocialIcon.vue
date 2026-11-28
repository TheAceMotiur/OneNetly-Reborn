<script setup lang="ts">
import { Globe, Mail } from '@lucide/vue';
import { computed } from 'vue';
import { socialIconPaths } from '@/lib/social-icons';

const props = defineProps<{ name: string }>();

const paths = computed<string[]>(() => socialIconPaths[props.name] ?? []);
</script>

<template>
    <Mail v-if="props.name === 'email'" class="size-4" aria-hidden="true" />
    <Globe
        v-else-if="props.name === 'website'"
        class="size-4"
        aria-hidden="true"
    />
    <svg
        v-else
        viewBox="0 0 24 24"
        fill="currentColor"
        class="size-4"
        aria-hidden="true"
    >
        <path v-for="(d, index) in paths" :key="index" :d="d" />
    </svg>
</template>
