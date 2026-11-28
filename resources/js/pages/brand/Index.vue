<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowUpRight, Store, Utensils } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';

type Location = {
    username: string;
    name: string;
    orders: number;
    revenue_cents: number;
    new_orders: number;
    reservations: number;
    customers: number;
    currency: string;
    menu_url: string;
    orders_url: string;
    reports_url: string;
};

const props = defineProps<{
    locations: Location[];
    totals: {
        locations: number;
        orders: number;
        revenue_cents: number;
        customers: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Brand overview', href: '/restaurant/brand' }],
    },
});

function money(cents: number, currency: string): string {
    return `${(cents / 100).toFixed(2)} ${currency}`;
}
</script>

<template>
    <Head title="Brand overview" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <Heading
            title="Brand overview"
            description="Compare the last 30 days across every location you can access"
        />

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Locations</p>
                <p class="mt-1 text-2xl font-semibold">{{ props.totals.locations }}</p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Orders · 30 days</p>
                <p class="mt-1 text-2xl font-semibold">{{ props.totals.orders }}</p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Revenue · 30 days</p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ money(props.totals.revenue_cents, locations[0]?.currency ?? 'USD') }}
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Customers</p>
                <p class="mt-1 text-2xl font-semibold">{{ props.totals.customers }}</p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="location in props.locations"
                :key="location.username"
                class="rounded-xl border p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="flex size-9 items-center justify-center rounded-full border bg-muted/40">
                            <Store class="size-4" />
                        </span>
                        <div>
                            <p class="font-semibold">{{ location.name }}</p>
                            <p class="text-xs text-muted-foreground">@{{ location.username }}</p>
                        </div>
                    </div>
                    <span
                        v-if="location.new_orders"
                        class="rounded-full bg-amber-500/15 px-2 py-1 text-[11px] font-semibold text-amber-700"
                    >
                        {{ location.new_orders }} new
                    </span>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="text-xs text-muted-foreground">Orders</p>
                        <p class="font-semibold">{{ location.orders }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Revenue</p>
                        <p class="font-semibold">{{ money(location.revenue_cents, location.currency) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Reservations</p>
                        <p class="font-semibold">{{ location.reservations }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Customers</p>
                        <p class="font-semibold">{{ location.customers }}</p>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    <Button size="sm" variant="outline" as-child>
                        <a :href="location.menu_url">
                            <Utensils class="size-4" />
                            Menu
                        </a>
                    </Button>
                    <Button size="sm" variant="outline" as-child>
                        <a :href="location.orders_url">
                            Orders
                            <ArrowUpRight class="size-4" />
                        </a>
                    </Button>
                    <Button size="sm" variant="ghost" as-child>
                        <a :href="location.reports_url">Reports</a>
                    </Button>
                </div>
            </article>
        </div>

        <p
            v-if="props.locations.length === 0"
            class="rounded-xl border border-dashed px-6 py-16 text-center text-sm text-muted-foreground"
        >
            Add a restaurant to start comparing locations.
        </p>
    </div>
</template>
