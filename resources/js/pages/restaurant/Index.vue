<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    BellRing,
    Check,
    Download,
    ExternalLink,
    Gift,
    Plus,
    QrCode,
    Store,
    Users,
    Utensils,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import RestaurantTabs from '@/components/RestaurantTabs.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type RestaurantSummary = {
    username: string;
    name: string;
    public_url: string;
    is_published: boolean;
    ordering_enabled: boolean;
    accepting_orders: boolean;
    item_count: number;
    orders_count: number;
};

type RecentOrder = {
    id: number;
    reference: string;
    customer_name: string;
    type_label: string;
    table_number: string | null;
    total: string;
    status_label: string;
    status_class: string;
    created_human: string | null;
    items_count: number;
};

type OpeningDay = {
    closed: boolean;
    open: string;
    close: string;
};

type SelectedRestaurant = {
    username: string;
    name: string;
    description: string | null;
    address: string | null;
    phone: string | null;
    currency: string;
    template: string;
    ordering_enabled: boolean;
    accepting_orders: boolean;
    delivery_enabled: boolean;
    delivery_fee_cents: number;
    min_order_cents: number;
    tax_rate: number;
    service_rate: number;
    sms_notifications_enabled: boolean;
    loyalty_enabled: boolean;
    reservation_slot_capacity: number;
    reservation_max_party: number;
    reservation_auto_confirm: boolean;
    reservation_reminder_hours: number;
    opening_hours: OpeningDay[] | null;
    timezone: string;
    is_published: boolean;
    public_url: string;
    menu_url: string;
    orders_url: string;
    qr_url: string;
    item_count: number;
    category_count: number;
    stats: {
        today_orders: number;
        today_total: string;
        new_orders: number;
    };
    loyalty: {
        members: { name: string; phone: string; points: number; visits: number }[];
        members_count: number;
    };
    recent_orders: RecentOrder[];
};

type TeamMember = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    remove_url: string;
};

type ServiceRequestSummary = {
    id: number;
    type_label: string;
    table_number: string | null;
    restaurant: string;
    created_human: string | null;
    complete_url: string;
};

const props = defineProps<{
    restaurants: RestaurantSummary[];
    selected: SelectedRestaurant | null;
    totals: {
        restaurants: number;
        orders_today: number;
        new_orders: number;
        pending_requests: number;
        views_today: number;
        views_week: number;
    };
    serviceRequests: ServiceRequestSummary[];
    currencies: Record<string, string>;
    templates: Record<string, string>;
    timezones: Record<string, string>;
    weekdays: string[];
    can_manage_team: boolean;
    team_store_url: string | null;
    team: TeamMember[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Restaurant', href: '/restaurant' }],
    },
});

const createOpen = ref(false);

const createForm = useForm({
    name: '',
    username: '',
    description: '',
    address: '',
    phone: '',
});

function hoursFrom(hours: OpeningDay[] | null): OpeningDay[] {
    return Array.from({ length: 7 }, (_, day) => {
        const row = hours?.[day];

        return {
            closed: row?.closed ?? false,
            open: row?.open ?? '09:00',
            close: row?.close ?? '17:00',
        };
    });
}

const detailsForm = useForm({
    name: props.selected?.name ?? '',
    description: props.selected?.description ?? '',
    address: props.selected?.address ?? '',
    phone: props.selected?.phone ?? '',
    template: props.selected?.template ?? 'classic',
    delivery_enabled: props.selected?.delivery_enabled ?? true,
    delivery_fee: ((props.selected?.delivery_fee_cents ?? 0) / 100).toFixed(2),
    min_order: ((props.selected?.min_order_cents ?? 0) / 100).toFixed(2),
    tax_rate: String(props.selected?.tax_rate ?? 0),
    service_rate: String(props.selected?.service_rate ?? 0),
    sms_notifications_enabled:
        props.selected?.sms_notifications_enabled ?? false,
    loyalty_enabled: props.selected?.loyalty_enabled ?? true,
    reservation_slot_capacity: String(props.selected?.reservation_slot_capacity ?? 0),
    reservation_max_party: String(props.selected?.reservation_max_party ?? 20),
    reservation_auto_confirm: props.selected?.reservation_auto_confirm ?? false,
    reservation_reminder_hours: String(
        props.selected?.reservation_reminder_hours ?? 0,
    ),
    timezone: props.selected?.timezone ?? 'UTC',
    use_hours: props.selected?.opening_hours !== null,
    opening_hours: hoursFrom(props.selected?.opening_hours ?? null),
});

watch(
    () => props.selected?.username,
    () => {
        detailsForm.name = props.selected?.name ?? '';
        detailsForm.description = props.selected?.description ?? '';
        detailsForm.address = props.selected?.address ?? '';
        detailsForm.phone = props.selected?.phone ?? '';
        detailsForm.template = props.selected?.template ?? 'classic';
        detailsForm.delivery_enabled =
            props.selected?.delivery_enabled ?? true;
        detailsForm.delivery_fee = (
            (props.selected?.delivery_fee_cents ?? 0) / 100
        ).toFixed(2);
        detailsForm.min_order = (
            (props.selected?.min_order_cents ?? 0) / 100
        ).toFixed(2);
        detailsForm.tax_rate = String(props.selected?.tax_rate ?? 0);
        detailsForm.service_rate = String(props.selected?.service_rate ?? 0);
        detailsForm.sms_notifications_enabled =
            props.selected?.sms_notifications_enabled ?? false;
        detailsForm.loyalty_enabled = props.selected?.loyalty_enabled ?? true;
        detailsForm.reservation_slot_capacity = String(
            props.selected?.reservation_slot_capacity ?? 0,
        );
        detailsForm.reservation_max_party = String(
            props.selected?.reservation_max_party ?? 20,
        );
        detailsForm.reservation_auto_confirm =
            props.selected?.reservation_auto_confirm ?? false;
        detailsForm.reservation_reminder_hours = String(
            props.selected?.reservation_reminder_hours ?? 0,
        );
        detailsForm.timezone = props.selected?.timezone ?? 'UTC';
        detailsForm.use_hours =
            props.selected?.opening_hours !== null &&
            props.selected?.opening_hours !== undefined;
        detailsForm.opening_hours = hoursFrom(
            props.selected?.opening_hours ?? null,
        );
        detailsForm.clearErrors();
    },
);

const teamForm = reactive({ email: '', role: 'staff' });
const teamErrors = ref<Record<string, string>>({});
const addingMember = ref(false);

function addTeamMember(): void {
    if (props.team_store_url === null) {
        return;
    }

    router.post(props.team_store_url, { ...teamForm }, {
        preserveScroll: true,
        onStart: () => {
            addingMember.value = true;
        },
        onFinish: () => {
            addingMember.value = false;
        },
        onError: (errors) => {
            teamErrors.value = errors;
        },
        onSuccess: () => {
            teamForm.email = '';
            teamForm.role = 'staff';
            teamErrors.value = {};
        },
    });
}

function removeTeamMember(member: TeamMember): void {
    if (!confirm(`Remove ${member.name} from the team?`)) {
        return;
    }

    router.delete(member.remove_url, { preserveScroll: true });
}

const tableNumber = ref('1');

const tableQrUrl = computed(() =>
    props.selected === null
        ? ''
        : `${props.selected.qr_url}?table=${encodeURIComponent(tableNumber.value || '1')}`,
);
const tableQrDownloadUrl = computed(() => `${tableQrUrl.value}&download=1`);

function submitCreate(): void {
    createForm.post('/restaurant', {
        preserveScroll: true,
        onSuccess: () => {
            createOpen.value = false;
            createForm.reset();
        },
    });
}

function saveDetails(): void {
    if (props.selected === null) {
        return;
    }

    detailsForm.transform((data) => ({
        ...data,
        opening_hours: data.use_hours ? data.opening_hours : null,
    }));

    detailsForm.patch(
        `/restaurant/${encodeURIComponent(props.selected.username)}`,
        { preserveScroll: true },
    );
}

function completeRequest(guestRequest: ServiceRequestSummary): void {
    router.patch(
        guestRequest.complete_url,
        {},
        { preserveScroll: true, preserveState: true },
    );
}

let refreshTimer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    refreshTimer = setInterval(() => {
        router.reload({ only: ['serviceRequests', 'totals'] });
    }, 20000);
});

onUnmounted(() => {
    if (refreshTimer !== null) {
        clearInterval(refreshTimer);
    }
});
</script>

<template>
    <Head title="Restaurant" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <Heading
                    title="Restaurant"
                    description="Menus, orders and table QR codes for every restaurant you run"
                />
                <Button variant="outline" size="sm" as-child>
                    <a href="/restaurant/brand">Brand overview</a>
                </Button>
                <p class="text-sm text-muted-foreground">
                    {{ props.totals.restaurants }}
                    {{ props.totals.restaurants === 1 ? 'restaurant' : 'restaurants' }}
                    ·                     {{ props.totals.orders_today }} orders today ·
                    {{ props.totals.new_orders }} waiting ·
                    {{ props.totals.views_today }} menu views today
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Dialog v-model:open="createOpen">
                    <DialogTrigger as-child>
                        <Button>
                            <Plus class="size-4" />
                            New restaurant
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Add a restaurant</DialogTitle>
                            <DialogDescription>
                                Each restaurant gets its own public menu page,
                                orders inbox and table QR codes.
                            </DialogDescription>
                        </DialogHeader>

                        <form class="space-y-3" @submit.prevent="submitCreate">
                            <div class="grid gap-2">
                                <Label for="restaurant-name">Name</Label>
                                <Input
                                    id="restaurant-name"
                                    v-model="createForm.name"
                                    placeholder="Joe's Pizza"
                                    required
                                />
                                <InputError :message="createForm.errors.name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="restaurant-username">
                                    Public address
                                </Label>
                                <div
                                    class="flex items-center overflow-hidden rounded-md border border-input focus-within:border-ring"
                                >
                                    <span
                                        class="border-r bg-muted/50 px-3 py-2 text-sm text-muted-foreground"
                                    >
                                        /
                                    </span>
                                    <input
                                        id="restaurant-username"
                                        v-model="createForm.username"
                                        class="h-9 w-full bg-transparent px-3 text-sm outline-none"
                                        placeholder="joes-pizza"
                                        required
                                    />
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Letters, numbers, dashes and underscores.
                                </p>
                                <InputError
                                    :message="createForm.errors.username"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="restaurant-description">
                                    Description
                                </Label>
                                <Input
                                    id="restaurant-description"
                                    v-model="createForm.description"
                                    placeholder="Wood-fired pizza and natural wine"
                                />
                                <InputError
                                    :message="createForm.errors.description"
                                />
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="restaurant-address">
                                        Address
                                    </Label>
                                    <Input
                                        id="restaurant-address"
                                        v-model="createForm.address"
                                        placeholder="12 Main Street"
                                    />
                                    <InputError
                                        :message="createForm.errors.address"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="restaurant-phone">Phone</Label>
                                    <Input
                                        id="restaurant-phone"
                                        v-model="createForm.phone"
                                        placeholder="+1 555 0100"
                                    />
                                    <InputError
                                        :message="createForm.errors.phone"
                                    />
                                </div>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="submit"
                                    :disabled="createForm.processing"
                                >
                                    Add restaurant
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <Button
                    v-if="props.selected"
                    variant="outline"
                    as-child
                >
                    <a :href="props.selected.public_url" target="_blank">
                        <ExternalLink class="size-4" />
                        View menu
                    </a>
                </Button>
            </div>
        </div>

        <div
            v-if="props.restaurants.length"
            class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
        >
            <Link
                v-for="restaurant in props.restaurants"
                :key="restaurant.username"
                :href="`/restaurant?restaurant=${encodeURIComponent(restaurant.username)}`"
                class="rounded-xl border p-4 transition hover:-translate-y-0.5 hover:shadow-sm"
                :class="
                    props.selected?.username === restaurant.username
                        ? 'border-primary bg-primary/5'
                        : 'bg-card'
                "
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ restaurant.name }}</p>
                        <p class="truncate text-xs text-muted-foreground">
                            /{{ restaurant.username }}
                        </p>
                    </div>
                    <Store class="size-4 shrink-0 text-muted-foreground" />
                </div>
                <div class="mt-3 flex flex-wrap gap-1.5 text-[11px]">
                    <span class="rounded-full border px-2 py-0.5">
                        {{ restaurant.item_count }} items
                    </span>
                    <span class="rounded-full border px-2 py-0.5">
                        {{ restaurant.orders_count }} orders
                    </span>
                    <span
                        v-if="restaurant.ordering_enabled"
                        class="rounded-full bg-emerald-500/10 px-2 py-0.5 text-emerald-700 dark:text-emerald-300"
                    >
                        Ordering on
                    </span>
                    <span
                        v-if="
                            restaurant.ordering_enabled &&
                            !restaurant.accepting_orders
                        "
                        class="rounded-full bg-amber-500/10 px-2 py-0.5 text-amber-700 dark:text-amber-300"
                    >
                        Paused
                    </span>
                </div>
            </Link>
        </div>

        <template v-if="props.selected">
            <RestaurantTabs
                :restaurant="{
                    username: props.selected.username,
                    name: props.selected.name,
                }"
                active="overview"
            />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium"
                            >Orders today</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-semibold">
                            {{ props.selected.stats.today_orders }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            At {{ props.selected.name }}
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
                            {{ props.selected.stats.today_total }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Paid at the table, pickup or delivery
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium"
                            >New orders</CardTitle
                        >
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-semibold">
                            {{ props.selected.stats.new_orders }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Waiting for the kitchen
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium">Menu</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-semibold">
                            {{ props.selected.item_count }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Items in {{ props.selected.category_count }}
                            {{ props.selected.category_count === 1 ? 'section' : 'sections' }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div
                class="grid gap-4 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]"
            >
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle class="text-base">Recent orders</CardTitle>
                        <Link
                            :href="props.selected.orders_url"
                            class="text-sm text-muted-foreground underline-offset-4 hover:underline"
                        >
                            All orders
                        </Link>
                    </CardHeader>
                    <CardContent>
                        <ul
                            v-if="props.selected.recent_orders.length"
                            class="divide-y"
                        >
                            <li
                                v-for="order in props.selected.recent_orders"
                                :key="order.id"
                                class="flex flex-wrap items-center justify-between gap-2 py-3 first:pt-0 last:pb-0"
                            >
                                <div class="min-w-0">
                                    <p class="font-mono text-sm font-medium">
                                        {{ order.reference }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ order.customer_name }} ·
                                        {{ order.type_label }}
                                        <span v-if="order.table_number">
                                            · Table {{ order.table_number }}
                                        </span>
                                        · {{ order.items_count }} items ·
                                        {{ order.created_human }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        :class="order.status_class"
                                        class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                                    >
                                        {{ order.status_label }}
                                    </span>
                                    <span
                                        class="text-sm font-semibold tabular-nums"
                                    >
                                        {{ order.total }}
                                    </span>
                                </div>
                            </li>
                        </ul>

                        <div
                            v-else
                            class="flex flex-col items-center gap-2 py-8 text-center"
                        >
                            <p class="text-sm font-medium">No orders yet</p>
                            <p class="max-w-sm text-xs text-muted-foreground">
                                Add dishes to the menu and enable ordering, then
                                share your table QR code. Orders land here and
                                in your inbox.
                            </p>
                            <Button variant="outline" size="sm" as-child>
                                <a :href="props.selected.menu_url">
                                    <Utensils class="size-4" />
                                    Open menu builder
                                </a>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <div class="space-y-4">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <BellRing class="size-4" />
                                Guest requests
                            </CardTitle>
                            <span
                                v-if="props.serviceRequests.length"
                                class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-300"
                            >
                                {{ props.serviceRequests.length }} open
                            </span>
                        </CardHeader>
                        <CardContent>
                            <ul
                                v-if="props.serviceRequests.length"
                                class="divide-y"
                            >
                                <li
                                    v-for="guestRequest in props.serviceRequests"
                                    :key="guestRequest.id"
                                    class="flex flex-wrap items-center justify-between gap-2 py-3 first:pt-0 last:pb-0"
                                >
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium">
                                            {{ guestRequest.type_label }}
                                            <span
                                                v-if="guestRequest.table_number"
                                            >
                                                · Table
                                                {{ guestRequest.table_number }}
                                            </span>
                                        </p>
                                        <p
                                            class="truncate text-xs text-muted-foreground"
                                        >
                                            {{ guestRequest.restaurant }} ·
                                            {{ guestRequest.created_human }}
                                        </p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="completeRequest(guestRequest)"
                                    >
                                        <Check class="size-4" />
                                        Done
                                    </Button>
                                </li>
                            </ul>

                            <div
                                v-else
                                class="flex flex-col items-center gap-1 py-6 text-center"
                            >
                                <p class="text-sm font-medium">
                                    No open requests
                                </p>
                                <p
                                    class="max-w-xs text-xs text-muted-foreground"
                                >
                                    Guests can call a waiter, ask for the bill
                                    or request a clean table from the QR menu.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base"
                                >Restaurant details</CardTitle
                            >
                        </CardHeader>
                        <CardContent>
                            <form
                                class="space-y-3"
                                @submit.prevent="saveDetails"
                            >
                                <div class="grid gap-2">
                                    <Label for="details-name">Name</Label>
                                    <Input
                                        id="details-name"
                                        v-model="detailsForm.name"
                                        required
                                    />
                                    <InputError
                                        :message="detailsForm.errors.name"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="details-description">
                                        Description
                                    </Label>
                                    <Input
                                        id="details-description"
                                        v-model="detailsForm.description"
                                    />
                                    <InputError
                                        :message="detailsForm.errors.description"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="details-address">Address</Label>
                                    <Input
                                        id="details-address"
                                        v-model="detailsForm.address"
                                    />
                                    <InputError
                                        :message="detailsForm.errors.address"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="details-template">
                                        Menu template
                                    </Label>
                                    <select
                                        id="details-template"
                                        v-model="detailsForm.template"
                                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option
                                            v-for="(label, key) in props.templates"
                                            :key="key"
                                            :value="key"
                                        >
                                            {{ label }}
                                        </option>
                                    </select>
                                    <p class="text-xs text-muted-foreground">
                                        Changes the layout, colours and typography
                                        of your public menu page.
                                    </p>
                                    <InputError
                                        :message="detailsForm.errors.template"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="details-phone">Phone</Label>
                                    <Input
                                        id="details-phone"
                                        v-model="detailsForm.phone"
                                    />
                                    <InputError
                                        :message="detailsForm.errors.phone"
                                    />
                                </div>

                                <div class="space-y-3 rounded-lg border bg-muted/20 p-3">
                                    <div>
                                        <p class="text-sm font-medium">
                                            Order charges
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            Applied to guest orders at checkout.
                                            Leave at 0 to charge only the menu
                                            prices.
                                        </p>
                                    </div>

                                    <label
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            v-model="
                                                detailsForm.delivery_enabled
                                            "
                                            type="checkbox"
                                            class="size-4 rounded border-input"
                                        />
                                        Offer delivery
                                    </label>
                                    <InputError
                                        :message="
                                            detailsForm.errors
                                                .delivery_enabled
                                        "
                                    />

                                    <label
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            v-model="
                                                detailsForm.sms_notifications_enabled
                                            "
                                            type="checkbox"
                                            class="size-4 rounded border-input"
                                        />
                                        Text guests about order and booking
                                        updates
                                    </label>
                                    <InputError
                                        :message="
                                            detailsForm.errors
                                                .sms_notifications_enabled
                                        "
                                    />

                                    <label
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            v-model="
                                                detailsForm.loyalty_enabled
                                            "
                                            type="checkbox"
                                            class="size-4 rounded border-input"
                                        />
                                        Award loyalty points on orders
                                    </label>
                                    <InputError
                                        :message="
                                            detailsForm.errors
                                                .loyalty_enabled
                                        "
                                    />

                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div class="grid gap-2">
                                            <Label for="details-slot-capacity">
                                                Guests per time slot
                                            </Label>
                                            <Input
                                                id="details-slot-capacity"
                                                v-model="detailsForm.reservation_slot_capacity"
                                                type="number"
                                                min="0"
                                                max="500"
                                            />
                                            <p class="text-xs text-muted-foreground">
                                                0 means unlimited
                                            </p>
                                            <InputError
                                                :message="detailsForm.errors.reservation_slot_capacity"
                                            />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label for="details-max-party">
                                                Largest party
                                            </Label>
                                            <Input
                                                id="details-max-party"
                                                v-model="detailsForm.reservation_max_party"
                                                type="number"
                                                min="1"
                                                max="100"
                                            />
                                            <InputError
                                                :message="detailsForm.errors.reservation_max_party"
                                            />
                                        </div>
                                    </div>
                                    <label class="flex items-center gap-2 text-sm">
                                        <input
                                            v-model="detailsForm.reservation_auto_confirm"
                                            type="checkbox"
                                            class="size-4 rounded border-input"
                                        />
                                        Confirm bookings automatically
                                    </label>
                                    <InputError
                                        :message="detailsForm.errors.reservation_auto_confirm"
                                    />
                                    <div class="grid max-w-xs gap-2">
                                        <Label for="details-reminder-hours">
                                            SMS reminder before the booking
                                        </Label>
                                        <Input
                                            id="details-reminder-hours"
                                            v-model="detailsForm.reservation_reminder_hours"
                                            type="number"
                                            min="0"
                                            max="72"
                                        />
                                        <p class="text-xs text-muted-foreground">
                                            Hours before the table. 0 turns
                                            reminders off.
                                        </p>
                                        <InputError
                                            :message="detailsForm.errors.reservation_reminder_hours"
                                        />
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div class="grid gap-2">
                                            <Label for="details-tax">
                                                Tax rate (%)
                                            </Label>
                                            <Input
                                                id="details-tax"
                                                v-model="detailsForm.tax_rate"
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                            />
                                            <InputError
                                                :message="
                                                    detailsForm.errors
                                                        .tax_rate
                                                "
                                            />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label for="details-service">
                                                Service charge (%)
                                            </Label>
                                            <Input
                                                id="details-service"
                                                v-model="
                                                    detailsForm.service_rate
                                                "
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.01"
                                            />
                                            <InputError
                                                :message="
                                                    detailsForm.errors
                                                        .service_rate
                                                "
                                            />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label for="details-delivery-fee">
                                                Delivery fee
                                            </Label>
                                            <Input
                                                id="details-delivery-fee"
                                                v-model="
                                                    detailsForm.delivery_fee
                                                "
                                                type="number"
                                                min="0"
                                                step="0.01"
                                            />
                                            <InputError
                                                :message="
                                                    detailsForm.errors
                                                        .delivery_fee
                                                "
                                            />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label for="details-min-order">
                                                Minimum order
                                            </Label>
                                            <Input
                                                id="details-min-order"
                                                v-model="detailsForm.min_order"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                            />
                                            <InputError
                                                :message="
                                                    detailsForm.errors
                                                        .min_order
                                                "
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-3 rounded-lg border bg-muted/20 p-3">
                                    <div>
                                        <p class="text-sm font-medium">
                                            Opening hours
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            Guest ordering only accepts orders
                                            during these hours. Leave this off
                                            to take orders at any time.
                                        </p>
                                    </div>

                                    <label class="flex items-center gap-2 text-sm">
                                        <input
                                            v-model="detailsForm.use_hours"
                                            type="checkbox"
                                            class="size-4 rounded border-input"
                                        />
                                        Set opening hours
                                    </label>

                                    <div
                                        v-if="detailsForm.use_hours"
                                        class="space-y-2"
                                    >
                                        <div class="grid gap-2">
                                            <Label for="details-timezone">
                                                Timezone
                                            </Label>
                                            <select
                                                id="details-timezone"
                                                v-model="detailsForm.timezone"
                                                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            >
                                                <option
                                                    v-for="(
                                                        label, value
                                                    ) in props.timezones"
                                                    :key="value"
                                                    :value="value"
                                                >
                                                    {{ label }} ({{ value }})
                                                </option>
                                            </select>
                                            <InputError
                                                :message="
                                                    detailsForm.errors
                                                        .timezone
                                                "
                                            />
                                        </div>

                                        <div
                                            v-for="(day, index) in detailsForm.opening_hours"
                                            :key="index"
                                            class="grid items-center gap-2 sm:grid-cols-[110px_auto_1fr_1fr]"
                                        >
                                            <span class="text-sm font-medium">
                                                {{ props.weekdays[index] }}
                                            </span>
                                            <label
                                                class="flex items-center gap-1.5 text-xs text-muted-foreground"
                                            >
                                                <input
                                                    v-model="day.closed"
                                                    type="checkbox"
                                                    class="size-3.5 rounded border-input"
                                                />
                                                Closed
                                            </label>
                                            <Input
                                                v-model="day.open"
                                                type="time"
                                                :disabled="day.closed"
                                                aria-label="Opens at"
                                            />
                                            <Input
                                                v-model="day.close"
                                                type="time"
                                                :disabled="day.closed"
                                                aria-label="Closes at"
                                            />
                                        </div>
                                        <InputError
                                            :message="
                                                detailsForm.errors
                                                    .opening_hours
                                            "
                                        />
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2 pt-1">
                                    <Button
                                        type="submit"
                                        :disabled="detailsForm.processing"
                                    >
                                        Save details
                                    </Button>
                                    <Button variant="outline" as-child>
                                        <a :href="props.selected?.menu_url">
                                            <Utensils class="size-4" />
                                            Menu
                                        </a>
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <Card v-if="props.selected.loyalty_enabled">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Gift class="size-4" />
                                Loyalty
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <p class="text-sm text-muted-foreground">
                                Guests earn one point per currency unit spent.
                                {{ props.selected.loyalty.members_count }}
                                {{ props.selected.loyalty.members_count === 1 ? 'guest has' : 'guests have' }}
                                joined.
                            </p>
                            <div
                                v-if="props.selected.loyalty.members.length"
                                class="divide-y rounded-lg border"
                            >
                                <div
                                    v-for="member in props.selected.loyalty.members"
                                    :key="member.phone"
                                    class="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                                >
                                    <div>
                                        <p class="font-medium">
                                            {{ member.name }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ member.phone }} ·
                                            {{ member.visits }} visits
                                        </p>
                                    </div>
                                    <span class="font-semibold tabular-nums">
                                        {{ member.points }} pts
                                    </span>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                Points appear after the first phone order.
                            </p>
                        </CardContent>
                    </Card>

                    <Card v-if="props.can_manage_team">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Users class="size-4" />
                                Team
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <p class="text-sm text-muted-foreground">
                                Managers can change the menu and settings.
                                Staff can handle orders and reservations.
                            </p>

                            <div
                                v-if="props.team.length"
                                class="divide-y rounded-lg border"
                            >
                                <div
                                    v-for="member in props.team"
                                    :key="member.id"
                                    class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm"
                                >
                                    <div>
                                        <p class="font-medium">
                                            {{ member.name }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ member.email }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                        >
                                            {{ member.role_label }}
                                        </span>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="text-destructive"
                                            @click="removeTeamMember(member)"
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                No managers or staff yet. Add their email and we
                                will send an invitation.
                            </p>

                            <form
                                class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_140px_auto] sm:items-end"
                                @submit.prevent="addTeamMember"
                            >
                                <div class="grid gap-2">
                                    <Label for="team-email">Email</Label>
                                    <Input
                                        id="team-email"
                                        v-model="teamForm.email"
                                        type="email"
                                        placeholder="name@example.com"
                                        required
                                    />
                                    <InputError
                                        :message="teamErrors.email"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="team-role">Role</Label>
                                    <select
                                        id="team-role"
                                        v-model="teamForm.role"
                                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring"
                                    >
                                        <option value="staff">Staff</option>
                                        <option value="manager">Manager</option>
                                    </select>
                                    <InputError :message="teamErrors.role" />
                                </div>
                                <Button type="submit" :disabled="addingMember">
                                    <Plus class="size-4" />
                                    Invite
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2 text-base">
                                <QrCode class="size-4" />
                                Table QR code
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="flex items-end gap-3">
                                <div class="grid gap-2">
                                    <Label for="hub-table-number">
                                        Table number
                                    </Label>
                                    <Input
                                        id="hub-table-number"
                                        v-model="tableNumber"
                                        class="w-24"
                                        maxlength="20"
                                        placeholder="1"
                                    />
                                </div>
                                <Button variant="outline" as-child>
                                    <a :href="tableQrDownloadUrl">
                                        <Download class="size-4" />
                                        Download
                                    </a>
                                </Button>
                            </div>
                            <img
                                v-if="tableQrUrl"
                                :src="tableQrUrl"
                                :alt="`QR code for table ${tableNumber || '1'}`"
                                class="size-36 rounded-lg border bg-white p-2"
                            />
                            <p class="text-xs text-muted-foreground">
                                Points to
                                <span class="font-medium">
                                    {{ props.selected?.public_url }}
                                </span>
                                and carries the table number into the order.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </template>

        <div
            v-else
            class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-6 py-16 text-center text-muted-foreground"
        >
            <Store class="size-8" />
            <p class="font-medium text-foreground">No restaurant yet</p>
            <p class="max-w-sm text-sm">
                Add your first restaurant to build a menu, take orders and print
                table QR codes.
            </p>
            <Button class="mt-2" @click="createOpen = true">
                <Plus class="size-4" />
                Add a restaurant
            </Button>
        </div>
    </div>
</template>
