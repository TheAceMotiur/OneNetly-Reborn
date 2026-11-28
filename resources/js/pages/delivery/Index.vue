<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, Trash2, Truck } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import RestaurantTabs from '@/components/RestaurantTabs.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Zone = {
    id: number;
    name: string;
    postcodes: string;
    fee: string;
    min_order: string;
    is_active: boolean;
};

const props = defineProps<{
    zones: Zone[];
    restaurant: {
        username: string;
        name: string;
        currency: string;
        delivery_fee: string;
    };
    restaurants: { username: string; name: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Restaurant', href: '/restaurant' },
            { title: 'Delivery', href: '/restaurant' },
        ],
    },
});

const base = computed(
    () => `/restaurant/${encodeURIComponent(props.restaurant.username)}/delivery`,
);

const form = reactive({
    name: '',
    postcodes: '',
    fee: props.restaurant.delivery_fee,
    min_order: '0',
});

const errors = ref<Record<string, string>>({});
const saving = ref(false);

function addZone(): void {
    router.post(base.value, { ...form, is_active: true }, {
        preserveScroll: true,
        onStart: () => {
            saving.value = true;
        },
        onFinish: () => {
            saving.value = false;
        },
        onError: (value) => {
            errors.value = value;
        },
        onSuccess: () => {
            errors.value = {};
            form.name = '';
            form.postcodes = '';
            form.min_order = '0';
        },
    });
}

function toggleZone(zone: Zone): void {
    router.patch(
        `${base.value}/${zone.id}`,
        {
            name: zone.name,
            postcodes: zone.postcodes,
            fee: zone.fee,
            min_order: zone.min_order,
            is_active: !zone.is_active,
        },
        { preserveScroll: true },
    );
}

function removeZone(zone: Zone): void {
    if (!confirm(`Delete the ${zone.name} delivery zone?`)) {
        return;
    }

    router.delete(`${base.value}/${zone.id}`, { preserveScroll: true });
}

function switchRestaurant(event: Event): void {
    const username = (event.target as HTMLSelectElement).value;

    router.visit(
        `/restaurant/${encodeURIComponent(username)}/delivery`,
    );
}
</script>

<template>
    <Head title="Delivery zones" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="`${props.restaurant.name} delivery`"
                description="Postcode areas you deliver to, with their own fee and minimum order"
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

        <RestaurantTabs :restaurant="props.restaurant" active="delivery" />

        <p class="flex items-start gap-2 rounded-lg border bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
            <Truck class="mt-0.5 size-4 shrink-0" />
            <span>
                When at least one zone is active, delivery orders must use a
                postcode that matches a prefix below. The zone fee and minimum
                order replace the restaurant defaults for those orders.
            </span>
        </p>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Add a delivery zone</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="addZone">
                    <div class="grid gap-2">
                        <Label for="zone-name">Zone name</Label>
                        <Input id="zone-name" v-model="form.name" placeholder="City centre" />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="zone-postcodes">Postcodes or prefixes</Label>
                        <Input
                            id="zone-postcodes"
                            v-model="form.postcodes"
                            placeholder="1000, 101, 12"
                        />
                        <p class="text-xs text-muted-foreground">
                            Comma separated. A prefix matches every postcode
                            that starts with it.
                        </p>
                        <InputError :message="errors.postcodes" />
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="zone-fee">Delivery fee ({{ props.restaurant.currency }})</Label>
                            <Input id="zone-fee" v-model="form.fee" type="number" min="0" step="0.01" />
                            <InputError :message="errors.fee" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="zone-min">Minimum order ({{ props.restaurant.currency }})</Label>
                            <Input id="zone-min" v-model="form.min_order" type="number" min="0" step="0.01" />
                            <InputError :message="errors.min_order" />
                        </div>
                    </div>
                    <div class="flex items-end">
                        <Button type="submit" :disabled="saving">
                            <Plus class="size-4" />
                            Add zone
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <div v-if="props.zones.length" class="grid gap-3">
            <Card v-for="zone in props.zones" :key="zone.id">
                <CardContent class="flex flex-wrap items-center justify-between gap-4 pt-5">
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-semibold">{{ zone.name }}</p>
                            <Badge :variant="zone.is_active ? 'default' : 'secondary'">
                                {{ zone.is_active ? 'Active' : 'Paused' }}
                            </Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ zone.postcodes }}
                        </p>
                        <p class="text-sm">
                            Fee {{ zone.fee }} {{ props.restaurant.currency }} ·
                            minimum {{ zone.min_order }} {{ props.restaurant.currency }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button variant="outline" size="sm" @click="toggleZone(zone)">
                            {{ zone.is_active ? 'Pause' : 'Activate' }}
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-destructive"
                            aria-label="Delete zone"
                            @click="removeZone(zone)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <p
            v-else
            class="rounded-xl border border-dashed px-6 py-12 text-center text-sm text-muted-foreground"
        >
            No delivery zones yet. Add one to limit delivery to the areas you
            serve.
        </p>
    </div>
</template>
