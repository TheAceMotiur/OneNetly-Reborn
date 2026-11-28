<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BarChart3,
    CalendarDays,
    ChefHat,
    ClipboardList,
    History,
    LayoutGrid,
    Mail,
    MessageSquareQuote,
    Truck,
    Users,
    Utensils,
} from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    restaurant: { username: string; name: string };
    active:
        | 'overview'
        | 'menu'
        | 'orders'
        | 'kitchen'
        | 'customers'
        | 'reservations'
        | 'delivery'
        | 'activity'
        | 'reviews'
        | 'subscribers'
        | 'reports';
}>();

const base = computed(
    () => `/restaurant/${encodeURIComponent(props.restaurant.username)}`,
);

const tabs = computed(() => [
    {
        key: 'overview',
        label: 'Overview',
        href: `/restaurant?restaurant=${encodeURIComponent(props.restaurant.username)}`,
        icon: LayoutGrid,
    },
    {
        key: 'menu',
        label: 'Menu',
        href: `${base.value}/menu`,
        icon: Utensils,
    },
    {
        key: 'orders',
        label: 'Orders',
        href: `${base.value}/orders`,
        icon: ClipboardList,
    },
    {
        key: 'kitchen',
        label: 'Kitchen',
        href: `${base.value}/kitchen`,
        icon: ChefHat,
    },
    {
        key: 'customers',
        label: 'Customers',
        href: `${base.value}/customers`,
        icon: Users,
    },
    {
        key: 'reservations',
        label: 'Reservations',
        href: `${base.value}/reservations`,
        icon: CalendarDays,
    },
    {
        key: 'delivery',
        label: 'Delivery',
        href: `${base.value}/delivery`,
        icon: Truck,
    },
    {
        key: 'reviews',
        label: 'Reviews',
        href: `${base.value}/reviews`,
        icon: MessageSquareQuote,
    },
    {
        key: 'subscribers',
        label: 'Subscribers',
        href: `/subscribers/${encodeURIComponent(props.restaurant.username)}`,
        icon: Mail,
    },
    {
        key: 'reports',
        label: 'Reports',
        href: `${base.value}/reports`,
        icon: BarChart3,
    },
    {
        key: 'activity',
        label: 'Activity',
        href: `${base.value}/activity`,
        icon: History,
    },
]);
</script>

<template>
    <nav class="flex gap-1 overflow-x-auto rounded-lg border bg-muted/30 p-1">
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.href"
            class="inline-flex shrink-0 items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition"
            :class="
                tab.key === props.active
                    ? 'bg-background text-foreground shadow-sm'
                    : 'text-muted-foreground hover:text-foreground'
            "
        >
            <component :is="tab.icon" class="size-4" />
            {{ tab.label }}
        </Link>
    </nav>
</template>
