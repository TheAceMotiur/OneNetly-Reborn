<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Search } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import RestaurantTabs from '@/components/RestaurantTabs.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Customer = {
    id: number;
    name: string | null;
    phone: string;
    email: string | null;
    points: number;
    lifetime_points: number;
    visits: number;
    total_spent: string;
    notes: string | null;
    last_order_human: string | null;
};

const props = defineProps<{
    customers: {
        data: Customer[];
        current_page: number;
        last_page: number;
        total: number;
    };
    stats: {
        customers: number;
        repeat: number;
        points: number;
        spent: string;
    };
    search: string;
    restaurant: { username: string; name: string };
    restaurants: { username: string; name: string }[];
    exportUrl: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Restaurant', href: '/restaurant' },
            { title: 'Customers', href: '/restaurant' },
        ],
    },
});

const query = ref(props.search);
const notes = ref<Record<number, string>>(
    Object.fromEntries(
        props.customers.data.map((customer) => [
            customer.id,
            customer.notes ?? '',
        ]),
    ),
);

function search(): void {
    router.get(
        `/restaurant/${encodeURIComponent(props.restaurant.username)}/customers`,
        { q: query.value || undefined },
        { preserveState: true, preserveScroll: true },
    );
}

function saveNotes(customer: Customer): void {
    router.patch(
        `/restaurant/${encodeURIComponent(props.restaurant.username)}/customers/${customer.id}`,
        { notes: notes.value[customer.id] || null },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="`Customers · ${restaurant.name}`" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <Heading
            :title="`${restaurant.name} customers`"
            description="Order history, loyalty balances and private notes for your guests"
        />

        <RestaurantTabs :restaurant="restaurant" active="customers" />

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Customers</p>
                <p class="mt-1 text-2xl font-semibold">{{ stats.customers }}</p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Repeat guests</p>
                <p class="mt-1 text-2xl font-semibold">{{ stats.repeat }}</p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Outstanding points</p>
                <p class="mt-1 text-2xl font-semibold">{{ stats.points }}</p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Lifetime spend</p>
                <p class="mt-1 text-2xl font-semibold">{{ stats.spent }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <form class="flex min-w-64 flex-1 gap-2" @submit.prevent="search">
                <Input v-model="query" placeholder="Search name, phone or email" />
                <Button type="submit" variant="outline">
                    <Search class="size-4" />
                    Search
                </Button>
            </form>
            <Button variant="outline" as-child>
                <a :href="exportUrl">
                    <Download class="size-4" />
                    Export CSV
                </a>
            </Button>
        </div>

        <div v-if="customers.data.length" class="space-y-3">
            <div
                v-for="customer in customers.data"
                :key="customer.id"
                class="rounded-xl border p-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-medium">
                            {{ customer.name || 'Guest' }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ customer.phone }}
                            <span v-if="customer.email"> · {{ customer.email }}</span>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full border px-2 py-1">{{ customer.points }} pts</span>
                        <span class="rounded-full border px-2 py-1">{{ customer.visits }} visits</span>
                        <span class="rounded-full border px-2 py-1">{{ customer.total_spent }}</span>
                    </div>
                </div>
                <p class="mt-2 text-xs text-muted-foreground">
                    Last order {{ customer.last_order_human || 'never' }}
                </p>
                <details class="mt-3">
                    <summary class="cursor-pointer text-xs font-medium">Internal notes</summary>
                    <div class="mt-2 flex gap-2">
                        <textarea
                            v-model="notes[customer.id]"
                            rows="2"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                            placeholder="Allergies, preferences, follow-up…"
                        />
                        <Button size="sm" @click="saveNotes(customer)">Save</Button>
                    </div>
                </details>
            </div>
        </div>
        <p v-else class="rounded-xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground">
            Customers appear after their first order with a phone number.
        </p>

        <div v-if="customers.last_page > 1" class="flex items-center justify-between text-sm">
            <span class="text-muted-foreground">{{ customers.total }} customers</span>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="customers.current_page <= 1"
                    @click="router.get(`/restaurant/${encodeURIComponent(restaurant.username)}/customers?page=${customers.current_page - 1}`)"
                >
                    Previous
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="customers.current_page >= customers.last_page"
                    @click="router.get(`/restaurant/${encodeURIComponent(restaurant.username)}/customers?page=${customers.current_page + 1}`)"
                >
                    Next
                </Button>
            </div>
        </div>
    </div>
</template>
