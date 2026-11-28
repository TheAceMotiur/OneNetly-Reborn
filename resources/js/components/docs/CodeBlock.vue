<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { ref } from 'vue';

const props = withDefaults(
    defineProps<{
        code: string;
        language?: string;
    }>(),
    {
        language: 'json',
    },
);

const copied = ref(false);

async function copy(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.code);
        copied.value = true;

        setTimeout(() => {
            copied.value = false;
        }, 1500);
    } catch {
        // Clipboard access can be blocked by the browser.
    }
}
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-950 text-zinc-100"
    >
        <div
            class="flex items-center justify-between gap-3 border-b border-white/10 px-4 py-2"
        >
            <span
                class="text-[11px] font-medium tracking-[0.15em] text-zinc-400 uppercase"
            >
                {{ language }}
            </span>
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-[11px] font-medium text-zinc-400 transition hover:bg-white/10 hover:text-white"
                @click="copy"
            >
                <Check v-if="copied" class="size-3.5" />
                <Copy v-else class="size-3.5" />
                {{ copied ? 'Copied' : 'Copy' }}
            </button>
        </div>
        <pre class="overflow-x-auto p-4 text-xs leading-relaxed"><code>{{ code }}</code></pre>
    </div>
</template>
