<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Download,
    ExternalLink,
    Eye,
    EyeOff,
    ListPlus,
    Pencil,
    Plus,
    QrCode,
    Settings2,
    ShoppingBag,
    TicketPercent,
    Trash2,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import RestaurantTabs from '@/components/RestaurantTabs.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type MenuOptionChoice = {
    id: number;
    name: string;
    price: string | null;
    price_cents: number;
    is_available: boolean;
};

type MenuOptionGroup = {
    id: number;
    name: string;
    is_required: boolean;
    allow_multiple: boolean;
    options: MenuOptionChoice[];
};

type MenuItem = {
    id: number;
    name: string;
    description: string | null;
    price: string | null;
    sale_price: string | null;
    sale_starts_at: string | null;
    sale_ends_at: string | null;
    is_on_sale: boolean;
    dietary: string | null;
    allergens: string[];
    image_url: string | null;
    is_available: boolean;
    is_featured: boolean;
    option_groups: MenuOptionGroup[];
};

type MenuCategory = {
    id: number;
    name: string;
    description: string | null;
    daypart: string;
    is_active: boolean;
    items: MenuItem[];
};

type Coupon = {
    id: number;
    code: string;
    label: string;
    type: string;
    value: string;
    used_count: number;
    max_uses: number | null;
    expires_at: string | null;
    is_active: boolean;
};

type ItemDraft = {
    name: string;
    price: string;
    sale_price: string;
    sale_starts_at: string;
    sale_ends_at: string;
    description: string;
    dietary: string;
    allergens: string[];
    is_featured: boolean;
    image: File | null;
};

const props = defineProps<{
    categories: MenuCategory[];
    settings: {
        ordering_enabled: boolean;
        currency: string;
    };
    coupons: Coupon[];
    currencies: Record<string, string>;
    dayparts: Record<string, string>;
    allergens: Record<string, string>;
    restaurant: { username: string; name: string };
    restaurants: { username: string; name: string }[];
    baseUrl: string;
    publicUrl: string;
    qrUrl: string;
    ordersUrl: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Restaurant', href: '/restaurant' },
            { title: 'Menu', href: '/restaurant' },
        ],
    },
});

const urls = computed(() => ({
    settings: `${props.baseUrl}/settings`,
    categories: `${props.baseUrl}/categories`,
    category: (id: number): string => `${props.baseUrl}/categories/${id}`,
    items: (categoryId: number): string =>
        `${props.baseUrl}/categories/${categoryId}/items`,
    item: (id: number): string => `${props.baseUrl}/items/${id}`,
    optionGroups: (itemId: number): string =>
        `${props.baseUrl}/items/${itemId}/option-groups`,
    optionGroup: (itemId: number, groupId: number): string =>
        `${props.baseUrl}/items/${itemId}/option-groups/${groupId}`,
    options: (itemId: number, groupId: number): string =>
        `${props.baseUrl}/items/${itemId}/option-groups/${groupId}/options`,
    option: (itemId: number, groupId: number, optionId: number): string =>
        `${props.baseUrl}/items/${itemId}/option-groups/${groupId}/options/${optionId}`,
    promotions: `${props.baseUrl}/promotions`,
    promotion: (id: number): string => `${props.baseUrl}/promotions/${id}`,
}));

function switchRestaurant(event: Event): void {
    const username = (event.target as HTMLSelectElement).value;

    router.visit(`/restaurant/${encodeURIComponent(username)}/menu`);
}

const inputClass =
    'flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm';

const settingsForm = reactive({
    ordering_enabled: props.settings.ordering_enabled,
    currency: props.settings.currency,
});
const savingSettings = ref(false);

const couponForm = reactive({
    code: '',
    type: 'percent',
    value: '10',
    min_order: '',
    max_uses: '',
    expires_at: '',
});
const couponErrors = ref<Record<string, string>>({});
const addingCoupon = ref(false);

function addCoupon(): void {
    router.post(urls.value.promotions, { ...couponForm }, {
        preserveScroll: true,
        onStart: () => {
            addingCoupon.value = true;
        },
        onFinish: () => {
            addingCoupon.value = false;
        },
        onError: (errors) => {
            couponErrors.value = errors;
        },
        onSuccess: () => {
            couponForm.code = '';
            couponForm.value = '10';
            couponForm.min_order = '';
            couponForm.max_uses = '';
            couponForm.expires_at = '';
            couponErrors.value = {};
        },
    });
}

function toggleCoupon(coupon: Coupon): void {
    router.patch(
        urls.value.promotion(coupon.id),
        { is_active: !coupon.is_active },
        { preserveScroll: true },
    );
}

function deleteCoupon(coupon: Coupon): void {
    if (!confirm(`Delete promo code ${coupon.code}?`)) {
        return;
    }

    router.delete(urls.value.promotion(coupon.id), { preserveScroll: true });
}

const tableNumber = ref('1');
const tableQrUrl = computed(
    () => `${props.qrUrl}?table=${encodeURIComponent(tableNumber.value || '1')}`,
);
const tableQrDownloadUrl = computed(() => `${tableQrUrl.value}&download=1`);

const categoryForm = reactive({
    name: '',
    description: '',
    daypart: 'all_day',
});
const categoryErrors = ref<Record<string, string>>({});
const addingCategory = ref(false);

const editingCategoryId = ref<number | null>(null);
const categoryDraft = reactive({
    name: '',
    description: '',
    daypart: 'all_day',
});
const categoryEditErrors = ref<Record<string, string>>({});
const savingCategory = ref(false);

const blankItem = (): ItemDraft => ({
    name: '',
    price: '',
    sale_price: '',
    sale_starts_at: '',
    sale_ends_at: '',
    description: '',
    dietary: '',
    allergens: [],
    is_featured: false,
    image: null,
});

const itemForms = reactive<Record<number, ItemDraft>>({});
const itemErrors = ref<Record<string, string>>({});
const addingItemId = ref<number | null>(null);

const editingItemId = ref<number | null>(null);
const itemEdit = reactive<ItemDraft & { removeImage: boolean }>({
    ...blankItem(),
    removeImage: false,
});
const itemEditErrors = ref<Record<string, string>>({});
const savingItem = ref(false);

const optionsItemId = ref<number | null>(null);
const optionsItem = computed<MenuItem | null>(() =>
    props.categories
        .flatMap((category) => category.items)
        .find((item) => item.id === optionsItemId.value) ?? null,
);
const optionsOpen = computed({
    get: () => optionsItem.value !== null,
    set: (open: boolean) => {
        if (!open) {
            optionsItemId.value = null;
        }
    },
});

const groupDraft = reactive({
    name: '',
    is_required: false,
    allow_multiple: false,
});
const groupErrors = ref<Record<string, string>>({});
const addingGroup = ref(false);
const choiceDrafts = reactive<Record<number, { name: string; price: string }>>(
    {},
);
const choiceErrors = ref<Record<string, string>>({});
const addingChoice = ref<number | null>(null);

function choiceDraft(groupId: number): { name: string; price: string } {
    choiceDrafts[groupId] ??= { name: '', price: '' };

    return choiceDrafts[groupId];
}

function openOptions(item: MenuItem): void {
    optionsItemId.value = item.id;
    groupDraft.name = '';
    groupDraft.is_required = false;
    groupDraft.allow_multiple = false;
    groupErrors.value = {};
    choiceErrors.value = {};

    item.option_groups.forEach((group) => choiceDraft(group.id));
}

function addGroup(item: MenuItem): void {
    router.post(
        urls.value.optionGroups(item.id),
        {
            name: groupDraft.name,
            is_required: groupDraft.is_required,
            allow_multiple: groupDraft.allow_multiple,
        },
        {
            preserveScroll: true,
            onStart: () => {
                addingGroup.value = true;
            },
            onFinish: () => {
                addingGroup.value = false;
            },
            onError: (errors) => {
                groupErrors.value = errors;
            },
            onSuccess: () => {
                groupDraft.name = '';
                groupDraft.is_required = false;
                groupDraft.allow_multiple = false;
                groupErrors.value = {};
            },
        },
    );
}

function removeGroup(item: MenuItem, group: MenuOptionGroup): void {
    if (!confirm(`Delete the "${group.name}" options?`)) {
        return;
    }

    router.delete(urls.value.optionGroup(item.id, group.id), {
        preserveScroll: true,
    });
}

function addChoice(item: MenuItem, group: MenuOptionGroup): void {
    const draft = choiceDraft(group.id);

    router.post(
        urls.value.options(item.id, group.id),
        { name: draft.name, price: draft.price },
        {
            preserveScroll: true,
            onStart: () => {
                addingChoice.value = group.id;
            },
            onFinish: () => {
                addingChoice.value = null;
            },
            onError: (errors) => {
                choiceErrors.value = errors;
            },
            onSuccess: () => {
                choiceDraft(group.id).name = '';
                choiceDraft(group.id).price = '';
                choiceErrors.value = {};
            },
        },
    );
}

function removeChoice(
    item: MenuItem,
    group: MenuOptionGroup,
    choice: MenuOptionChoice,
): void {
    router.delete(urls.value.option(item.id, group.id, choice.id), {
        preserveScroll: true,
    });
}

watch(
    () => props.categories,
    (categories) => {
        categories.forEach((category) => {
            itemForms[category.id] ??= blankItem();
        });
    },
    { immediate: true, deep: true },
);

function saveSettings(): void {
    router.patch(
        urls.value.settings,
        {
            ordering_enabled: settingsForm.ordering_enabled,
            currency: settingsForm.currency,
        },
        {
            preserveScroll: true,
            onStart: () => {
                savingSettings.value = true;
            },
            onFinish: () => {
                savingSettings.value = false;
            },
        },
    );
}

function onItemImage(event: Event, target: ItemDraft): void {
    target.image = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function toggleAllergen(list: string[], allergen: string): void {
    const index = list.indexOf(allergen);

    if (index === -1) {
        list.push(allergen);
    } else {
        list.splice(index, 1);
    }
}

function addCategory(): void {
    router.post(urls.value.categories, categoryForm, {
        preserveScroll: true,
        onStart: () => {
            addingCategory.value = true;
        },
        onFinish: () => {
            addingCategory.value = false;
        },
        onError: (errors) => {
            categoryErrors.value = errors;
        },
        onSuccess: () => {
            categoryErrors.value = {};
            categoryForm.name = '';
            categoryForm.description = '';
            categoryForm.daypart = 'all_day';
        },
    });
}

function startEditingCategory(category: MenuCategory): void {
    editingCategoryId.value = category.id;
    categoryDraft.name = category.name;
    categoryDraft.description = category.description ?? '';
    categoryDraft.daypart = category.daypart;
    categoryEditErrors.value = {};
}

function cancelEditingCategory(): void {
    editingCategoryId.value = null;
    categoryEditErrors.value = {};
}

function saveCategory(category: MenuCategory): void {
    router.patch(
        urls.value.category(category.id),
        {
            name: categoryDraft.name,
            description: categoryDraft.description || null,
            daypart: categoryDraft.daypart,
            is_active: category.is_active,
        },
        {
            preserveScroll: true,
            onStart: () => {
                savingCategory.value = true;
            },
            onFinish: () => {
                savingCategory.value = false;
            },
            onError: (errors) => {
                categoryEditErrors.value = errors;
            },
            onSuccess: () => {
                categoryEditErrors.value = {};
                editingCategoryId.value = null;
            },
        },
    );
}

function toggleCategory(category: MenuCategory): void {
    router.patch(
        urls.value.category(category.id),
        {
            name: category.name,
            description: category.description,
            daypart: category.daypart,
            is_active: !category.is_active,
        },
        { preserveScroll: true },
    );
}

function removeCategory(category: MenuCategory): void {
    if (
        !confirm(
            `Delete "${category.name}" and all of its menu items?`,
        )
    ) {
        return;
    }

    router.delete(urls.value.category(category.id), {
        preserveScroll: true,
    });
}

function addItem(category: MenuCategory): void {
    const form = itemForms[category.id];

    router.post(
        urls.value.items(category.id),
        {
            ...form,
            allergens: form.allergens,
            is_available: true,
            is_featured: form.is_featured,
        },
        {
            forceFormData: form.image !== null,
            preserveScroll: true,
            onStart: () => {
                addingItemId.value = category.id;
            },
            onFinish: () => {
                addingItemId.value = null;
            },
            onError: (errors) => {
                itemErrors.value = errors;
            },
            onSuccess: () => {
                itemErrors.value = {};
                itemForms[category.id] = blankItem();
            },
        },
    );
}

function startEditingItem(item: MenuItem): void {
    editingItemId.value = item.id;
    itemEdit.name = item.name;
    itemEdit.price = item.price ?? '';
    itemEdit.description = item.description ?? '';
    itemEdit.sale_price = item.sale_price ?? '';
    itemEdit.sale_starts_at = item.sale_starts_at ?? '';
    itemEdit.sale_ends_at = item.sale_ends_at ?? '';
    itemEdit.dietary = item.dietary ?? '';
    itemEdit.allergens = [...item.allergens];
    itemEdit.is_featured = item.is_featured;
    itemEdit.image = null;
    itemEdit.removeImage = false;
    itemEditErrors.value = {};
}

function cancelEditingItem(): void {
    editingItemId.value = null;
    itemEditErrors.value = {};
}

function saveItem(item: MenuItem): void {
    router.patch(
        urls.value.item(item.id),
        {
            ...itemEdit,
            allergens: itemEdit.allergens,
            is_available: item.is_available,
            is_featured: itemEdit.is_featured,
            remove_image: itemEdit.removeImage,
        },
        {
            forceFormData: itemEdit.image !== null,
            preserveScroll: true,
            onStart: () => {
                savingItem.value = true;
            },
            onFinish: () => {
                savingItem.value = false;
            },
            onError: (errors) => {
                itemEditErrors.value = errors;
            },
            onSuccess: () => {
                itemEditErrors.value = {};
                editingItemId.value = null;
            },
        },
    );
}

function toggleItem(item: MenuItem): void {
    router.patch(
        urls.value.item(item.id),
        {
            name: item.name,
            description: item.description,
            price: item.price,
            dietary: item.dietary,
            is_available: !item.is_available,
            is_featured: item.is_featured,
        },
        { preserveScroll: true },
    );
}

function removeItem(item: MenuItem): void {
    if (!confirm(`Delete "${item.name}"?`)) {
        return;
    }

    router.delete(urls.value.item(item.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Menu" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="`${props.restaurant.name} menu`"
                description="Build the menu, take orders and print table QR codes"
            />
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <a :href="props.ordersUrl">
                        <ShoppingBag class="size-4" />
                        Orders
                    </a>
                </Button>
                <Button variant="outline" as-child>
                    <a :href="props.publicUrl" target="_blank">
                        <ExternalLink class="size-4" />
                        View menu
                    </a>
                </Button>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <RestaurantTabs :restaurant="props.restaurant" active="menu" />
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

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <Settings2 class="size-4" />
                    Ordering &amp; table QR
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-5">
                <div
                    class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px_auto] lg:items-end"
                >
                    <div class="flex items-start gap-3 rounded-lg border p-4">
                        <input
                            id="menu-ordering-enabled"
                            v-model="settingsForm.ordering_enabled"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-input"
                        />
                        <div class="grid gap-1">
                            <Label for="menu-ordering-enabled">
                                Accept orders
                            </Label>
                            <p class="text-sm text-muted-foreground">
                                Show the order button and cart on the public
                                menu for {{ props.restaurant.name }}. Orders are
                                paid at the table, on pickup, or on delivery.
                            </p>
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="menu-currency">Menu currency</Label>
                        <select
                            id="menu-currency"
                            v-model="settingsForm.currency"
                            :class="inputClass"
                        >
                            <option
                                v-for="(label, code) in props.currencies"
                                :key="code"
                                :value="code"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>
                    <Button
                        type="button"
                        :disabled="savingSettings"
                        @click="saveSettings"
                    >
                        Save settings
                    </Button>
                </div>

                <div class="rounded-lg border bg-muted/20 p-4">
                    <div
                        class="flex flex-wrap items-start justify-between gap-4"
                    >
                        <div class="max-w-md space-y-1">
                            <p class="flex items-center gap-2 text-sm font-medium">
                                <QrCode class="size-4" />
                                Table QR code
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Put a different QR code on each table. The
                                table number is included when the guest orders.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="grid gap-2">
                                <Label for="table-number">Table number</Label>
                                <Input
                                    id="table-number"
                                    v-model="tableNumber"
                                    class="w-28"
                                    maxlength="20"
                                    placeholder="1"
                                />
                            </div>
                            <Button variant="outline" as-child>
                                <a :href="tableQrDownloadUrl">
                                    <Download class="size-4" />
                                    Download QR
                                </a>
                            </Button>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-4">
                        <img
                            :src="tableQrUrl"
                            :alt="`QR code for table ${tableNumber || '1'}`"
                            class="size-32 rounded-lg border bg-white p-2"
                        />
                        <p class="max-w-sm text-xs text-muted-foreground">
                            Print the code and place it on the table. Guests
                            scan it with their phone camera — no app needed.
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <TicketPercent class="size-4" />
                    Promo codes
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-5">
                <div v-if="props.coupons.length" class="divide-y rounded-lg border">
                    <div
                        v-for="coupon in props.coupons"
                        :key="coupon.id"
                        class="flex flex-wrap items-center justify-between gap-3 px-3 py-2.5 text-sm"
                    >
                        <div>
                            <p class="flex items-center gap-2 font-mono font-semibold">
                                {{ coupon.code }}
                                <span
                                    v-if="!coupon.is_active"
                                    class="rounded-full border px-2 py-0.5 text-[10px] uppercase text-muted-foreground"
                                >
                                    Paused
                                </span>
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ coupon.label }} · {{ coupon.used_count }} used
                                <span v-if="coupon.max_uses"
                                    >of {{ coupon.max_uses }}</span
                                >
                                <span v-if="coupon.expires_at"
                                    >· expires {{ coupon.expires_at }}</span
                                >
                            </p>
                        </div>
                        <div class="flex gap-1.5">
                            <Button
                                size="sm"
                                variant="outline"
                                @click="toggleCoupon(coupon)"
                            >
                                {{ coupon.is_active ? 'Pause' : 'Activate' }}
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="text-destructive"
                                aria-label="Delete promo code"
                                @click="deleteCoupon(coupon)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    Promo codes apply at guest checkout and can be limited by
                    minimum order, total uses and expiry date.
                </p>

                <form
                    class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                    @submit.prevent="addCoupon"
                >
                    <div class="grid gap-2">
                        <Label for="coupon-code">Code</Label>
                        <Input
                            id="coupon-code"
                            v-model="couponForm.code"
                            placeholder="WELCOME10"
                            maxlength="40"
                            required
                        />
                        <InputError :message="couponErrors.code" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="coupon-type">Type</Label>
                        <select
                            id="coupon-type"
                            v-model="couponForm.type"
                            :class="inputClass"
                        >
                            <option value="percent">Percentage</option>
                            <option value="fixed">Fixed amount</option>
                        </select>
                        <InputError :message="couponErrors.type" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="coupon-value">
                            {{ couponForm.type === 'percent' ? 'Percent' : 'Amount' }}
                        </Label>
                        <Input
                            id="coupon-value"
                            v-model="couponForm.value"
                            type="number"
                            min="0.01"
                            step="0.01"
                            required
                        />
                        <InputError :message="couponErrors.value" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="coupon-min">Minimum order</Label>
                        <Input
                            id="coupon-min"
                            v-model="couponForm.min_order"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                        />
                        <InputError :message="couponErrors.min_order" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="coupon-max">Max uses</Label>
                        <Input
                            id="coupon-max"
                            v-model="couponForm.max_uses"
                            type="number"
                            min="1"
                            placeholder="Unlimited"
                        />
                        <InputError :message="couponErrors.max_uses" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="coupon-expiry">Expires</Label>
                        <Input
                            id="coupon-expiry"
                            v-model="couponForm.expires_at"
                            type="date"
                        />
                        <InputError :message="couponErrors.expires_at" />
                    </div>
                    <div class="flex items-end sm:col-span-2">
                        <Button type="submit" :disabled="addingCoupon">
                            <Plus class="size-4" />
                            Add promo code
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Add a menu section</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_160px_auto] lg:items-end"
                    @submit.prevent="addCategory"
                >
                    <div class="grid gap-2">
                        <Label for="category-name">Section name</Label>
                        <Input
                            id="category-name"
                            v-model="categoryForm.name"
                            placeholder="Breakfast"
                        />
                        <InputError :message="categoryErrors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="category-description">Description</Label>
                        <Input
                            id="category-description"
                            v-model="categoryForm.description"
                            placeholder="Served until 11am"
                        />
                        <InputError :message="categoryErrors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="category-daypart">Menu</Label>
                        <select
                            id="category-daypart"
                            v-model="categoryForm.daypart"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                        >
                            <option
                                v-for="(label, value) in props.dayparts"
                                :key="value"
                                :value="value"
                            >
                                {{ label }}
                            </option>
                        </select>
                        <InputError :message="categoryErrors.daypart" />
                    </div>
                    <Button type="submit" :disabled="addingCategory">
                        <Plus class="size-4" />
                        Add section
                    </Button>
                </form>
            </CardContent>
        </Card>

        <div
            v-if="props.categories.length === 0"
            class="rounded-xl border border-dashed px-6 py-16 text-center"
        >
            <p class="font-medium">Your menu is empty</p>
            <p class="mt-1 text-sm text-muted-foreground">
                Add a section above, then fill it with dishes, drinks or
                services.
            </p>
        </div>

        <div v-else class="space-y-4">
            <div
                v-for="category in props.categories"
                :key="category.id"
                class="overflow-hidden rounded-xl border"
            >
                <div
                    class="flex flex-wrap items-start justify-between gap-3 border-b bg-muted/30 p-4"
                >
                    <div v-if="editingCategoryId === category.id" class="flex-1 space-y-3">
                        <div class="grid gap-2 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label :for="`category-name-${category.id}`">
                                    Section name
                                </Label>
                                <Input
                                    :id="`category-name-${category.id}`"
                                    v-model="categoryDraft.name"
                                />
                                <InputError :message="categoryEditErrors.name" />
                            </div>
                            <div class="grid gap-2">
                                <Label
                                    :for="`category-daypart-${category.id}`"
                                >
                                    Menu
                                </Label>
                                <select
                                    :id="`category-daypart-${category.id}`"
                                    v-model="categoryDraft.daypart"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                >
                                    <option
                                        v-for="(label, value) in props.dayparts"
                                        :key="value"
                                        :value="value"
                                    >
                                        {{ label }}
                                    </option>
                                </select>
                                <InputError
                                    :message="categoryEditErrors.daypart"
                                />
                            </div>
                            <div class="grid gap-2 sm:col-span-2">
                                <Label
                                    :for="`category-description-${category.id}`"
                                >
                                    Description
                                </Label>
                                <Input
                                    :id="`category-description-${category.id}`"
                                    v-model="categoryDraft.description"
                                />
                                <InputError
                                    :message="categoryEditErrors.description"
                                />
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                size="sm"
                                :disabled="savingCategory"
                                @click="saveCategory(category)"
                            >
                                Save
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                @click="cancelEditingCategory"
                            >
                                Cancel
                            </Button>
                        </div>
                    </div>

                    <div v-else class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base font-semibold">
                                {{ category.name }}
                            </h2>
                            <Badge
                                :variant="
                                    category.is_active ? 'default' : 'secondary'
                                "
                            >
                                {{ category.is_active ? 'Visible' : 'Hidden' }}
                            </Badge>
                            <Badge variant="outline">
                                {{ props.dayparts[category.daypart] ?? category.daypart }}
                            </Badge>
                        </div>
                        <p
                            v-if="category.description"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            {{ category.description }}
                        </p>
                    </div>

                    <div
                        v-if="editingCategoryId !== category.id"
                        class="flex items-center gap-1"
                    >
                        <Button
                            variant="ghost"
                            size="sm"
                            :aria-label="
                                category.is_active
                                    ? 'Hide section'
                                    : 'Show section'
                            "
                            @click="toggleCategory(category)"
                        >
                            <EyeOff
                                v-if="category.is_active"
                                class="size-4"
                            />
                            <Eye v-else class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            aria-label="Edit section"
                            @click="startEditingCategory(category)"
                        >
                            <Pencil class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-destructive"
                            aria-label="Delete section"
                            @click="removeCategory(category)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>

                <div class="p-4">
                    <ul
                        v-if="category.items.length"
                        class="divide-y rounded-lg border"
                    >
                        <li
                            v-for="item in category.items"
                            :key="item.id"
                            class="px-3 py-3"
                        >
                            <div
                                v-if="editingItemId === item.id"
                                class="space-y-3"
                            >
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label :for="`item-name-${item.id}`">
                                            Item name
                                        </Label>
                                        <Input
                                            :id="`item-name-${item.id}`"
                                            v-model="itemEdit.name"
                                        />
                                        <InputError
                                            :message="itemEditErrors.name"
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label :for="`item-price-${item.id}`">
                                            Price
                                        </Label>
                                        <Input
                                            :id="`item-price-${item.id}`"
                                            v-model="itemEdit.price"
                                            placeholder="12.00"
                                        />
                                        <p class="text-xs text-muted-foreground">
                                            Numbers only (e.g. 12.50) so guests
                                            can order this item.
                                        </p>
                                        <InputError
                                            :message="itemEditErrors.price"
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label :for="`item-sale-price-${item.id}`">
                                            Sale price
                                        </Label>
                                        <Input
                                            :id="`item-sale-price-${item.id}`"
                                            v-model="itemEdit.sale_price"
                                            placeholder="Leave blank for none"
                                        />
                                        <InputError
                                            :message="itemEditErrors.sale_price"
                                        />
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="grid gap-2">
                                            <Label :for="`item-sale-start-${item.id}`">
                                                Sale starts
                                            </Label>
                                            <Input
                                                :id="`item-sale-start-${item.id}`"
                                                v-model="itemEdit.sale_starts_at"
                                                type="time"
                                            />
                                            <InputError :message="itemEditErrors.sale_starts_at" />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label :for="`item-sale-end-${item.id}`">
                                                Sale ends
                                            </Label>
                                            <Input
                                                :id="`item-sale-end-${item.id}`"
                                                v-model="itemEdit.sale_ends_at"
                                                type="time"
                                            />
                                            <InputError :message="itemEditErrors.sale_ends_at" />
                                        </div>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label
                                            :for="`item-description-${item.id}`"
                                        >
                                            Description
                                        </Label>
                                        <Input
                                            :id="`item-description-${item.id}`"
                                            v-model="itemEdit.description"
                                        />
                                        <InputError
                                            :message="itemEditErrors.description"
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label :for="`item-dietary-${item.id}`">
                                            Dietary labels
                                        </Label>
                                        <Input
                                            :id="`item-dietary-${item.id}`"
                                            v-model="itemEdit.dietary"
                                            placeholder="V, GF, contains nuts"
                                        />
                                        <InputError
                                            :message="itemEditErrors.dietary"
                                        />
                                    </div>
                                    <div class="grid gap-2 sm:col-span-2">
                                        <Label
                                            :for="`item-allergens-${item.id}`"
                                        >
                                            Allergens
                                        </Label>
                                        <div class="flex flex-wrap gap-1.5">
                                            <button
                                                v-for="(
                                                    label, key
                                                ) in props.allergens"
                                                :key="key"
                                                type="button"
                                                class="rounded-full border px-2.5 py-1 text-xs transition"
                                                :class="
                                                    itemEdit.allergens.includes(
                                                        key,
                                                    )
                                                        ? 'border-amber-500 bg-amber-500/15 font-medium'
                                                        : 'border-input text-muted-foreground hover:bg-muted'
                                                "
                                                @click="
                                                    toggleAllergen(
                                                        itemEdit.allergens,
                                                        key,
                                                    )
                                                "
                                            >
                                                {{ label }}
                                            </button>
                                        </div>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Guests can filter these allergens out
                                            of the public menu.
                                        </p>
                                        <InputError
                                            :message="itemEditErrors.allergens"
                                        />
                                    </div>
                                    <label
                                        class="flex items-start gap-2 rounded-lg border p-3 sm:col-span-2"
                                    >
                                        <input
                                            v-model="itemEdit.is_featured"
                                            type="checkbox"
                                            class="mt-0.5 size-4 rounded border-input"
                                        />
                                        <span class="grid gap-0.5">
                                            <span class="text-sm font-medium">
                                                Chef's pick
                                            </span>
                                            <span
                                                class="text-xs text-muted-foreground"
                                            >
                                                Highlight this item in the
                                                featured row of your public
                                                menu.
                                            </span>
                                        </span>
                                    </label>
                                    <div class="grid gap-2 sm:col-span-2">
                                        <Label :for="`item-image-${item.id}`">
                                            Item image
                                        </Label>
                                        <div class="flex flex-wrap items-center gap-3">
                                            <img
                                                v-if="
                                                    item.image_url &&
                                                    !itemEdit.removeImage
                                                "
                                                :src="item.image_url"
                                                alt=""
                                                class="size-14 rounded-lg border object-cover"
                                            />
                                            <input
                                                :id="`item-image-${item.id}`"
                                                type="file"
                                                accept="image/jpeg,image/png,image/webp"
                                                class="text-sm"
                                                @change="onItemImage($event, itemEdit)"
                                            />
                                        </div>
                                        <label
                                            v-if="item.image_url"
                                            class="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                v-model="itemEdit.removeImage"
                                                type="checkbox"
                                                class="size-4 rounded border-input"
                                            />
                                            Remove current image
                                        </label>
                                        <InputError
                                            :message="itemEditErrors.image"
                                        />
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <Button
                                        size="sm"
                                        :disabled="savingItem"
                                        @click="saveItem(item)"
                                    >
                                        Save
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        @click="cancelEditingItem"
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </div>

                            <div
                                v-else
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div class="min-w-0">
                                    <img
                                        v-if="item.image_url"
                                        :src="item.image_url"
                                        alt=""
                                        class="mb-2 h-20 w-full max-w-56 rounded-lg border object-cover"
                                    />
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-medium">{{ item.name }}</p>
                                        <Badge
                                            v-if="item.is_featured"
                                            variant="default"
                                        >
                                            Chef's pick
                                        </Badge>
                                        <Badge
                                            v-if="item.option_groups.length"
                                            variant="secondary"
                                        >
                                            {{ item.option_groups.length }}
                                            {{
                                                item.option_groups.length === 1
                                                    ? 'option group'
                                                    : 'option groups'
                                            }}
                                        </Badge>
                                        <Badge
                                            v-if="!item.is_available"
                                            variant="secondary"
                                        >
                                            Unavailable
                                        </Badge>
                                        <span
                                            v-if="item.dietary"
                                            class="rounded-full border px-2 py-0.5 text-[10px] tracking-wide uppercase text-muted-foreground"
                                        >
                                            {{ item.dietary }}
                                        </span>
                                    </div>
                                    <p
                                        v-if="item.description"
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{ item.description }}
                                    </p>
                                </div>

                                <div class="flex items-center gap-1">
                                    <span
                                        v-if="item.price"
                                        class="mr-2 text-sm font-semibold tabular-nums"
                                    >
                                        {{ item.price }}
                                    </span>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        :aria-label="
                                            item.is_available
                                                ? 'Mark unavailable'
                                                : 'Mark available'
                                        "
                                        @click="toggleItem(item)"
                                    >
                                        <EyeOff
                                            v-if="item.is_available"
                                            class="size-4"
                                        />
                                        <Eye v-else class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        aria-label="Manage options"
                                        @click="openOptions(item)"
                                    >
                                        <ListPlus class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        aria-label="Edit item"
                                        @click="startEditingItem(item)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                        aria-label="Delete item"
                                        @click="removeItem(item)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        No items in this section yet.
                    </p>

                    <form
                        class="mt-4 grid gap-3 border-t pt-4 sm:grid-cols-2"
                        @submit.prevent="addItem(category)"
                    >
                        <div class="grid gap-2">
                            <Label :for="`new-item-name-${category.id}`">
                                Item name
                            </Label>
                            <Input
                                :id="`new-item-name-${category.id}`"
                                v-model="itemForms[category.id].name"
                                placeholder="Avocado toast"
                            />
                            <InputError :message="itemErrors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`new-item-price-${category.id}`">
                                Price
                            </Label>
                            <Input
                                :id="`new-item-price-${category.id}`"
                                v-model="itemForms[category.id].price"
                                placeholder="12.00"
                            />
                            <p class="text-xs text-muted-foreground">
                                Numbers only (e.g. 12.50) so guests can order
                                this item.
                            </p>
                            <InputError :message="itemErrors.price" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`new-item-sale-price-${category.id}`">
                                Sale price
                            </Label>
                            <Input
                                :id="`new-item-sale-price-${category.id}`"
                                v-model="itemForms[category.id].sale_price"
                                placeholder="Leave blank for none"
                            />
                            <InputError :message="itemErrors.sale_price" />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="grid gap-2">
                                <Label :for="`new-item-sale-start-${category.id}`">
                                    Sale starts
                                </Label>
                                <Input
                                    :id="`new-item-sale-start-${category.id}`"
                                    v-model="itemForms[category.id].sale_starts_at"
                                    type="time"
                                />
                                <InputError :message="itemErrors.sale_starts_at" />
                            </div>
                            <div class="grid gap-2">
                                <Label :for="`new-item-sale-end-${category.id}`">
                                    Sale ends
                                </Label>
                                <Input
                                    :id="`new-item-sale-end-${category.id}`"
                                    v-model="itemForms[category.id].sale_ends_at"
                                    type="time"
                                />
                                <InputError :message="itemErrors.sale_ends_at" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`new-item-description-${category.id}`">
                                Description
                            </Label>
                            <Input
                                :id="`new-item-description-${category.id}`"
                                v-model="itemForms[category.id].description"
                                placeholder="Sourdough, avocado, chili"
                            />
                            <InputError :message="itemErrors.description" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`new-item-dietary-${category.id}`">
                                Dietary labels
                            </Label>
                            <Input
                                :id="`new-item-dietary-${category.id}`"
                                v-model="itemForms[category.id].dietary"
                                placeholder="V, GF"
                            />
                            <InputError :message="itemErrors.dietary" />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label :for="`new-item-allergens-${category.id}`">
                                Allergens
                            </Label>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="(label, key) in props.allergens"
                                    :key="key"
                                    type="button"
                                    class="rounded-full border px-2.5 py-1 text-xs transition"
                                    :class="
                                        itemForms[
                                            category.id
                                        ].allergens.includes(key)
                                            ? 'border-amber-500 bg-amber-500/15 font-medium'
                                            : 'border-input text-muted-foreground hover:bg-muted'
                                    "
                                    @click="
                                        toggleAllergen(
                                            itemForms[category.id].allergens,
                                            key,
                                        )
                                    "
                                >
                                    {{ label }}
                                </button>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Guests can filter these allergens out of the
                                public menu.
                            </p>
                            <InputError :message="itemErrors.allergens" />
                        </div>
                        <label
                            class="flex items-start gap-2 rounded-lg border p-3 sm:col-span-2"
                        >
                            <input
                                v-model="itemForms[category.id].is_featured"
                                type="checkbox"
                                class="mt-0.5 size-4 rounded border-input"
                            />
                            <span class="grid gap-0.5">
                                <span class="text-sm font-medium">
                                    Chef's pick
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    Show this item in the featured row of your
                                    public menu.
                                </span>
                            </span>
                        </label>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label :for="`new-item-image-${category.id}`">
                                Item image
                            </Label>
                            <input
                                :id="`new-item-image-${category.id}`"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="text-sm"
                                @change="
                                    onItemImage(
                                        $event,
                                        itemForms[category.id],
                                    )
                                "
                            />
                            <p class="text-xs text-muted-foreground">
                                Optional JPEG, PNG or WebP up to 2 MB.
                            </p>
                            <InputError :message="itemErrors.image" />
                        </div>
                        <div class="sm:col-span-2">
                            <Button
                                type="submit"
                                size="sm"
                                :disabled="addingItemId === category.id"
                            >
                                <Plus class="size-4" />
                                Add item
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <Dialog v-model:open="optionsOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        Options for {{ optionsItem?.name }}
                    </DialogTitle>
                    <DialogDescription>
                        Add choice groups such as Size, Milk or Extras. Guests
                        must choose from required groups before ordering.
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="optionsItem"
                    class="max-h-[60vh] space-y-4 overflow-y-auto pr-1"
                >
                    <div
                        v-for="group in optionsItem.option_groups"
                        :key="group.id"
                        class="space-y-2 rounded-lg border p-3"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-medium">
                                    {{ group.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        group.is_required
                                            ? 'Required'
                                            : 'Optional'
                                    }}
                                    ·
                                    {{
                                        group.allow_multiple
                                            ? 'Multiple choices'
                                            : 'Single choice'
                                    }}
                                </p>
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-destructive"
                                aria-label="Delete option group"
                                @click="removeGroup(optionsItem, group)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>

                        <ul
                            v-if="group.options.length"
                            class="divide-y rounded-md border text-sm"
                        >
                            <li
                                v-for="choice in group.options"
                                :key="choice.id"
                                class="flex items-center justify-between gap-2 px-3 py-1.5"
                            >
                                <span>
                                    {{ choice.name }}
                                    <span
                                        v-if="choice.price"
                                        class="text-xs text-muted-foreground"
                                    >
                                        +{{ choice.price }}
                                    </span>
                                </span>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive"
                                    :aria-label="`Delete ${choice.name}`"
                                    @click="removeChoice(optionsItem, group, choice)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </li>
                        </ul>
                        <p v-else class="text-xs text-muted-foreground">
                            No choices yet.
                        </p>

                        <form
                            class="flex flex-wrap gap-2"
                            @submit.prevent="addChoice(optionsItem, group)"
                        >
                            <Input
                                v-model="choiceDraft(group.id).name"
                                class="min-w-0 flex-1"
                                placeholder="Large"
                                maxlength="60"
                                required
                            />
                            <Input
                                v-model="choiceDraft(group.id).price"
                                class="w-24"
                                placeholder="+1.00"
                                maxlength="30"
                            />
                            <Button
                                type="submit"
                                size="sm"
                                :disabled="addingChoice === group.id"
                            >
                                Add
                            </Button>
                        </form>
                        <InputError :message="choiceErrors.name" />
                        <InputError :message="choiceErrors.price" />
                    </div>

                    <form
                        class="space-y-3 rounded-lg border p-3"
                        @submit.prevent="addGroup(optionsItem)"
                    >
                        <p class="text-sm font-medium">New option group</p>
                        <div class="grid gap-2">
                            <Input
                                v-model="groupDraft.name"
                                placeholder="Size"
                                maxlength="60"
                                required
                            />
                            <InputError :message="groupErrors.name" />
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="groupDraft.is_required"
                                type="checkbox"
                                class="size-4 rounded border-input"
                            />
                            Required
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="groupDraft.allow_multiple"
                                type="checkbox"
                                class="size-4 rounded border-input"
                            />
                            Allow multiple choices
                        </label>
                        <Button
                            type="submit"
                            size="sm"
                            :disabled="addingGroup"
                        >
                            <Plus class="size-4" />
                            Add group
                        </Button>
                    </form>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
