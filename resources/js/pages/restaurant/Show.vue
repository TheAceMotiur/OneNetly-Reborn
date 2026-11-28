<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
  BellRing,
  CalendarDays,
  MapPin,
  Minus,
  Phone,
  Plus,
  Receipt,
  Search,
  Share2,
  ShoppingBag,
  Sparkles,
  Star,
  Trash2,
  Utensils,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

type PublicRestaurant = {
    username: string;
    name: string;
    description: string | null;
    address: string | null;
    phone: string | null;
    ordering_enabled: boolean;
    accepting_orders: boolean;
    delivery_enabled: boolean;
    delivery_fee_cents: number;
    min_order_cents: number;
    tax_rate: number;
    service_rate: number;
    is_open_now: boolean;
    today_hours: string | null;
    currency: string;
    template: string;
    table: string | null;
    seo_title: string;
    show_branding: boolean;
};

type PublicMenuItem = {
    id: number;
    name: string;
    description: string | null;
    price: string | null;
    original_price: string | null;
    is_on_sale: boolean;
    dietary: string | null;
    allergens: string[];
    image_url: string | null;
    price_cents: number | null;
    is_featured: boolean;
    option_groups: PublicOptionGroup[];
};

type PublicOption = {
    id: number;
    name: string;
    price_cents: number;
};

type PublicOptionGroup = {
    id: number;
    name: string;
    is_required: boolean;
    allow_multiple: boolean;
    options: PublicOption[];
};

type PublicReview = {
    id: number;
    name: string;
    rating: number;
    comment: string | null;
    created_at: string | null;
};

type SelectedOption = {
    id: number;
    name: string;
    group: string;
    price_cents: number;
};

type PublicMenuCategory = {
    id: number;
    name: string;
    description: string | null;
    daypart: string;
    items: PublicMenuItem[];
};

type CartLine = {
    key: string;
    item: PublicMenuItem;
    quantity: number;
    notes: string;
    options: SelectedOption[];
};

type MenuDesign = {
    layout: 'center' | 'left';
    page: string;
    hero: string;
    avatar: string;
    heading: string;
    muted: string;
    card: string;
    chip: string;
    nav: string;
    divider: string;
    accent: string;
    price: string;
    badge: string;
};

const designs: Record<string, MenuDesign> = {
    classic: {
        layout: 'center',
        page: 'bg-[#faf7f2] text-stone-900 dark:bg-stone-950 dark:text-stone-100',
        hero: 'border border-stone-200 bg-white shadow-sm dark:border-stone-800 dark:bg-stone-900',
        avatar: 'rounded-full bg-amber-500 text-white',
        heading: 'font-serif text-4xl font-semibold tracking-tight',
        muted: 'text-stone-600 dark:text-stone-400',
        card: 'border border-stone-200 bg-white shadow-sm dark:border-stone-800 dark:bg-stone-900',
        chip: 'border border-stone-300 bg-white text-stone-700 dark:border-stone-700 dark:bg-stone-900 dark:text-stone-200',
        nav: 'border-stone-200 bg-[#faf7f2]/90 dark:border-stone-800 dark:bg-stone-950/90',
        divider: 'border-stone-200 dark:border-stone-800',
        accent: 'bg-amber-500 text-white hover:bg-amber-600',
        price: 'text-stone-900 dark:text-stone-100',
        badge: 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200',
    },
    modern: {
        layout: 'left',
        page: 'bg-white text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100',
        hero: 'border border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900',
        avatar: 'rounded-2xl bg-emerald-600 text-white',
        heading: 'text-4xl font-semibold tracking-tight',
        muted: 'text-zinc-500 dark:text-zinc-400',
        card: 'border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900',
        chip: 'border border-zinc-300 bg-white text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200',
        nav: 'border-zinc-200 bg-white/90 dark:border-zinc-800 dark:bg-zinc-950/90',
        divider: 'border-zinc-200 dark:border-zinc-800',
        accent: 'bg-emerald-600 text-white hover:bg-emerald-700',
        price: 'text-zinc-900 dark:text-zinc-100',
        badge: 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200',
    },
    dark: {
        layout: 'center',
        page: 'bg-stone-950 text-stone-100',
        hero: 'border border-stone-800 bg-stone-900',
        avatar: 'rounded-full bg-amber-500 text-stone-950',
        heading: 'text-4xl font-semibold tracking-tight',
        muted: 'text-stone-400',
        card: 'border border-stone-800 bg-stone-900',
        chip: 'border border-stone-700 bg-stone-900 text-stone-200',
        nav: 'border-stone-800 bg-stone-950/90',
        divider: 'border-stone-800',
        accent: 'bg-amber-500 text-stone-950 hover:bg-amber-400',
        price: 'text-stone-100',
        badge: 'border-amber-500/30 bg-amber-500/10 text-amber-200',
    },
    elegant: {
        layout: 'center',
        page: 'bg-[#f4f1ea] text-stone-900 dark:bg-[#1c1917] dark:text-stone-100',
        hero: 'border border-stone-300/70 bg-transparent dark:border-stone-700',
        avatar: 'rounded-full bg-stone-900 text-stone-50 dark:bg-stone-100 dark:text-stone-900',
        heading: 'font-serif text-4xl font-medium tracking-tight',
        muted: 'text-stone-600 dark:text-stone-400',
        card: 'border border-stone-300/70 bg-[#faf8f3] dark:border-stone-700 dark:bg-stone-900',
        chip: 'border border-stone-400/60 bg-transparent text-stone-700 dark:border-stone-600 dark:text-stone-300',
        nav: 'border-stone-300/70 bg-[#f4f1ea]/90 dark:border-stone-800 dark:bg-[#1c1917]/90',
        divider: 'border-stone-300/70 dark:border-stone-800',
        accent: 'bg-stone-900 text-stone-50 hover:bg-stone-800 dark:bg-stone-100 dark:text-stone-900 dark:hover:bg-white',
        price: 'text-stone-900 dark:text-stone-100',
        badge: 'border-stone-400/60 bg-transparent text-stone-700 dark:border-stone-600 dark:text-stone-300',
    },
    fresh: {
        layout: 'left',
        page: 'bg-emerald-50 text-emerald-950 dark:bg-emerald-950 dark:text-emerald-50',
        hero: 'border border-emerald-200 bg-white dark:border-emerald-900 dark:bg-emerald-900/40',
        avatar: 'rounded-2xl bg-emerald-600 text-white',
        heading: 'text-4xl font-semibold tracking-tight',
        muted: 'text-emerald-800/80 dark:text-emerald-200/80',
        card: 'border border-emerald-200 bg-white shadow-sm dark:border-emerald-900 dark:bg-emerald-900/30',
        chip: 'border border-emerald-300 bg-white text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900 dark:text-emerald-100',
        nav: 'border-emerald-200 bg-emerald-50/90 dark:border-emerald-900 dark:bg-emerald-950/90',
        divider: 'border-emerald-200 dark:border-emerald-900',
        accent: 'bg-emerald-600 text-white hover:bg-emerald-700',
        price: 'text-emerald-950 dark:text-emerald-50',
        badge: 'border-emerald-300 bg-emerald-100 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-900 dark:text-emerald-100',
    },
    minimal: {
        layout: 'center',
        page: 'bg-white text-black dark:bg-black dark:text-white',
        hero: 'border-b border-black/10 pb-8 dark:border-white/15',
        avatar: 'rounded-none bg-black text-white dark:bg-white dark:text-black',
        heading: 'text-4xl font-light tracking-[0.08em] uppercase',
        muted: 'text-black/60 dark:text-white/60',
        card: 'border-b border-black/10 dark:border-white/15',
        chip: 'border border-black/20 bg-transparent text-black/70 dark:border-white/25 dark:text-white/70',
        nav: 'border-black/10 bg-white/90 dark:border-white/15 dark:bg-black/90',
        divider: 'border-black/10 dark:border-white/15',
        accent: 'bg-black text-white hover:bg-black/80 dark:bg-white dark:text-black dark:hover:bg-white/80',
        price: 'text-black dark:text-white',
        badge: 'border-black/20 bg-transparent text-black/70 dark:border-white/25 dark:text-white/70',
    },
};

const props = defineProps<{
    restaurant: PublicRestaurant;
    menu: PublicMenuCategory[];
    dayparts: Record<string, string>;
    allergens: Record<string, string>;
    reviews: PublicReview[];
    review_stats: { count: number; average: number };
}>();

const page = usePage();

const reviewForm = reactive({ name: '', rating: 5, comment: '' });
const reviewErrors = ref<Record<string, string>>({});
const submittingReview = ref(false);
const newsletterEmail = ref('');
const subscribing = ref(false);

function subscribe(): void {
    subscribing.value = true;

    router.post(
        `/subscribe/${encodeURIComponent(props.restaurant.username)}`,
        { email: newsletterEmail.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                newsletterEmail.value = '';
            },
            onFinish: () => {
                subscribing.value = false;
            },
        },
    );
}

function submitReview(): void {
    submittingReview.value = true;
    reviewErrors.value = {};

    router.post(
        `/restaurant/${encodeURIComponent(props.restaurant.username)}/review`,
        reviewForm,
        {
            preserveScroll: true,
            onError: (errors) => {
                reviewErrors.value = errors as Record<string, string>;
            },
            onSuccess: () => {
                reviewForm.name = '';
                reviewForm.rating = 5;
                reviewForm.comment = '';
            },
            onFinish: () => {
                submittingReview.value = false;
            },
        },
    );
}

const design = computed<MenuDesign>(
    () => designs[props.restaurant.template] ?? designs.classic,
);

const canOrder = computed(
    () =>
        props.restaurant.ordering_enabled &&
        props.restaurant.accepting_orders &&
        props.restaurant.is_open_now,
);

const placedOrder = computed(() => {
    const flash = page.flash as
        | {
              order?: {
                  reference: string;
                  total: string;
                  tracking_url: string;
              };
          }
        | undefined;

    return flash?.order ?? null;
});

const initials = computed(() =>
    props.restaurant.name
        .split(' ')
        .map((part) => part.charAt(0))
        .join('')
        .slice(0, 2)
        .toUpperCase(),
);

const search = ref('');
const normalizedSearch = computed(() => search.value.trim().toLowerCase());

function matchesSearch(item: PublicMenuItem): boolean {
    const query = normalizedSearch.value;

    if (query === '') {
        return true;
    }

    return [item.name, item.description, item.dietary]
        .filter((value): value is string => Boolean(value))
        .some((value) => value.toLowerCase().includes(query));
}

const excludedAllergens = ref<string[]>([]);

const availableAllergens = computed<Record<string, string>>(() => {
    const present = new Set(
        props.menu
            .flatMap((category) => category.items)
            .flatMap((item) => item.allergens),
    );

    return Object.fromEntries(
        Object.entries(props.allergens).filter(([key]) => present.has(key)),
    );
});

const hasAllergenOptions = computed(
    () => Object.keys(availableAllergens.value).length > 0,
);

function allergenLabel(key: string): string {
    return props.allergens[key] ?? key;
}

function toggleAllergenFilter(allergen: string): void {
    excludedAllergens.value = excludedAllergens.value.includes(allergen)
        ? excludedAllergens.value.filter((value) => value !== allergen)
        : [...excludedAllergens.value, allergen];
}

function matchesFilters(item: PublicMenuItem): boolean {
    return (
        matchesSearch(item) &&
        !item.allergens.some((allergen) =>
            excludedAllergens.value.includes(allergen),
        )
    );
}

function clearFilters(): void {
    search.value = '';
    excludedAllergens.value = [];
}

const activeDaypart = ref('all');

const availableDayparts = computed(() => {
    const used = new Set(props.menu.map((category) => category.daypart));

    return Object.entries(props.dayparts).filter(([value]) => used.has(value));
});

const filteredMenu = computed(() =>
    props.menu
        .filter(
            (category) =>
                activeDaypart.value === 'all' ||
                category.daypart === activeDaypart.value,
        )
        .map((category) => ({
            ...category,
            items: category.items.filter(matchesFilters),
        }))
        .filter((category) => category.items.length > 0),
);

const featuredItems = computed(() =>
    props.menu
        .flatMap((category) => category.items)
        .filter((item) => item.is_featured && matchesFilters(item)),
);

const cartStorageKey = computed(
    () => `restaurant-cart:${props.restaurant.username}`,
);

function loadCart(): Record<string, CartLine> {
    if (typeof window === 'undefined') {
        return {};
    }

    try {
        const raw = window.localStorage.getItem(cartStorageKey.value);

        if (!raw) {
            return {};
        }

        const parsed: unknown = JSON.parse(raw);

        if (typeof parsed !== 'object' || parsed === null) {
            return {};
        }

        const lines: Record<string, CartLine> = {};

        for (const [key, stored] of Object.entries(
            parsed as Record<string, Partial<CartLine>>,
        )) {
            if (!stored.item) {
                continue;
            }

            lines[key] = {
                key,
                item: stored.item,
                quantity: stored.quantity ?? 1,
                notes: stored.notes ?? '',
                options: stored.options ?? [],
            };
        }

        return lines;
    } catch {
        return {};
    }
}

const cart = ref<Record<string, CartLine>>(loadCart());
const cartOpen = ref(false);
const placingOrder = ref(false);
const orderErrors = ref<Record<string, string>>({});

watch(
    cart,
    (value) => {
        if (typeof window === 'undefined') {
            return;
        }

        try {
            if (Object.keys(value).length === 0) {
                window.localStorage.removeItem(cartStorageKey.value);
            } else {
                window.localStorage.setItem(
                    cartStorageKey.value,
                    JSON.stringify(value),
                );
            }
        } catch {
            // Storage may be unavailable in private browsing.
        }
    },
    { deep: true },
);

const orderForm = reactive({
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    order_type: 'dine_in',
    table_number: props.restaurant.table ?? '',
    delivery_address: '',
    delivery_postcode: '',
    scheduled_for: '',
    coupon_code: '',
    redeem_points: false,
    notes: '',
    website: '',
});

const cartLines = computed(() =>
    Object.values(cart.value).filter((line) => line.quantity > 0),
);

const cartCount = computed(() =>
    cartLines.value.reduce((total, line) => total + line.quantity, 0),
);

function lineUnitPrice(line: CartLine): number {
    return (
        (line.item.price_cents ?? 0) +
        line.options.reduce((total, option) => total + option.price_cents, 0)
    );
}

const cartSubtotalCents = computed(() =>
    cartLines.value.reduce(
        (total, line) => total + lineUnitPrice(line) * line.quantity,
        0,
    ),
);

const tipPercent = ref(0);

const serviceCents = computed(() =>
    Math.round((cartSubtotalCents.value * props.restaurant.service_rate) / 100),
);

const deliveryCents = computed(() =>
    orderForm.order_type === 'delivery' && props.restaurant.delivery_enabled
        ? props.restaurant.delivery_fee_cents
        : 0,
);

const taxCents = computed(() =>
    Math.round(
        ((cartSubtotalCents.value + serviceCents.value + deliveryCents.value) *
            props.restaurant.tax_rate) /
            100,
    ),
);

const tipCents = computed(() =>
    Math.round((cartSubtotalCents.value * tipPercent.value) / 100),
);

const orderTotalCents = computed(
    () =>
        cartSubtotalCents.value +
        serviceCents.value +
        deliveryCents.value +
        taxCents.value +
        tipCents.value,
);

const belowMinimum = computed(
    () =>
        props.restaurant.min_order_cents > 0 &&
        cartSubtotalCents.value < props.restaurant.min_order_cents,
);

function formatMoney(cents: number): string {
    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency: props.restaurant.currency,
        }).format(cents / 100);
    } catch {
        return `${(cents / 100).toFixed(2)} ${props.restaurant.currency}`;
    }
}

function addToOrder(item: PublicMenuItem): void {
    if (!canOrder.value || item.price_cents === null) {
        return;
    }

    const key = String(item.id);
    const existing = cart.value[key];

    cart.value[key] = existing
        ? { ...existing, quantity: existing.quantity + 1 }
        : { key, item, quantity: 1, notes: '', options: [] };

    toast.success(`${item.name} added to your order`);
}

function changeQuantity(line: CartLine, change: number): void {
    line.quantity += change;

    if (line.quantity <= 0) {
        delete cart.value[line.key];
    }
}

function hasOptions(item: PublicMenuItem): boolean {
    return (item.option_groups ?? []).length > 0;
}

function optionGroups(item: PublicMenuItem): PublicOptionGroup[] {
    return item.option_groups ?? [];
}

const optionsItem = ref<PublicMenuItem | null>(null);
const optionsOpen = computed({
    get: () => optionsItem.value !== null,
    set: (open: boolean) => {
        if (!open) {
            optionsItem.value = null;
        }
    },
});
const optionSelections = ref<Record<number, number[]>>({});
const optionQuantity = ref(1);

function selectedOptionIds(item: PublicMenuItem): number[] {
    return optionGroups(item).flatMap(
        (group) => optionSelections.value[group.id] ?? [],
    );
}

function optionsTotalCents(item: PublicMenuItem): number {
    const selected = new Set(selectedOptionIds(item));

    return optionGroups(item)
        .flatMap((group) => group.options)
        .filter((option) => selected.has(option.id))
        .reduce((total, option) => total + option.price_cents, 0);
}

function optionGroupSelected(group: PublicOptionGroup): number[] {
    return optionSelections.value[group.id] ?? [];
}

function chooseOption(group: PublicOptionGroup, option: PublicOption): void {
    const current = optionGroupSelected(group);

    if (group.allow_multiple) {
        optionSelections.value[group.id] = current.includes(option.id)
            ? current.filter((id) => id !== option.id)
            : [...current, option.id];

        return;
    }

    optionSelections.value[group.id] = current.includes(option.id)
        ? []
        : [option.id];
}

function optionsValid(item: PublicMenuItem): boolean {
    return optionGroups(item).every(
        (group) =>
            !group.is_required || optionGroupSelected(group).length > 0,
    );
}

const configuredTotalCents = computed(() => {
    const item = optionsItem.value;

    if (!item) {
        return 0;
    }

    return ((item.price_cents ?? 0) + optionsTotalCents(item)) * optionQuantity.value;
});

function openItem(item: PublicMenuItem): void {
    if (!canOrder.value || item.price_cents === null) {
        return;
    }

    if (!hasOptions(item)) {
        addToOrder(item);

        return;
    }

    optionsItem.value = item;
    optionSelections.value = {};
    optionQuantity.value = 1;
}

function addConfiguredItem(): void {
    const item = optionsItem.value;

    if (!item || !optionsValid(item)) {
        return;
    }

    const selected: SelectedOption[] = [];

    optionGroups(item).forEach((group) => {
        optionGroupSelected(group).forEach((id) => {
            const option = group.options.find((choice) => choice.id === id);

            if (option) {
                selected.push({
                    id: option.id,
                    name: option.name,
                    group: group.name,
                    price_cents: option.price_cents,
                });
            }
        });
    });

    const key = `${item.id}:${selected
        .map((option) => option.id)
        .sort((left, right) => left - right)
        .join('-')}`;
    const existing = cart.value[key];

    cart.value[key] = existing
        ? { ...existing, quantity: existing.quantity + optionQuantity.value }
        : {
              key,
              item,
              quantity: optionQuantity.value,
              notes: '',
              options: selected,
          };

    toast.success(`${item.name} added to your order`);
    optionsItem.value = null;
}

async function shareMenu(): Promise<void> {
    if (typeof window === 'undefined') {
        return;
    }

    const url = window.location.href;

    try {
        if (typeof navigator.share === 'function') {
            await navigator.share({ title: props.restaurant.name, url });

            return;
        }

        await navigator.clipboard.writeText(url);
        toast.success('Menu link copied');
    } catch {
        // Sharing was dismissed or blocked.
    }
}

const serviceActions = [
    { key: 'waiter', label: 'Call waiter', icon: BellRing },
    { key: 'bill', label: 'Request bill', icon: Receipt },
    { key: 'cleaning', label: 'Clean table', icon: Sparkles },
];

const requestingService = ref<string | null>(null);
const sentServices = ref<string[]>([]);

const reservationOpen = ref(false);
const reserving = ref(false);
const reservationErrors = ref<Record<string, string>>({});
const reservationForm = reactive({
    name: '',
    phone: '',
    email: '',
    party_size: 2,
    reservation_date: new Date().toISOString().slice(0, 10),
    reservation_time: '19:00',
    notes: '',
    join_waitlist: false,
    website: '',
});

function openReservation(): void {
    reservationErrors.value = {};
    reservationOpen.value = true;
}

function submitReservation(): void {
    router.post(
        `/restaurant/${encodeURIComponent(props.restaurant.username)}/reservation`,
        { ...reservationForm },
        {
            preserveScroll: true,
            onStart: () => {
                reserving.value = true;
            },
            onFinish: () => {
                reserving.value = false;
            },
            onError: (errors) => {
                reservationErrors.value = errors;
            },
            onSuccess: () => {
                reservationOpen.value = false;
                reservationErrors.value = {};
                reservationForm.notes = '';
            },
        },
    );
}

function requestService(type: string): void {
    if (props.restaurant.table === null || requestingService.value !== null) {
        return;
    }

    router.post(
        `/restaurant/${encodeURIComponent(props.restaurant.username)}/service-request`,
        {
            type,
            table_number: props.restaurant.table,
        },
        {
            preserveScroll: true,
            onStart: () => {
                requestingService.value = type;
            },
            onFinish: () => {
                requestingService.value = null;
            },
            onSuccess: () => {
                sentServices.value = [...sentServices.value, type];

                setTimeout(() => {
                    sentServices.value = sentServices.value.filter(
                        (sent) => sent !== type,
                    );
                }, 60000);
            },
        },
    );
}

function submitOrder(): void {
    if (cartLines.value.length === 0) {
        return;
    }

    router.post(
        `/restaurant/${encodeURIComponent(props.restaurant.username)}/order`,
        {
            ...orderForm,
            table_number:
                orderForm.order_type === 'dine_in'
                    ? orderForm.table_number
                    : null,
            delivery_address:
                orderForm.order_type === 'delivery'
                    ? orderForm.delivery_address
                    : null,
            delivery_postcode:
                orderForm.order_type === 'delivery'
                    ? orderForm.delivery_postcode
                    : null,
            scheduled_for: orderForm.scheduled_for || null,
            tip_cents: tipCents.value,
            items: cartLines.value.map((line) => ({
                id: line.item.id,
                quantity: line.quantity,
                notes: line.notes || null,
                options: line.options.map((option) => option.id),
            })),
        },
        {
            preserveScroll: true,
            onStart: () => {
                placingOrder.value = true;
            },
            onFinish: () => {
                placingOrder.value = false;
            },
            onError: (errors) => {
                orderErrors.value = errors;
            },
            onSuccess: () => {
                cart.value = {};
                cartOpen.value = false;
                orderErrors.value = {};
                tipPercent.value = 0;
                orderForm.delivery_address = '';
                orderForm.delivery_postcode = '';
                orderForm.scheduled_for = '';
                orderForm.coupon_code = '';
                orderForm.redeem_points = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="props.restaurant.seo_title" />

    <div :class="[design.page]" class="min-h-screen">
        <div
            :class="[
                canOrder && cartCount > 0 ? 'pb-28' : '',
            ]"
            class="mx-auto flex min-h-screen w-full max-w-3xl flex-col px-5 py-10"
        >
            <header
                :class="[
                    design.hero,
                    design.layout === 'center'
                        ? 'flex flex-col items-center gap-4 rounded-3xl p-8 text-center'
                        : 'flex flex-col gap-4 rounded-3xl p-8 sm:flex-row sm:items-center sm:gap-6',
                ]"
            >
                <div
                    :class="[
                        design.avatar,
                        design.layout === 'center' ? 'size-20' : 'size-20 shrink-0',
                    ]"
                    class="flex items-center justify-center text-2xl font-semibold shadow-lg"
                >
                    {{ initials }}
                </div>

                <div
                    :class="
                        design.layout === 'center'
                            ? 'flex flex-col items-center gap-2'
                            : 'flex flex-1 flex-col gap-2'
                    "
                >
                    <div class="space-y-1">
                        <h1 :class="[design.heading]">
                            {{ props.restaurant.name }}
                        </h1>
                        <p :class="[design.muted]" class="text-xs">
                            @{{ props.restaurant.username }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center justify-center gap-1.5">
                        <p
                            v-if="props.restaurant.table && canOrder"
                            :class="[design.badge]"
                            class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-medium"
                        >
                            Table {{ props.restaurant.table }}
                        </p>
                        <p
                            v-if="
                                props.restaurant.ordering_enabled &&
                                props.restaurant.today_hours
                            "
                            :class="[design.badge]"
                            class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-medium"
                        >
                            <span v-if="canOrder">
                                Open now · {{ props.restaurant.today_hours }}
                            </span>
                            <span v-else>
                                Closed · {{ props.restaurant.today_hours }}
                            </span>
                        </p>
                    </div>

                    <p
                        v-if="props.restaurant.description"
                        :class="[design.muted]"
                        class="max-w-lg text-sm leading-relaxed"
                    >
                        {{ props.restaurant.description }}
                    </p>

                    <div
                        v-if="props.restaurant.address || props.restaurant.phone"
                        :class="[
                            design.muted,
                            design.layout === 'center'
                                ? 'flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs'
                                : 'flex flex-wrap items-center gap-x-4 gap-y-1 text-xs',
                        ]"
                    >
                        <span
                            v-if="props.restaurant.address"
                            class="inline-flex items-center gap-1.5"
                        >
                            <MapPin class="size-3.5" />
                            {{ props.restaurant.address }}
                        </span>
                        <a
                            v-if="props.restaurant.phone"
                            :href="`tel:${props.restaurant.phone}`"
                            class="inline-flex items-center gap-1.5 hover:underline"
                        >
                            <Phone class="size-3.5" />
                            {{ props.restaurant.phone }}
                        </a>
                    </div>
                </div>

                <div class="flex shrink-0 flex-col gap-2 self-start">
                    <button
                        type="button"
                        :class="[design.chip]"
                        class="inline-flex items-center justify-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium transition hover:opacity-80"
                        @click="openReservation"
                    >
                        <CalendarDays class="size-3.5" />
                        Reserve a table
                    </button>
                    <button
                        type="button"
                        :class="[design.chip]"
                        class="inline-flex items-center justify-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium transition hover:opacity-80"
                        @click="shareMenu"
                    >
                        <Share2 class="size-3.5" />
                        Share menu
                    </button>
                </div>
            </header>

            <main class="mt-8 flex-1">
                <div
                    v-if="placedOrder"
                    class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm"
                >
                    <p class="font-semibold">
                        Order {{ placedOrder.reference }} received
                    </p>
                                                        <p class="mt-0.5 text-xs opacity-80">
                            Total {{ placedOrder.total }}. The restaurant has
                            your order.
                        </p>
                        <a
                            :href="placedOrder.tracking_url"
                            class="mt-2 inline-flex items-center gap-1 text-xs font-semibold underline underline-offset-2"
                        >
                            Track this order
                        </a>
                    </div>

                <div
                    v-if="
                        props.restaurant.ordering_enabled &&
                        !props.restaurant.accepting_orders
                    "
                    class="mb-5 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm"
                >
                    <p class="font-semibold">Ordering is paused</p>
                    <p class="mt-0.5 text-xs opacity-80">
                        The menu is still available. Please order with a team
                        member.
                    </p>
                </div>

                <div
                    v-else-if="
                        props.restaurant.ordering_enabled &&
                        !props.restaurant.is_open_now
                    "
                    class="mb-5 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm"
                >
                    <p class="font-semibold">Ordering is closed</p>
                    <p class="mt-0.5 text-xs opacity-80">
                        <template v-if="props.restaurant.today_hours">
                            Today's hours are
                            {{ props.restaurant.today_hours }}. You can still
                            browse the menu.
                        </template>
                        <template v-else>
                            You can still browse the menu. Please order with a
                            team member.
                        </template>
                    </p>
                </div>

                <div v-if="props.menu.length" class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 opacity-50"
                    />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search the menu"
                        :class="[design.card]"
                        class="h-11 w-full rounded-full pr-4 pl-9 text-sm outline-none focus:ring-2 focus:ring-current/20"
                    />
                </div>

                <div
                    v-if="hasAllergenOptions"
                    class="mt-3 flex flex-wrap items-center gap-1.5"
                >
                    <span
                        class="text-[11px] font-medium tracking-wide uppercase opacity-60"
                    >
                        Filter out
                    </span>
                    <button
                        v-for="(label, key) in availableAllergens"
                        :key="key"
                        type="button"
                        class="rounded-full border px-2.5 py-1 text-[11px] font-medium transition"
                        :class="
                            excludedAllergens.includes(key)
                                ? 'border-red-400 bg-red-500/10 text-red-700 line-through dark:text-red-300'
                                : design.chip
                        "
                        @click="toggleAllergenFilter(key)"
                    >
                        {{ label }}
                    </button>
                </div>

                <section
                    v-if="featuredItems.length && normalizedSearch === ''"
                    class="mt-6"
                >
                    <div class="flex items-center gap-2">
                        <Sparkles class="size-4" />
                        <h2 class="text-sm font-semibold tracking-wide uppercase">
                            Chef's picks
                        </h2>
                    </div>
                    <div class="mt-3 flex gap-3 overflow-x-auto pb-2">
                        <article
                            v-for="item in featuredItems"
                            :key="`featured-${item.id}`"
                            :class="[design.card]"
                            class="flex w-60 shrink-0 flex-col overflow-hidden rounded-2xl"
                        >
                            <img
                                v-if="item.image_url"
                                :src="item.image_url"
                                :alt="item.name"
                                class="h-32 w-full object-cover"
                            />
                            <div class="flex flex-1 flex-col p-4">
                                <p class="text-sm font-semibold">{{ item.name }}</p>
                                <p
                                    v-if="item.description"
                                    :class="[design.muted]"
                                    class="mt-1 line-clamp-2 text-xs leading-relaxed"
                                >
                                    {{ item.description }}
                                </p>
                                <div class="mt-auto flex items-center justify-between gap-2 pt-3">
                                    <span class="flex items-baseline gap-1.5">
                                        <span
                                            v-if="item.original_price"
                                            class="text-xs text-muted-foreground line-through tabular-nums"
                                        >
                                            {{ item.original_price }}
                                        </span>
                                        <span
                                            v-if="item.price"
                                            :class="[design.price]"
                                            class="text-sm font-semibold tabular-nums"
                                        >
                                            {{ item.price }}
                                        </span>
                                    </span>
                                    <button
                                        v-if="canOrder && item.price_cents !== null"
                                        type="button"
                                        :class="[design.accent]"
                                        class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                                        @click="addToOrder(item)"
                                    >
                                        Add
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <div
                    v-if="availableDayparts.length > 1"
                    class="mt-6 flex flex-wrap gap-2"
                >
                    <button
                        type="button"
                        :class="
                            activeDaypart === 'all'
                                ? design.accent
                                : design.chip
                        "
                        class="rounded-full px-3 py-1.5 text-xs font-semibold"
                        @click="activeDaypart = 'all'"
                    >
                        Full menu
                    </button>
                    <button
                        v-for="[value, label] in availableDayparts"
                        :key="value"
                        type="button"
                        :class="
                            activeDaypart === value
                                ? design.accent
                                : design.chip
                        "
                        class="rounded-full px-3 py-1.5 text-xs font-semibold"
                        @click="activeDaypart = value"
                    >
                        {{ label }}
                    </button>
                </div>

                <nav
                    v-if="filteredMenu.length > 1"
                    :class="[design.nav]"
                    class="sticky top-0 z-30 -mx-5 mt-6 flex gap-2 overflow-x-auto border-b px-5 py-3 backdrop-blur"
                >
                    <a
                        v-for="category in filteredMenu"
                        :key="`nav-${category.id}`"
                        :href="`#menu-${category.id}`"
                        :class="[design.chip]"
                        class="shrink-0 rounded-full px-3 py-1.5 text-xs font-medium"
                    >
                        {{ category.name }}
                    </a>
                </nav>

                <div v-if="filteredMenu.length" class="mt-6 space-y-10">
                    <div
                        v-for="category in filteredMenu"
                        :id="`menu-${category.id}`"
                        :key="category.id"
                        class="scroll-mt-24"
                    >
                        <div :class="[design.divider]" class="border-b pb-3">
                            <h2 class="text-sm font-semibold tracking-wide uppercase">
                                {{ category.name }}
                            </h2>
                            <p
                                v-if="category.description"
                                :class="[design.muted]"
                                class="mt-1 text-xs"
                            >
                                {{ category.description }}
                            </p>
                        </div>

                        <div
                            :class="[
                                props.restaurant.template === 'minimal'
                                    ? ['divide-y', design.divider]
                                    : 'mt-3 grid gap-3 sm:grid-cols-2',
                            ]"
                        >
                            <article
                                v-for="item in category.items"
                                :key="item.id"
                                :class="[
                                    design.card,
                                    props.restaurant.template === 'minimal'
                                        ? 'flex items-start justify-between gap-3 py-4'
                                        : 'flex flex-col overflow-hidden rounded-2xl text-left',
                                ]"
                            >
                                <img
                                    v-if="
                                        item.image_url &&
                                        props.restaurant.template !== 'minimal'
                                    "
                                    :src="item.image_url"
                                    :alt="item.name"
                                    class="h-40 w-full object-cover"
                                />
                                <div
                                    :class="
                                        props.restaurant.template === 'minimal'
                                            ? 'flex-1'
                                            : 'flex flex-1 flex-col p-4'
                                    "
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="text-sm font-semibold">
                                                    {{ item.name }}
                                                </p>
                                                <span
                                                    v-if="item.is_featured"
                                                    :class="[design.badge]"
                                                    class="rounded-full border px-2 py-0.5 text-[10px] tracking-wide uppercase"
                                                >
                                                    Chef's pick
                                                </span>
                                                <span
                                                    v-if="item.dietary"
                                                    class="rounded-full border px-2 py-0.5 text-[10px] tracking-wide uppercase opacity-70"
                                                >
                                                    {{ item.dietary }}
                                                </span>
                                                <span
                                                    v-if="item.allergens.length"
                                                    class="text-[10px] tracking-wide uppercase opacity-60"
                                                >
                                                    Contains
                                                    {{
                                                        item.allergens
                                                            .map(allergenLabel)
                                                            .join(', ')
                                                    }}
                                                </span>
                                            </div>
                                            <p
                                                v-if="item.description"
                                                :class="[design.muted]"
                                                class="mt-1 text-xs leading-relaxed"
                                            >
                                                {{ item.description }}
                                            </p>
                                        </div>
                                        <span
                                            v-if="item.price"
                                            :class="[design.price]"
                                            class="shrink-0 text-sm font-semibold tabular-nums"
                                        >
                                            {{ item.price }}
                                        </span>
                                    </div>

                                    <div
                                        class="mt-auto flex items-center justify-end gap-2 pt-3"
                                    >
                                        <div
                                            v-if="
                                                !hasOptions(item) &&
                                                cart[String(item.id)]
                                            "
                                            class="flex items-center gap-1 rounded-full border p-0.5"
                                        >
                                            <button
                                                type="button"
                                                class="flex size-7 items-center justify-center rounded-full transition hover:bg-black/5 dark:hover:bg-white/10"
                                                :aria-label="`Remove one ${item.name}`"
                                                @click="
                                                    changeQuantity(
                                                        cart[String(item.id)],
                                                        -1,
                                                    )
                                                "
                                            >
                                                <Minus class="size-3.5" />
                                            </button>
                                            <span
                                                class="min-w-5 text-center text-xs font-semibold tabular-nums"
                                            >
                                                {{
                                                    cart[String(item.id)]
                                                        .quantity
                                                }}
                                            </span>
                                            <button
                                                type="button"
                                                class="flex size-7 items-center justify-center rounded-full transition hover:bg-black/5 dark:hover:bg-white/10"
                                                :aria-label="`Add one ${item.name}`"
                                                @click="addToOrder(item)"
                                            >
                                                <Plus class="size-3.5" />
                                            </button>
                                        </div>
                                        <button
                                            v-else-if="canOrder && item.price_cents !== null"
                                            type="button"
                                            :class="[design.chip]"
                                            class="rounded-full px-3 py-1.5 text-xs font-semibold transition hover:-translate-y-0.5"
                                            @click="openItem(item)"
                                        >
                                            {{ hasOptions(item) ? 'Choose' : 'Add' }}
                                        </button>
                                        <span
                                            v-else-if="canOrder && item.price_cents === null"
                                            class="text-[10px] tracking-wide uppercase opacity-60"
                                        >
                                            Ask for price
                                        </span>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>

                <div
                    v-else-if="props.menu.length"
                    class="mt-10 flex flex-col items-center gap-2 rounded-2xl border border-dashed px-6 py-14 text-center"
                >
                    <Search class="size-8 opacity-50" />
                    <p class="font-medium">
                        {{
                            search
                                ? `No dishes match "${search}"`
                                : 'No dishes match your filters'
                        }}
                    </p>
                    <button
                        type="button"
                        class="text-sm underline underline-offset-4"
                        @click="clearFilters"
                    >
                        Clear filters
                    </button>
                </div>

                <section
                    v-if="props.restaurant.table"
                    :class="[design.card]"
                    class="mt-10 rounded-2xl p-5"
                >
                    <div class="flex items-center gap-2">
                        <BellRing class="size-4" />
                        <h2 class="text-sm font-semibold">Need something?</h2>
                    </div>
                    <p :class="[design.muted]" class="mt-1 text-xs">
                        Let the team know you're at table
                        {{ props.restaurant.table }}.
                    </p>
                    <div class="mt-4 grid gap-2 sm:grid-cols-3">
                        <button
                            v-for="action in serviceActions"
                            :key="action.key"
                            type="button"
                            :class="[
                                sentServices.includes(action.key)
                                    ? design.chip
                                    : design.accent,
                            ]"
                            class="flex items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="
                                requestingService !== null ||
                                sentServices.includes(action.key)
                            "
                            @click="requestService(action.key)"
                        >
                            <component :is="action.icon" class="size-4" />
                            {{
                                sentServices.includes(action.key)
                                    ? 'Sent'
                                    : action.label
                            }}
                        </button>
                    </div>
                </section>

                <div
                    v-else
                    class="mt-6 flex flex-col items-center gap-2 rounded-2xl border border-dashed px-6 py-16 text-center opacity-80"
                >
                    <Utensils class="size-8" />
                    <p class="font-medium">The menu is being prepared</p>
                    <p class="max-w-sm text-sm">
                        Check back soon for what's on at
                        {{ props.restaurant.name }}.
                    </p>
                </div>
                <section class="mt-16 border-t pt-8">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold">Guest reviews</h2>
                            <div class="mt-1 flex items-center gap-1.5 text-sm opacity-70">
                                <Star class="size-4 fill-amber-400 text-amber-400" />
                                <span>{{ props.review_stats.average || '—' }}</span>
                                <span>· {{ props.review_stats.count }} published</span>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="props.reviews.length"
                        class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <article
                            v-for="review in props.reviews"
                            :key="review.id"
                            class="rounded-xl border p-4"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold">{{ review.name }}</p>
                                <div class="flex gap-0.5">
                                    <Star
                                        v-for="star in 5"
                                        :key="star"
                                        class="size-3"
                                        :class="
                                            star <= review.rating
                                                ? 'fill-amber-400 text-amber-400'
                                                : 'opacity-20'
                                        "
                                    />
                                </div>
                            </div>
                            <p v-if="review.comment" class="mt-2 text-sm leading-relaxed opacity-80">
                                {{ review.comment }}
                            </p>
                            <p class="mt-2 text-[11px] opacity-50">{{ review.created_at }}</p>
                        </article>
                    </div>

                    <div class="mt-6 max-w-xl rounded-xl border p-4">
                        <p class="text-sm font-semibold">Leave a review</p>
                        <div class="mt-3 grid gap-3">
                            <input
                                v-model="reviewForm.name"
                                maxlength="80"
                                placeholder="Your name"
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring"
                            />
                            <p v-if="reviewErrors.name" class="text-xs text-red-500">
                                {{ reviewErrors.name }}
                            </p>
                            <div class="flex items-center gap-1">
                                <button
                                    v-for="star in 5"
                                    :key="star"
                                    type="button"
                                    :aria-label="`${star} stars`"
                                    @click="reviewForm.rating = star"
                                >
                                    <Star
                                        class="size-6"
                                        :class="
                                            star <= reviewForm.rating
                                                ? 'fill-amber-400 text-amber-400'
                                                : 'opacity-25'
                                        "
                                    />
                                </button>
                            </div>
                            <p v-if="reviewErrors.rating" class="text-xs text-red-500">
                                {{ reviewErrors.rating }}
                            </p>
                            <textarea
                                v-model="reviewForm.comment"
                                rows="3"
                                maxlength="1000"
                                placeholder="How was your visit? (optional)"
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring"
                            />
                            <p v-if="reviewErrors.comment" class="text-xs text-red-500">
                                {{ reviewErrors.comment }}
                            </p>
                            <button
                                type="button"
                                :disabled="submittingReview"
                                class="w-fit rounded-full bg-foreground px-4 py-2 text-sm font-semibold text-background disabled:opacity-50"
                                @click="submitReview"
                            >
                                {{ submittingReview ? 'Sending…' : 'Submit review' }}
                            </button>
                        </div>
                    </div>

                    <form
                        class="mt-8 flex max-w-xl flex-col gap-2 rounded-xl border p-4 sm:flex-row"
                        @submit.prevent="subscribe"
                    >
                        <div class="flex-1">
                            <p class="text-sm font-semibold">Get news and offers</p>
                            <p class="text-xs opacity-60">Join the email list for this restaurant.</p>
                        </div>
                        <input
                            v-model="newsletterEmail"
                            type="email"
                            required
                            maxlength="120"
                            placeholder="you@example.com"
                            class="min-w-0 rounded-lg border bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring"
                        />
                        <button
                            type="submit"
                            :disabled="subscribing"
                            class="rounded-lg bg-foreground px-4 py-2 text-sm font-semibold text-background disabled:opacity-50"
                        >
                            Subscribe
                        </button>
                    </form>
                </section>
            </main>

            <footer
                v-if="props.restaurant.show_branding"
                class="mt-auto pt-12 text-center text-xs opacity-60"
            >
                Menu powered by {{ String(page.props.name ?? '') }}
            </footer>
        </div>

        <button
            v-if="canOrder && cartCount > 0"
            type="button"
            :class="[design.nav]"
            class="fixed inset-x-0 bottom-0 z-40 border-t px-5 py-3 shadow-[0_-12px_30px_-18px_rgba(0,0,0,0.45)] backdrop-blur"
            @click="cartOpen = true"
        >
            <div class="mx-auto flex w-full max-w-3xl items-center justify-between gap-3">
                <span class="flex items-center gap-2 text-sm font-semibold">
                    <span
                        :class="[design.avatar]"
                        class="flex size-7 items-center justify-center rounded-full text-xs"
                    >
                        {{ cartCount }}
                    </span>
                    <ShoppingBag class="size-4" />
                    View order
                </span>
                <span class="text-sm font-semibold tabular-nums">
                    {{ formatMoney(cartSubtotalCents) }}
                </span>
            </div>
        </button>

        <Dialog v-model:open="reservationOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Reserve a table</DialogTitle>
                    <DialogDescription>
                        Request a table at {{ props.restaurant.name }}. The
                        restaurant will confirm your booking.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-3" @submit.prevent="submitReservation">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <label for="res-name" class="text-xs font-medium"
                                >Name</label
                            >
                            <input
                                id="res-name"
                                v-model="reservationForm.name"
                                type="text"
                                maxlength="80"
                                autocomplete="name"
                                required
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring"
                            />
                            <p
                                v-if="reservationErrors.name"
                                class="text-xs text-red-500"
                            >
                                {{ reservationErrors.name }}
                            </p>
                        </div>
                        <div class="grid gap-1.5">
                            <label for="res-phone" class="text-xs font-medium"
                                >Phone</label
                            >
                            <input
                                id="res-phone"
                                v-model="reservationForm.phone"
                                type="tel"
                                maxlength="40"
                                autocomplete="tel"
                                required
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring"
                            />
                            <p
                                v-if="reservationErrors.phone"
                                class="text-xs text-red-500"
                            >
                                {{ reservationErrors.phone }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <label for="res-email" class="text-xs font-medium">
                            Email (optional)
                        </label>
                        <input
                            id="res-email"
                            v-model="reservationForm.email"
                            type="email"
                            maxlength="120"
                            autocomplete="email"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring"
                        />
                        <p
                            v-if="reservationErrors.email"
                            class="text-xs text-red-500"
                        >
                            {{ reservationErrors.email }}
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="grid gap-1.5">
                            <label for="res-party" class="text-xs font-medium"
                                >Guests</label
                            >
                            <input
                                id="res-party"
                                v-model.number="reservationForm.party_size"
                                type="number"
                                min="1"
                                max="30"
                                required
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring"
                            />
                            <p
                                v-if="reservationErrors.party_size"
                                class="text-xs text-red-500"
                            >
                                {{ reservationErrors.party_size }}
                            </p>
                        </div>
                        <div class="grid gap-1.5">
                            <label for="res-date" class="text-xs font-medium"
                                >Date</label
                            >
                            <input
                                id="res-date"
                                v-model="reservationForm.reservation_date"
                                type="date"
                                required
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring"
                            />
                            <p
                                v-if="reservationErrors.reservation_date"
                                class="text-xs text-red-500"
                            >
                                {{ reservationErrors.reservation_date }}
                            </p>
                        </div>
                        <div class="grid gap-1.5">
                            <label for="res-time" class="text-xs font-medium"
                                >Time</label
                            >
                            <input
                                id="res-time"
                                v-model="reservationForm.reservation_time"
                                type="time"
                                required
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring"
                            />
                            <p
                                v-if="reservationErrors.reservation_time"
                                class="text-xs text-red-500"
                            >
                                {{ reservationErrors.reservation_time }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <label for="res-notes" class="text-xs font-medium">
                            Notes (optional)
                        </label>
                        <textarea
                            id="res-notes"
                            v-model="reservationForm.notes"
                            rows="2"
                            maxlength="500"
                            placeholder="Birthday, high chair, allergies…"
                            class="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring"
                        />
                    </div>

                    <label class="flex items-start gap-2 rounded-lg border bg-muted/30 px-3 py-2 text-xs">
                        <input
                            v-model="reservationForm.join_waitlist"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-input"
                        />
                        <span>
                            Join the waitlist if this time is fully booked.
                        </span>
                    </label>
                    <p
                        v-if="reservationErrors.reservation_time"
                        class="text-xs text-red-500"
                    >
                        {{ reservationErrors.reservation_time }}
                    </p>

                    <div class="hidden" aria-hidden="true">
                        <label for="res-website">Website</label>
                        <input
                            id="res-website"
                            v-model="reservationForm.website"
                            type="text"
                            tabindex="-1"
                            autocomplete="off"
                        />
                    </div>

                    <DialogFooter>
                        <button
                            type="submit"
                            class="w-full rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="reserving"
                        >
                            {{ reserving ? 'Sending request…' : 'Request table' }}
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="optionsOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{ optionsItem?.name }}
                    </DialogTitle>
                    <DialogDescription>
                        Choose the options for this item. Required groups must be
                        selected before adding it to your order.
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="optionsItem"
                    class="max-h-[60vh] space-y-4 overflow-y-auto pr-1"
                >
                    <div
                        v-for="group in optionGroups(optionsItem)"
                        :key="group.id"
                        class="space-y-2"
                    >
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold">
                                {{ group.name }}
                            </p>
                            <span
                                class="rounded-full border px-2 py-0.5 text-[10px] uppercase tracking-wide opacity-70"
                            >
                                {{
                                    group.is_required ? 'Required' : 'Optional'
                                }}
                            </span>
                            <span
                                v-if="group.allow_multiple"
                                class="rounded-full border px-2 py-0.5 text-[10px] uppercase tracking-wide opacity-70"
                            >
                                Choose any
                            </span>
                        </div>

                        <div class="grid gap-1.5">
                            <label
                                v-for="option in group.options"
                                :key="option.id"
                                class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border px-3 py-2 text-sm"
                            >
                                <span class="flex items-center gap-2">
                                    <input
                                        :type="
                                            group.allow_multiple
                                                ? 'checkbox'
                                                : 'radio'
                                        "
                                        :name="`option-group-${group.id}`"
                                        :checked="
                                            optionGroupSelected(group).includes(
                                                option.id,
                                            )
                                        "
                                        @change="chooseOption(group, option)"
                                    />
                                    {{ option.name }}
                                </span>
                                <span
                                    v-if="option.price_cents"
                                    class="text-xs tabular-nums opacity-70"
                                >
                                    +{{ formatMoney(option.price_cents) }}
                                </span>
                            </label>
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-between border-t pt-3"
                    >
                        <div class="flex items-center gap-1 rounded-full border p-0.5">
                            <button
                                type="button"
                                class="flex size-7 items-center justify-center rounded-full transition hover:bg-black/5 dark:hover:bg-white/10"
                                aria-label="Remove one"
                                @click="optionQuantity = Math.max(1, optionQuantity - 1)"
                            >
                                <Minus class="size-3.5" />
                            </button>
                            <span
                                class="min-w-5 text-center text-xs font-semibold tabular-nums"
                            >
                                {{ optionQuantity }}
                            </span>
                            <button
                                type="button"
                                class="flex size-7 items-center justify-center rounded-full transition hover:bg-black/5 dark:hover:bg-white/10"
                                aria-label="Add one"
                                @click="optionQuantity += 1"
                            >
                                <Plus class="size-3.5" />
                            </button>
                        </div>
                        <span class="text-sm font-semibold tabular-nums">
                            {{ formatMoney(configuredTotalCents) }}
                        </span>
                    </div>
                </div>

                <DialogFooter>
                    <button
                        type="button"
                        class="w-full rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="
                            !optionsItem ||
                            !optionsValid(optionsItem) ||
                            placingOrder
                        "
                        @click="addConfiguredItem"
                    >
                        Add to order · {{ formatMoney(configuredTotalCents) }}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Sheet v-model:open="cartOpen">
            <SheetContent
                side="right"
                class="flex w-full flex-col gap-0 p-0 sm:max-w-md"
            >
                <SheetHeader class="border-b p-5">
                    <SheetTitle>Your order</SheetTitle>
                    <SheetDescription>
                        <span v-if="props.restaurant.table">
                            Table {{ props.restaurant.table }} ·
                        </span>
                        Pay at the table, on pickup, or on delivery.
                    </SheetDescription>
                </SheetHeader>

                <div class="flex-1 space-y-5 overflow-y-auto p-5">
                    <div v-if="cartLines.length === 0" class="py-10 text-center">
                        <ShoppingBag class="mx-auto size-8 text-muted-foreground" />
                        <p class="mt-2 text-sm font-medium">
                            Your order is empty
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Add dishes from the menu to get started.
                        </p>
                    </div>

                    <div v-else class="space-y-4">
                        <div
                            v-for="line in cartLines"
                            :key="line.key"
                            class="border-b pb-4 last:border-0 last:pb-0"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold">
                                        {{ line.item.name }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-muted-foreground">
                                        {{ formatMoney(lineUnitPrice(line)) }}
                                        each
                                    </p>
                                    <div
                                        v-if="line.options.length"
                                        class="mt-1 space-y-0.5 text-[11px] text-muted-foreground"
                                    >
                                        <p
                                            v-for="option in line.options"
                                            :key="option.id"
                                        >
                                            {{ option.group }}:
                                            {{ option.name }}
                                            <span v-if="option.price_cents"
                                                >+{{
                                                    formatMoney(
                                                        option.price_cents,
                                                    )
                                                }}</span
                                            >
                                        </p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="text-muted-foreground transition hover:text-destructive"
                                    :aria-label="`Remove ${line.item.name}`"
                                    @click="delete cart[line.key]"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>

                            <div class="mt-2 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-1 rounded-full border p-0.5">
                                    <button
                                        type="button"
                                        class="flex size-7 items-center justify-center rounded-full transition hover:bg-muted"
                                        :aria-label="`Remove one ${line.item.name}`"
                                        @click="changeQuantity(line, -1)"
                                    >
                                        <Minus class="size-3.5" />
                                    </button>
                                    <span
                                        class="min-w-5 text-center text-xs font-semibold tabular-nums"
                                    >
                                        {{ line.quantity }}
                                    </span>
                                    <button
                                        type="button"
                                        class="flex size-7 items-center justify-center rounded-full transition hover:bg-muted"
                                        :aria-label="`Add one ${line.item.name}`"
                                        @click="addToOrder(line.item)"
                                    >
                                        <Plus class="size-3.5" />
                                    </button>
                                </div>
                                <span class="text-sm font-semibold tabular-nums">
                                    {{
                                        formatMoney(
                                            lineUnitPrice(line) * line.quantity,
                                        )
                                    }}
                                </span>
                            </div>

                            <input
                                v-model="line.notes"
                                type="text"
                                maxlength="255"
                                placeholder="Special request (optional)"
                                class="mt-2 w-full rounded-lg border bg-background px-3 py-2 text-xs outline-none focus-visible:border-ring"
                            />
                        </div>
                    </div>

                    <div v-if="cartLines.length" class="space-y-3 border-t pt-4">
                        <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Your details
                        </p>

                        <div class="grid gap-2">
                            <label for="order-type" class="text-xs font-medium">
                                Service
                            </label>
                            <select
                                id="order-type"
                                v-model="orderForm.order_type"
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring"
                            >
                                <option value="dine_in">Dine in</option>
                                <option value="takeaway">Takeaway</option>
                                <option
                                    v-if="props.restaurant.delivery_enabled"
                                    value="delivery"
                                >
                                    Delivery
                                </option>
                            </select>
                            <p
                                v-if="orderErrors.order_type"
                                class="text-xs text-red-500"
                            >
                                {{ orderErrors.order_type }}
                            </p>
                        </div>

                        <div
                            v-if="orderForm.order_type === 'dine_in'"
                            class="grid gap-2"
                        >
                            <label for="order-table" class="text-xs font-medium">
                                Table number
                            </label>
                            <input
                                id="order-table"
                                v-model="orderForm.table_number"
                                type="text"
                                maxlength="20"
                                placeholder="7"
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring"
                            />
                            <p
                                v-if="orderErrors.table_number"
                                class="text-xs text-red-500"
                            >
                                {{ orderErrors.table_number }}
                            </p>
                        </div>

                        <div
                            v-if="orderForm.order_type === 'delivery'"
                            class="grid gap-2"
                        >
                            <label
                                for="order-address"
                                class="text-xs font-medium"
                            >
                                Delivery address
                            </label>
                            <textarea
                                id="order-address"
                                v-model="orderForm.delivery_address"
                                rows="2"
                                maxlength="255"
                                autocomplete="street-address"
                                placeholder="Street, building, apartment, city"
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring"
                            />
                            <p
                                v-if="orderErrors.delivery_address"
                                class="text-xs text-red-500"
                            >
                                {{ orderErrors.delivery_address }}
                            </p>
                            <label
                                for="order-postcode"
                                class="mt-2 text-xs font-medium"
                            >
                                Postcode
                            </label>
                            <input
                                id="order-postcode"
                                v-model="orderForm.delivery_postcode"
                                type="text"
                                maxlength="20"
                                autocomplete="postal-code"
                                placeholder="Delivery postcode"
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring"
                            />
                            <p
                                v-if="orderErrors.delivery_postcode"
                                class="text-xs text-red-500"
                            >
                                {{ orderErrors.delivery_postcode }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <label
                                for="order-scheduled"
                                class="text-xs font-medium"
                            >
                                Schedule for later (optional)
                            </label>
                            <input
                                id="order-scheduled"
                                v-model="orderForm.scheduled_for"
                                type="datetime-local"
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring"
                            />
                            <p
                                v-if="orderErrors.scheduled_for"
                                class="text-xs text-red-500"
                            >
                                {{ orderErrors.scheduled_for }}
                            </p>
                        </div>

                        <div class="grid gap-2 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <label for="order-name" class="text-xs font-medium">
                                    Name
                                </label>
                                <input
                                    id="order-name"
                                    v-model="orderForm.customer_name"
                                    type="text"
                                    autocomplete="name"
                                    placeholder="Your name"
                                    class="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring"
                                />
                                <p
                                    v-if="orderErrors.customer_name"
                                    class="text-xs text-red-500"
                                >
                                    {{ orderErrors.customer_name }}
                                </p>
                            </div>
                            <div class="grid gap-2">
                                <label for="order-phone" class="text-xs font-medium">
                                    Phone
                                </label>
                                <input
                                    id="order-phone"
                                    v-model="orderForm.customer_phone"
                                    type="tel"
                                    autocomplete="tel"
                                    placeholder="Your phone"
                                    class="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring"
                                />
                                <p
                                    v-if="orderErrors.customer_phone"
                                    class="text-xs text-red-500"
                                >
                                    {{ orderErrors.customer_phone }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <label for="order-email" class="text-xs font-medium">
                                Email (optional)
                            </label>
                            <input
                                id="order-email"
                                v-model="orderForm.customer_email"
                                type="email"
                                autocomplete="email"
                                placeholder="you@example.com"
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring"
                            />
                            <p
                                v-if="orderErrors.customer_email"
                                class="text-xs text-red-500"
                            >
                                {{ orderErrors.customer_email }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <label for="order-notes" class="text-xs font-medium">
                                Order notes (optional)
                            </label>
                            <textarea
                                id="order-notes"
                                v-model="orderForm.notes"
                                rows="3"
                                maxlength="500"
                                placeholder="Allergies, delivery instructions…"
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring"
                            />
                            <p
                                v-if="orderErrors.notes"
                                class="text-xs text-red-500"
                            >
                                {{ orderErrors.notes }}
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <label for="order-coupon" class="text-xs font-medium">
                                Promo code (optional)
                            </label>
                            <input
                                id="order-coupon"
                                v-model="orderForm.coupon_code"
                                type="text"
                                maxlength="40"
                                placeholder="WELCOME10"
                                class="w-full rounded-lg border bg-background px-3 py-2 text-sm uppercase outline-none focus-visible:border-ring"
                            />
                            <p
                                v-if="orderErrors.coupon_code"
                                class="text-xs text-red-500"
                            >
                                {{ orderErrors.coupon_code }}
                            </p>
                        </div>

                        <label
                            class="flex items-start gap-2 rounded-lg border p-3 text-xs"
                        >
                            <input
                                v-model="orderForm.redeem_points"
                                type="checkbox"
                                class="mt-0.5 size-4 rounded border-input"
                            />
                            <span>
                                <span class="font-medium">Use loyalty points</span>
                                <span class="mt-0.5 block text-muted-foreground">
                                    Redeem available points on this phone
                                    number. 100 points = 1 currency unit.
                                </span>
                            </span>
                        </label>

                        <div class="grid gap-2">
                            <p class="text-xs font-medium">Tip</p>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="percent in [0, 5, 10, 15]"
                                    :key="percent"
                                    type="button"
                                    class="rounded-full border px-3 py-1.5 text-xs font-medium transition"
                                    :class="
                                        tipPercent === percent
                                            ? 'border-amber-500 bg-amber-500 text-white'
                                            : 'bg-background hover:bg-muted'
                                    "
                                    @click="tipPercent = percent"
                                >
                                    {{
                                        percent === 0
                                            ? 'No tip'
                                            : `${percent}%`
                                    }}
                                </button>
                            </div>
                            <p
                                v-if="tipCents > 0"
                                class="text-[11px] text-muted-foreground"
                            >
                                Tip {{ formatMoney(tipCents) }}
                            </p>
                        </div>

                        <div class="hidden" aria-hidden="true">
                            <label for="order-website">Website</label>
                            <input
                                id="order-website"
                                v-model="orderForm.website"
                                type="text"
                                tabindex="-1"
                                autocomplete="off"
                            />
                        </div>

                        <p
                            v-if="orderErrors.items"
                            class="rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-xs text-red-600"
                        >
                            {{ orderErrors.items }}
                        </p>
                    </div>
                </div>

                <SheetFooter class="border-t">
                    <div class="space-y-1 text-xs">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span class="tabular-nums">
                                {{ formatMoney(cartSubtotalCents) }}
                            </span>
                        </div>
                        <div
                            v-if="serviceCents > 0"
                            class="flex items-center justify-between gap-3"
                        >
                            <span class="text-muted-foreground"
                                >Service ({{ props.restaurant.service_rate }}%)</span
                            >
                            <span class="tabular-nums">
                                {{ formatMoney(serviceCents) }}
                            </span>
                        </div>
                        <div
                            v-if="deliveryCents > 0"
                            class="flex items-center justify-between gap-3"
                        >
                            <span class="text-muted-foreground">Delivery</span>
                            <span class="tabular-nums">
                                {{ formatMoney(deliveryCents) }}
                            </span>
                        </div>
                        <div
                            v-if="taxCents > 0"
                            class="flex items-center justify-between gap-3"
                        >
                            <span class="text-muted-foreground"
                                >Tax ({{ props.restaurant.tax_rate }}%)</span
                            >
                            <span class="tabular-nums">
                                {{ formatMoney(taxCents) }}
                            </span>
                        </div>
                        <div
                            v-if="tipCents > 0"
                            class="flex items-center justify-between gap-3"
                        >
                            <span class="text-muted-foreground">Tip</span>
                            <span class="tabular-nums">
                                {{ formatMoney(tipCents) }}
                            </span>
                        </div>
                        <div
                            class="flex items-center justify-between gap-3 border-t pt-2 text-sm"
                        >
                            <span class="text-muted-foreground">Total</span>
                            <span class="text-lg font-semibold tabular-nums">
                                {{ formatMoney(orderTotalCents) }}
                            </span>
                        </div>
                        <p
                            v-if="belowMinimum"
                            class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-amber-700 dark:text-amber-300"
                        >
                            Minimum order is
                            {{ formatMoney(props.restaurant.min_order_cents) }}.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="w-full rounded-lg bg-amber-500 px-4 py-3 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="
                            placingOrder ||
                            cartLines.length === 0 ||
                            belowMinimum
                        "
                        @click="submitOrder"
                    >
                        {{
                            placingOrder
                                ? 'Sending order…'
                                : `Place order · ${formatMoney(orderTotalCents)}`
                        }}
                    </button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    </div>
</template>
