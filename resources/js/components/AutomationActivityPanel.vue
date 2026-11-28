<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Item = {
    id: number;
    label: string;
    status: string;
    trigger: string;
    contact: string | null;
    rule: string | null;
    detail: string | null;
    created_human: string | null;
};

defineProps<{ items: Item[] }>();

function statusClass(status: string): string {
    if (status === 'sent') {
        return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
    }

    if (status === 'failed') {
        return 'bg-destructive/10 text-destructive';
    }

    return 'bg-amber-500/10 text-amber-600 dark:text-amber-400';
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">Automation activity</CardTitle>
            <p class="text-sm text-muted-foreground">
                Every automation run, newest first, with what matched and what was sent.
            </p>
        </CardHeader>
        <CardContent class="space-y-2">
            <p v-if="items.length === 0" class="text-sm text-muted-foreground">
                No automation has run yet.
            </p>
            <div
                v-for="item in items"
                :key="item.id"
                class="flex flex-wrap items-center gap-2 rounded-lg border p-3"
            >
                <span
                    class="rounded-full px-2 py-0.5 text-[11px] font-medium capitalize"
                    :class="statusClass(item.status)"
                >
                    {{ item.status }}
                </span>
                <p class="text-sm font-medium">{{ item.label }}</p>
                <span v-if="item.rule" class="text-xs text-muted-foreground">{{ item.rule }}</span>
                <span class="text-xs text-muted-foreground">
                    {{ item.contact || 'Unknown contact' }} · {{ item.trigger }}
                </span>
                <span class="ml-auto text-xs text-muted-foreground">
                    {{ item.created_human }}
                </span>
                <p v-if="item.detail" class="w-full text-xs text-muted-foreground">
                    {{ item.detail }}
                </p>
            </div>
        </CardContent>
    </Card>
</template>
