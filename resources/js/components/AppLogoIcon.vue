<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        :class="className"
        v-bind="$attrs"
    >
        <circle
            cx="12"
            cy="12.5"
            r="7"
            fill="none"
            stroke="currentColor"
            stroke-width="2.6"
        />
        <path
            d="M16.9 7.1 18.1 5.9"
            fill="none"
            stroke="currentColor"
            stroke-width="2.2"
            stroke-linecap="round"
        />
        <circle cx="18.9" cy="5.1" r="1.9" fill="currentColor" />
    </svg>
</template>
