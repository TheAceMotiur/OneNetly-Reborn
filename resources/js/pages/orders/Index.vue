<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    Mail,
    MapPin,
    Phone,
    StickyNote,
    Table2,
    Utensils,
} from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import RestaurantTabs from '@/components/RestaurantTabs.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type OrderItemOption = {
    group: string;
    name: string;
    price_cents: number;
};

type OrderItem = {
    id: number;
    name: string;
    quantity: number;
    line_total: string;
    notes: string | null;
    options: OrderItemOption[];
};

type Order = {
    id: number;
    reference: string;
    customer_name: string;
    customer_phone: string;
    customer_email: string | null;
    type: string;
    type_label: string;
    table_number: string | null;
    delivery_address: string | null;
    delivery_postcode: string | null;
    scheduled_for: string | null;
    coupon_code: string | null;
    notes: string | null;
    subtotal: string;
    discount: string;
    delivery_fee: string;
    service: string;
    tax: string;
    tip: string;
    total: string;
    status: string;
    status_label: string;
    status_class: string;
    created_at: string | null;
    created_human: string | null;
    items: OrderItem[];
};

const props = defineProps<{
    orders: {
        data: Order[];
    };
    stats: {
        today_orders: number;
        today_total: string;
        new_orders: number;
    };
    statuses: Record<string, string>;
    types: Record<string, string>;
    restaurant: { username: string; name: string };
    restaurants: { username: string; name: string }[];
    ordersUrl: string;
    menuUrl: string;
    pagination: {
        current_page: number;
        last_page: number;
        total: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Restaurant', href: '/restaurant' },
            { title: 'Orders', href: '/restaurant' },
        ],
    },
});

const inputClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

const updatingId = ref<number | null>(null);

function updateStatus(order: Order, status: string): void {
    router.patch(
        `${props.ordersUrl}/${order.id}`,
        { status },
        {
            preserveScroll: true,
            onStart: () => {
                updatingId.value = order.id;
            },
            onFinish: () => {
                updatingId.value = null;
            },
        },
    );
}

function goToPage(page: number): void {
    if (page < 1 || page > props.pagination.last_page) {
        return;
    }

    router.get(props.ordersUrl, { page }, { preserveScroll: true });
}

function switchRestaurant(event: Event): void {
    const username = (event.target as HTMLSelectElement).value;

    router.visit(`/restaurant/${encodeURIComponent(username)}/orders`);
}
</script>

<template>
    <Head title="Orders" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="`${props.restaurant.name} orders`"
                description="Orders placed from the public menu"
            />
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <a :href="props.menuUrl">
                        <Utensils class="size-4" />
                        Menu builder
                    </a>
                </Button>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <RestaurantTabs :restaurant="props.restaurant" active="orders" />
            <select
                v-if="props.restaurants.length > 1"
                :value="props.restaurant.username"
                :class="inputClass"
                class="max-w-56"
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
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium"
                        >Orders today</CardTitle
                    >
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ props.stats.today_orders }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        From your public menu
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium"
                        >Sales today</CardTitle
                    >
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ props.stats.today_total }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Total order value
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium">New orders</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ props.stats.new_orders }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Waiting for the kitchen
                    </p>
                </CardContent>
            </Card>
        </div>

        <div
            v-if="props.orders.data.length"
            class="grid gap-4 xl:grid-cols-2"
        >
            <Card v-for="order in props.orders.data" :key="order.id">
                <CardHeader class="gap-3 border-b">
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <CardTitle class="font-mono text-base">
                                    {{ order.reference }}
                                </CardTitle>
                                <span
                                    :class="order.status_class"
                                    class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                                >
                                    {{ order.status_label }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ order.created_human }} ·
                                {{ order.created_at }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="rounded-full border px-2.5 py-0.5 text-[11px] font-medium"
                            >
                                {{ order.type_label }}
                            </span>
                            <span
                                v-if="order.table_number"
                                class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-medium"
                            >
                                <Table2 class="size-3" />
                                Table {{ order.table_number }}
                            </span>
                        </div>
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm"
                    >
                        <span class="font-medium">
                            {{ order.customer_name }}
                        </span>
                        <a
                            :href="`tel:${order.customer_phone}`"
                            class="inline-flex items-center gap-1 text-muted-foreground hover:text-foreground"
                        >
                            <Phone class="size-3.5" />
                            {{ order.customer_phone }}
                        </a>
                        <a
                            v-if="order.customer_email"
                            :href="`mailto:${order.customer_email}`"
                            class="inline-flex items-center gap-1 text-muted-foreground hover:text-foreground"
                        >
                            <Mail class="size-3.5" />
                            {{ order.customer_email }}
                        </a>
                    </div>

                    <p
                        v-if="order.delivery_address"
                        class="flex items-start gap-1.5 text-sm text-muted-foreground"
                    >
                        <MapPin class="mt-0.5 size-3.5 shrink-0" />
                        <span>
                            {{ order.delivery_address }}
                            <template v-if="order.delivery_postcode">
                                · {{ order.delivery_postcode }}
                            </template>
                        </span>
                    </p>

                    <p
                        v-if="order.scheduled_for"
                        class="text-sm font-medium text-amber-700 dark:text-amber-400"
                    >
                        Scheduled for {{ order.scheduled_for }}
                    </p>
                </CardHeader>

                <CardContent class="space-y-4 pt-4">
                    <ul class="space-y-2">
                        <li
                            v-for="item in order.items"
                            :key="item.id"
                            class="flex items-start justify-between gap-3 text-sm"
                        >
                            <div>
                                <span class="font-medium">
                                    {{ item.quantity }} × {{ item.name }}
                                </span>
                                <p
                                    v-if="item.options.length"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        item.options
                                            .map(
                                                (option) =>
                                                    `${option.group}: ${option.name}`,
                                            )
                                            .join(' · ')
                                    }}
                                </p>
                                <p
                                    v-if="item.notes"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ item.notes }}
                                </p>
                            </div>
                            <span class="shrink-0 tabular-nums">
                                {{ item.line_total }}
                            </span>
                        </li>
                    </ul>

                    <div
                        v-if="order.notes"
                        class="flex items-start gap-2 rounded-lg border bg-muted/30 p-3 text-sm"
                    >
                        <StickyNote class="mt-0.5 size-4 shrink-0" />
                        <p>{{ order.notes }}</p>
                    </div>

                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-t pt-4"
                    >
                        <div class="min-w-40 space-y-0.5 text-xs">
                            <div class="flex justify-between gap-4">
                                <span class="text-muted-foreground"
                                    >Subtotal</span
                                >
                                <span class="tabular-nums">
                                    {{ order.subtotal }}
                                </span>
                            </div>
                            <div
                                v-if="order.coupon_code"
                                class="flex justify-between gap-4 text-emerald-600"
                            >
                                <span>
                                    Discount ({{ order.coupon_code }})
                                </span>
                                <span class="tabular-nums">
                                    −{{ order.discount }}
                                </span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="text-muted-foreground"
                                    >Service</span
                                >
                                <span class="tabular-nums">
                                    {{ order.service }}
                                </span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="text-muted-foreground"
                                    >Delivery</span
                                >
                                <span class="tabular-nums">
                                    {{ order.delivery_fee }}
                                </span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="text-muted-foreground">Tax</span>
                                <span class="tabular-nums">
                                    {{ order.tax }}
                                </span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="text-muted-foreground">Tip</span>
                                <span class="tabular-nums">
                                    {{ order.tip }}
                                </span>
                            </div>
                            <div
                                class="flex justify-between gap-4 border-t pt-1 text-sm font-semibold"
                            >
                                <span>Total</span>
                                <span class="tabular-nums">
                                    {{ order.total }}
                                </span>
                            </div>
                        </div>

                        <label class="flex items-center gap-2 text-sm">
                            <span class="text-muted-foreground">Status</span>
                            <select
                                :value="order.status"
                                :class="inputClass"
                                :disabled="updatingId === order.id"
                                @change="
                                    updateStatus(
                                        order,
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    )
                                "
                            >
                                <option
                                    v-for="(label, value) in props.statuses"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </label>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div
            v-else
            class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-6 py-16 text-center text-muted-foreground"
        >
            <ClipboardList class="size-8" />
            <p class="font-medium text-foreground">No orders yet</p>
            <p class="max-w-sm text-sm">
                Enable ordering on your menu and share your table QR code. New
                orders will appear here and in your inbox.
            </p>
            <Button class="mt-2" variant="outline" as-child>
                <a :href="props.menuUrl">Open menu builder</a>
            </Button>
        </div>

        <div
            v-if="props.pagination.last_page > 1"
            class="flex items-center justify-between gap-3"
        >
            <p class="text-sm text-muted-foreground">
                {{ props.pagination.total }} orders · Page
                {{ props.pagination.current_page }} of
                {{ props.pagination.last_page }}
            </p>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="props.pagination.current_page <= 1"
                    @click="goToPage(props.pagination.current_page - 1)"
                >
                    <ChevronLeft class="size-4" />
                    Previous
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="
                        props.pagination.current_page >=
                        props.pagination.last_page
                    "
                    @click="goToPage(props.pagination.current_page + 1)"
                >
                    Next
                    <ChevronRight class="size-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
