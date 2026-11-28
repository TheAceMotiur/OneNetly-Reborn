<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Receipt, TrendingUp, Users, UtensilsCrossed } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import RestaurantTabs from '@/components/RestaurantTabs.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type DailyRow = { day: string; orders: number; revenue: string };
type TypeRow = { type: string; orders: number; revenue: string };
type TopItem = { name: string; quantity: number; revenue: string };

const props = defineProps<{
    restaurant: { username: string; name: string };
    restaurants: { username: string; name: string }[];
    filters: { from: string; to: string };
    stats: {
        orders: number;
        revenue: string;
        average_order: string;
        items_sold: number;
        cancelled: number;
    };
    daily: DailyRow[];
    by_type: TypeRow[];
    top_items: TopItem[];
    export_url: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Restaurant', href: '/restaurant' },
            { title: 'Reports', href: '/restaurant' },
        ],
    },
});

const from = ref(props.filters.from);
const to = ref(props.filters.to);

const base = `/restaurant/${encodeURIComponent(props.restaurant.username)}/reports`;

function applyRange(): void {
    router.get(
        base,
        { from: from.value, to: to.value },
        { preserveState: true, preserveScroll: true },
    );
}

function switchRestaurant(event: Event): void {
    const username = (event.target as HTMLSelectElement).value;

    router.visit(`/restaurant/${encodeURIComponent(username)}/reports`);
}
</script>

<template>
    <Head title="Reports" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="`${props.restaurant.name} reports`"
                description="Sales, order mix and best sellers"
            />
            <Button variant="outline" as-child>
                <a :href="props.export_url">
                    <Download class="size-4" />
                    Export CSV
                </a>
            </Button>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <RestaurantTabs :restaurant="props.restaurant" active="reports" />
            <div class="flex flex-wrap items-end gap-3">
                <select
                    v-if="props.restaurants.length > 1"
                    :value="props.restaurant.username"
                    class="h-9 max-w-56 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring"
                    aria-label="Switch restaurant"
                    @change="switchRestaurant"
                >
                    <option
                        v-for="option in props.restaurants"
                        :key="option.username"
                        :value="option.username"
                    >
                        {{ option.name }}
                    </option>
                </select>

                <form
                    class="flex flex-wrap items-end gap-2"
                    @submit.prevent="applyRange"
                >
                    <label class="grid gap-1 text-xs font-medium">
                        From
                        <input
                            v-model="from"
                            type="date"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring"
                        />
                    </label>
                    <label class="grid gap-1 text-xs font-medium">
                        To
                        <input
                            v-model="to"
                            type="date"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring"
                        />
                    </label>
                    <Button type="submit" variant="outline" size="sm">
                        Apply
                    </Button>
                </form>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-1">
                    <CardTitle class="text-sm font-medium">Revenue</CardTitle>
                    <TrendingUp class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ props.stats.revenue }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-1">
                    <CardTitle class="text-sm font-medium">Orders</CardTitle>
                    <Receipt class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ props.stats.orders }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-1">
                    <CardTitle class="text-sm font-medium"
                        >Average order</CardTitle
                    >
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ props.stats.average_order }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-1">
                    <CardTitle class="text-sm font-medium"
                        >Items sold</CardTitle
                    >
                    <UtensilsCrossed class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ props.stats.items_sold }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-1">
                    <CardTitle class="text-sm font-medium"
                        >Cancelled</CardTitle
                    >
                    <Users class="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ props.stats.cancelled }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Daily sales</CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="props.daily.length"
                        class="overflow-x-auto"
                    >
                        <table class="w-full text-left text-sm">
                            <thead
                                class="border-b text-xs tracking-wide text-muted-foreground uppercase"
                            >
                                <tr>
                                    <th class="py-2 pr-4 font-medium">Day</th>
                                    <th class="py-2 pr-4 font-medium">
                                        Orders
                                    </th>
                                    <th class="py-2 font-medium">Revenue</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="row in props.daily" :key="row.day">
                                    <td class="py-2 pr-4">{{ row.day }}</td>
                                    <td class="py-2 pr-4">{{ row.orders }}</td>
                                    <td class="py-2 tabular-nums">
                                        {{ row.revenue }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        No sales in this range.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Order types</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul v-if="props.by_type.length" class="divide-y text-sm">
                        <li
                            v-for="row in props.by_type"
                            :key="row.type"
                            class="flex items-center justify-between gap-3 py-2"
                        >
                            <span>
                                {{ row.type }}
                                <span class="text-muted-foreground"
                                    >· {{ row.orders }} orders</span
                                >
                            </span>
                            <span class="font-medium tabular-nums">{{
                                row.revenue
                            }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        No orders in this range.
                    </p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Best sellers</CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="props.top_items.length" class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-b text-xs tracking-wide text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="py-2 pr-4 font-medium">Item</th>
                                <th class="py-2 pr-4 font-medium">
                                    Quantity
                                </th>
                                <th class="py-2 font-medium">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="item in props.top_items"
                                :key="item.name"
                            >
                                <td class="py-2 pr-4">{{ item.name }}</td>
                                <td class="py-2 pr-4">{{ item.quantity }}</td>
                                <td class="py-2 tabular-nums">
                                    {{ item.revenue }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No items sold in this range.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
