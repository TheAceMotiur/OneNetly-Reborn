<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { History } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import RestaurantTabs from '@/components/RestaurantTabs.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';

type LogEntry = {
    id: number;
    action: string;
    action_label: string;
    description: string;
    user: string;
    created_at: string | null;
    created_human: string | null;
};

const props = defineProps<{
    logs: LogEntry[];
    restaurant: { username: string; name: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Restaurant', href: '/restaurant' },
            { title: 'Activity', href: '/restaurant' },
        ],
    },
});
</script>

<template>
    <Head title="Activity" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <Heading
            :title="`${props.restaurant.name} activity`"
            description="Everything you and your team changed, newest first"
        />

        <RestaurantTabs :restaurant="props.restaurant" active="activity" />

        <div v-if="props.logs.length" class="grid gap-2">
            <Card v-for="log in props.logs" :key="log.id">
                <CardContent class="flex flex-wrap items-start justify-between gap-3 py-4">
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary">{{ log.action_label }}</Badge>
                            <span class="text-sm font-medium">{{ log.user }}</span>
                        </div>
                        <p class="text-sm">{{ log.description }}</p>
                    </div>
                    <p
                        class="text-xs text-muted-foreground"
                        :title="log.created_at ?? ''"
                    >
                        {{ log.created_human }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div
            v-else
            class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-6 py-16 text-center text-muted-foreground"
        >
            <History class="size-8" />
            <p class="font-medium text-foreground">Nothing recorded yet</p>
            <p class="max-w-sm text-sm">
                Changes to your menu, orders, bookings, team and settings will
                appear here.
            </p>
        </div>
    </div>
</template>
