<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    ChefHat,
    Clock,
    MapPin,
    Phone,
    RefreshCw,
    XCircle,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted } from 'vue';

type TrackItem = {
    id: number;
    name: string;
    quantity: number;
    unit_price: string;
    line_total: string;
    notes: string | null;
    options: { group: string; name: string; price_cents: number }[];
};

type TrackOrder = {
    reference: string;
    status: string;
    status_label: string;
    status_class: string;
    type: string;
    type_label: string;
    table_number: string | null;
    delivery_address: string | null;
    coupon_code: string | null;
    customer_name: string;
    notes: string | null;
    created_at: string | null;
    created_human: string | null;
    items: TrackItem[];
    subtotal: string;
    discount: string;
    points_redeemed: number;
    loyalty_discount: string;
    delivery_fee: string;
    service: string;
    tax: string;
    tip: string;
    total: string;
    currency: string;
};

const props = defineProps<{
    restaurant: {
        username: string;
        name: string;
        address: string | null;
        phone: string | null;
        currency: string;
    };
    order: TrackOrder;
    loyalty: { points: number; visits: number } | null;
    steps: { key: string; label: string }[];
}>();

const cancelled = computed(() => props.order.status === 'cancelled');
const activeIndex = computed(() =>
    props.steps.findIndex((step) => step.key === props.order.status),
);

let timer: ReturnType<typeof setInterval> | null = null;

function refresh(): void {
    router.reload({ only: ['order'] });
}

onMounted(() => {
    timer = setInterval(refresh, 20000);
});

onUnmounted(() => {
    if (timer !== null) {
        clearInterval(timer);
    }
});
</script>

<template>
    <Head :title="`Order ${order.reference}`" />

    <div class="min-h-screen bg-stone-100 px-4 py-10 text-stone-900">
        <div class="mx-auto w-full max-w-xl space-y-4">
            <a
                :href="`/restaurant/${encodeURIComponent(restaurant.username)}`"
                class="inline-flex items-center gap-1.5 text-sm text-stone-600 hover:text-stone-900"
            >
                <ArrowLeft class="size-4" />
                Back to {{ restaurant.name }}
            </a>

            <div class="rounded-2xl border bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium uppercase text-stone-500">
                            Order
                        </p>
                        <h1 class="font-mono text-2xl font-semibold">
                            {{ order.reference }}
                        </h1>
                        <p class="mt-1 text-sm text-stone-500">
                            {{ order.type_label }}
                            <span v-if="order.table_number">
                                · Table {{ order.table_number }}
                            </span>
                            · {{ order.created_human }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            :class="order.status_class"
                            class="rounded-full px-3 py-1 text-xs font-semibold"
                        >
                            {{ order.status_label }}
                        </span>
                        <button
                            type="button"
                            class="flex size-8 items-center justify-center rounded-full border text-stone-500 transition hover:bg-stone-50"
                            aria-label="Refresh status"
                            @click="refresh"
                        >
                            <RefreshCw class="size-3.5" />
                        </button>
                    </div>
                </div>

                <div
                    v-if="props.loyalty"
                    class="mt-5 flex items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                >
                    <div>
                        <p class="font-semibold">Loyalty balance</p>
                        <p class="text-xs opacity-80">
                            {{ props.loyalty.visits }} orders with this
                            restaurant
                        </p>
                    </div>
                    <span class="text-lg font-semibold tabular-nums">
                        {{ props.loyalty.points }} pts
                    </span>
                </div>

                <div
                    v-if="cancelled"
                    class="mt-5 flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
                >
                    <XCircle class="mt-0.5 size-4 shrink-0" />
                    <p>
                        This order was cancelled. Please speak to the
                        restaurant if you have any questions.
                    </p>
                </div>

                <ol v-else class="mt-6 space-y-4">
                    <li
                        v-for="(step, index) in steps"
                        :key="step.key"
                        class="flex items-start gap-3"
                    >
                        <span
                            class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full border text-xs font-semibold"
                            :class="
                                index < activeIndex
                                    ? 'border-emerald-600 bg-emerald-600 text-white'
                                    : index === activeIndex
                                      ? 'border-amber-500 bg-amber-500 text-white'
                                      : 'border-stone-200 bg-white text-stone-400'
                            "
                        >
                            <Check
                                v-if="index < activeIndex"
                                class="size-3.5"
                            />
                            <ChefHat
                                v-else-if="
                                    index === activeIndex &&
                                    step.key === 'preparing'
                                "
                                class="size-3.5"
                            />
                            <Clock
                                v-else-if="
                                    index === activeIndex &&
                                    step.key !== 'new'
                                "
                                class="size-3.5"
                            />
                            <span v-else>{{ index + 1 }}</span>
                        </span>
                        <div>
                            <p
                                class="text-sm font-medium"
                                :class="
                                    index <= activeIndex
                                        ? 'text-stone-900'
                                        : 'text-stone-400'
                                "
                            >
                                {{ step.label }}
                            </p>
                            <p
                                v-if="index === activeIndex"
                                class="text-xs text-stone-500"
                            >
                                {{
                                    step.key === 'new'
                                        ? 'We have your order and the kitchen will start shortly.'
                                        : step.key === 'preparing'
                                          ? 'Your order is being prepared.'
                                          : step.key === 'ready'
                                            ? 'Your order is ready.'
                                            : 'This order is complete. Thank you!'
                                }}
                            </p>
                        </div>
                    </li>
                </ol>

                <p
                    v-if="order.created_at"
                    class="mt-4 text-xs text-stone-400"
                >
                    Placed {{ order.created_at }}
                </p>
            </div>

            <div class="rounded-2xl border bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold">Your order</h2>

                <ul class="mt-3 divide-y">
                    <li
                        v-for="item in order.items"
                        :key="item.id"
                        class="flex items-start justify-between gap-3 py-3 text-sm"
                    >
                        <div>
                            <p class="font-medium">
                                {{ item.quantity }} × {{ item.name }}
                            </p>
                            <p
                                v-for="option in item.options"
                                :key="`${option.group}-${option.name}`"
                                class="text-xs text-stone-500"
                            >
                                {{ option.group }}: {{ option.name }}
                            </p>
                            <p
                                v-if="item.notes"
                                class="text-xs text-stone-500"
                            >
                                {{ item.notes }}
                            </p>
                        </div>
                        <span class="shrink-0 tabular-nums">
                            {{ item.line_total }}
                        </span>
                    </li>
                </ul>

                <div class="mt-3 space-y-1 border-t pt-3 text-xs">
                    <div class="flex justify-between gap-4">
                        <span class="text-stone-500">Subtotal</span>
                        <span class="tabular-nums">{{ order.subtotal }}</span>
                    </div>
                    <div
                        v-if="order.coupon_code"
                        class="flex justify-between gap-4 text-emerald-700"
                    >
                        <span>Discount ({{ order.coupon_code }})</span>
                        <span class="tabular-nums">−{{ order.discount }}</span>
                    </div>
                    <div
                        v-if="order.points_redeemed > 0"
                        class="flex items-center justify-between gap-3"
                    >
                        <span>Loyalty ({{ order.points_redeemed }} pts)</span>
                        <span class="tabular-nums">−{{ order.loyalty_discount }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-stone-500">Service</span>
                        <span class="tabular-nums">{{ order.service }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-stone-500">Delivery</span>
                        <span class="tabular-nums">{{
                            order.delivery_fee
                        }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-stone-500">Tax</span>
                        <span class="tabular-nums">{{ order.tax }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-stone-500">Tip</span>
                        <span class="tabular-nums">{{ order.tip }}</span>
                    </div>
                    <div
                        class="flex justify-between gap-4 border-t pt-2 text-sm font-semibold"
                    >
                        <span>Total</span>
                        <span class="tabular-nums">{{ order.total }}</span>
                    </div>
                </div>
            </div>

            <div
                v-if="order.delivery_address"
                class="rounded-2xl border bg-white p-6 shadow-sm"
            >
                <h2 class="text-sm font-semibold">Delivery address</h2>
                <p class="mt-2 flex items-start gap-2 text-sm text-stone-600">
                    <MapPin class="mt-0.5 size-4 shrink-0" />
                    {{ order.delivery_address }}
                </p>
            </div>

            <div class="rounded-2xl border bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold">{{ restaurant.name }}</h2>
                <p
                    v-if="restaurant.address"
                    class="mt-2 flex items-start gap-2 text-sm text-stone-600"
                >
                    <MapPin class="mt-0.5 size-4 shrink-0" />
                    {{ restaurant.address }}
                </p>
                <a
                    v-if="restaurant.phone"
                    :href="`tel:${restaurant.phone}`"
                    class="mt-1 inline-flex items-center gap-2 text-sm text-stone-600 hover:text-stone-900"
                >
                    <Phone class="size-4" />
                    {{ restaurant.phone }}
                </a>
            </div>
        </div>
    </div>
</template>
