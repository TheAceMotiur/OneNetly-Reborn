<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    Check,
    ExternalLink,
    Mail,
    Phone,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import RestaurantTabs from '@/components/RestaurantTabs.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Reservation = {
    id: number;
    name: string;
    phone: string;
    email: string | null;
    party_size: number;
    date: string;
    date_label: string;
    time: string;
    notes: string | null;
    status: string;
    status_label: string;
    status_class: string;
    created_human: string | null;
};

const props = defineProps<{
    reservations: Reservation[];
    stats: {
        pending: number;
        upcoming: number;
        guests: number;
        waitlist: number;
    };
    statuses: Record<string, string>;
    restaurant: {
        username: string;
        name: string;
        public_url: string;
    };
    restaurants: { username: string; name: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Restaurant', href: '/restaurant' },
            { title: 'Reservations', href: '/restaurant' },
        ],
    },
});

const base = computed(
    () => `/restaurant/${encodeURIComponent(props.restaurant.username)}`,
);

const updatingId = ref<number | null>(null);

function updateStatus(reservation: Reservation, status: string): void {
    router.patch(
        `${base.value}/reservations/${reservation.id}`,
        { status },
        {
            preserveScroll: true,
            onStart: () => {
                updatingId.value = reservation.id;
            },
            onFinish: () => {
                updatingId.value = null;
            },
        },
    );
}

function switchRestaurant(event: Event): void {
    const username = (event.target as HTMLSelectElement).value;

    router.visit(`/restaurant/${encodeURIComponent(username)}/reservations`);
}
</script>

<template>
    <Head title="Reservations" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="`${props.restaurant.name} reservations`"
                description="Table requests from your public menu"
            />
            <Button variant="outline" as-child>
                <a :href="props.restaurant.public_url" target="_blank">
                    <ExternalLink class="size-4" />
                    Open menu
                </a>
            </Button>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <RestaurantTabs
                :restaurant="props.restaurant"
                active="reservations"
            />
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
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardContent class="pt-5">
                    <p class="text-sm text-muted-foreground">
                        Waiting to confirm
                    </p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ props.stats.pending }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-5">
                    <p class="text-sm text-muted-foreground">
                        Confirmed upcoming
                    </p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ props.stats.upcoming }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-5">
                    <p class="text-sm text-muted-foreground">
                        Guests expected
                    </p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ props.stats.guests }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-5">
                    <p class="text-sm text-muted-foreground">
                        On the waitlist
                    </p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ props.stats.waitlist }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Confirm one to offer the table
                    </p>
                </CardContent>
            </Card>
        </div>

        <div v-if="props.reservations.length" class="grid gap-3">
            <Card v-for="reservation in props.reservations" :key="reservation.id">
                <CardContent
                    class="flex flex-wrap items-start justify-between gap-4 pt-5"
                >
                    <div class="flex min-w-0 items-start gap-4">
                        <div
                            class="flex w-20 shrink-0 flex-col items-center rounded-lg border bg-muted/30 px-2 py-2 text-center"
                        >
                            <CalendarDays class="size-4 text-muted-foreground" />
                            <p class="mt-1 text-xs font-medium">
                                {{ reservation.date_label }}
                            </p>
                            <p class="text-sm font-semibold">
                                {{ reservation.time }}
                            </p>
                        </div>

                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold">
                                    {{ reservation.name }}
                                </p>
                                <span
                                    :class="reservation.status_class"
                                    class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                >
                                    {{ reservation.status_label }}
                                </span>
                            </div>
                            <p
                                class="flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <Users class="size-3.5" />
                                {{ reservation.party_size }}
                                {{
                                    reservation.party_size === 1
                                        ? 'guest'
                                        : 'guests'
                                }}
                            </p>
                            <div
                                class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm"
                            >
                                <a
                                    :href="`tel:${reservation.phone}`"
                                    class="inline-flex items-center gap-1 text-muted-foreground hover:text-foreground"
                                >
                                    <Phone class="size-3.5" />
                                    {{ reservation.phone }}
                                </a>
                                <a
                                    v-if="reservation.email"
                                    :href="`mailto:${reservation.email}`"
                                    class="inline-flex items-center gap-1 text-muted-foreground hover:text-foreground"
                                >
                                    <Mail class="size-3.5" />
                                    {{ reservation.email }}
                                </a>
                            </div>
                            <p
                                v-if="reservation.notes"
                                class="rounded-md bg-muted/40 px-2 py-1 text-xs text-muted-foreground"
                            >
                                {{ reservation.notes }}
                            </p>
                            <p
                                v-if="reservation.created_human"
                                class="text-[11px] text-muted-foreground"
                            >
                                Requested {{ reservation.created_human }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-if="reservation.status !== 'confirmed'"
                            size="sm"
                            :disabled="updatingId === reservation.id"
                            @click="updateStatus(reservation, 'confirmed')"
                        >
                            <Check class="size-4" />
                            Confirm
                        </Button>
                        <Button
                            v-if="reservation.status !== 'cancelled'"
                            variant="outline"
                            size="sm"
                            :disabled="updatingId === reservation.id"
                            @click="updateStatus(reservation, 'cancelled')"
                        >
                            <X class="size-4" />
                            Cancel
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div
            v-else
            class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-6 py-16 text-center text-muted-foreground"
        >
            <CalendarDays class="size-8" />
            <p class="font-medium text-foreground">No reservations yet</p>
            <p class="max-w-sm text-sm">
                Guests can request a table from your public menu. Requests appear
                here for you to confirm or cancel.
            </p>
            <Button class="mt-2" variant="outline" as-child>
                <a :href="props.restaurant.public_url" target="_blank">
                    Open public menu
                </a>
            </Button>
        </div>
    </div>
</template>
