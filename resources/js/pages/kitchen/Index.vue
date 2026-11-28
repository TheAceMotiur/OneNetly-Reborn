<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Clock, RefreshCw } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import RestaurantTabs from '@/components/RestaurantTabs.vue';
import { Button } from '@/components/ui/button';

type KitchenOrder = {
    id: number;
    reference: string;
    status: string;
    status_label: string;
    status_class: string;
    type_label: string;
    table_number: string | null;
    customer_name: string;
    notes: string | null;
    created_at: string | null;
    waiting_minutes: number;
    items: {
        id: number;
        name: string;
        quantity: number;
        notes: string | null;
        options: { group: string; name: string }[];
    }[];
    update_url: string;
};

const props = defineProps<{
    orders: KitchenOrder[];
    restaurant: { username: string; name: string };
    restaurants: { username: string; name: string }[];
    refresh_url: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Restaurant', href: '/restaurant' },
            { title: 'Kitchen', href: '/restaurant' },
        ],
    },
});

const busy = ref<number | null>(null);
let timer: ReturnType<typeof setInterval> | null = null;

const nextStatus: Record<string, string> = {
    new: 'preparing',
    preparing: 'ready',
    ready: 'completed',
};

const nextLabel: Record<string, string> = {
    new: 'Start cooking',
    preparing: 'Mark ready',
    ready: 'Complete',
};

function advance(order: KitchenOrder): void {
    const status = nextStatus[order.status];

    if (!status) {
        return;
    }

    busy.value = order.id;

    router.patch(
        order.update_url,
        { status },
        {
            preserveScroll: true,
            onFinish: () => {
                busy.value = null;
            },
        },
    );
}

function reload(): void {
    router.reload({ only: ['orders'] });
}

onMounted(() => {
    timer = setInterval(reload, 15000);
});

onUnmounted(() => {
    if (timer !== null) {
        clearInterval(timer);
    }
});
</script>

<template>
    <Head :title="`Kitchen · ${restaurant.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                :title="`${restaurant.name} kitchen`"
                description="Live tickets, wait times and one-tap progression"
            />
            <Button variant="outline" @click="reload">
                <RefreshCw class="size-4" />
                Refresh
            </Button>
        </div>

        <RestaurantTabs :restaurant="restaurant" active="kitchen" />

        <div v-if="orders.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="order in orders"
                :key="order.id"
                class="flex flex-col rounded-xl border bg-card p-4 shadow-sm"
            >
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-mono text-sm font-semibold">
                            {{ order.reference }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ order.type_label }}
                            <span v-if="order.table_number"> · Table {{ order.table_number }}</span>
                        </p>
                    </div>
                    <span
                        :class="order.status_class"
                        class="rounded-full px-2 py-1 text-[11px] font-semibold"
                    >
                        {{ order.status_label }}
                    </span>
                </div>

                <div class="mt-3 flex items-center gap-1 text-xs text-muted-foreground">
                    <Clock class="size-3.5" />
                    {{ order.waiting_minutes }} min · {{ order.customer_name }}
                </div>

                <ul class="mt-3 flex-1 space-y-2 border-t pt-3 text-sm">
                    <li v-for="item in order.items" :key="item.id">
                        <span class="font-semibold">{{ item.quantity }}×</span>
                        {{ item.name }}
                        <p v-if="item.options.length" class="text-xs text-muted-foreground">
                            {{ item.options.map((option) => option.name).join(', ') }}
                        </p>
                        <p v-if="item.notes" class="text-xs text-amber-600">
                            {{ item.notes }}
                        </p>
                    </li>
                </ul>

                <p v-if="order.notes" class="mt-2 rounded-md bg-amber-50 p-2 text-xs text-amber-800">
                    {{ order.notes }}
                </p>

                <Button
                    class="mt-4 w-full"
                    :disabled="busy === order.id"
                    @click="advance(order)"
                >
                    {{ busy === order.id ? 'Updating…' : nextLabel[order.status] }}
                </Button>
            </article>
        </div>
        <p v-else class="rounded-xl border border-dashed px-6 py-16 text-center text-sm text-muted-foreground">
            No active tickets. New orders appear here automatically.
        </p>
    </div>
</template>
