<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type AdminPlan = {
    id: number;
    name: string;
    plan_type: string;
    tool: string | null;
    price: string;
    currency: string;
    features: string[];
    is_active: boolean;
    has_custom_domain: boolean;
    has_analytics: boolean;
    has_white_label: boolean;
    position: number;
    max_links: number | null;
    max_restaurants: number | null;
    duration_days: number | null;
    purchases_count: number;
};

const props = defineProps<{
    plans: AdminPlan[];
    planTypes: string[];
    tools: Record<string, string>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Admin', href: '/admin' }, { title: 'Plans', href: '/admin/plans' }] },
});

const editingId = ref<number | null>(null);
const processing = ref(false);

const form = reactive({
    name: '',
    plan_type: 'pro',
    tool: '',
    price: '9.99',
    currency: 'USD',
    features: '',
    is_active: true,
    has_custom_domain: true,
    has_analytics: true,
    has_white_label: true,
    position: 0,
    max_links: '' as string | number,
    max_restaurants: '' as string | number,
    duration_days: '' as string | number,
});

function reset(): void {
    editingId.value = null;
    form.name = '';
    form.plan_type = 'pro';
    form.tool = '';
    form.price = '9.99';
    form.currency = 'USD';
    form.features = '';
    form.is_active = true;
    form.has_custom_domain = true;
    form.has_analytics = true;
    form.has_white_label = true;
    form.position = 0;
    form.max_links = '';
    form.max_restaurants = '';
    form.duration_days = '';
}

function edit(plan: AdminPlan): void {
    editingId.value = plan.id;
    form.name = plan.name;
    form.plan_type = plan.plan_type;
    form.tool = plan.tool ?? '';
    form.price = plan.price;
    form.currency = plan.currency;
    form.features = plan.features.join('\n');
    form.is_active = plan.is_active;
    form.has_custom_domain = plan.has_custom_domain;
    form.has_analytics = plan.has_analytics;
    form.has_white_label = plan.has_white_label;
    form.position = plan.position;
    form.max_links = plan.max_links ?? '';
    form.max_restaurants = plan.max_restaurants ?? '';
    form.duration_days = plan.duration_days ?? '';
}

function payload() {
    return {
        name: form.name,
        plan_type: form.plan_type,
        tool: form.plan_type === 'tool' ? form.tool : null,
        price: form.price,
        currency: form.currency,
        features: form.features
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean),
        is_active: form.is_active,
        has_custom_domain: form.has_custom_domain,
        has_analytics: form.has_analytics,
        has_white_label:
            form.plan_type === 'tool' || form.plan_type === 'bundle'
                ? false
                : form.has_white_label,
        position: form.position,
        max_links:
            form.max_links === '' || form.max_links === null
                ? null
                : Number(form.max_links),
        max_restaurants:
            form.max_restaurants === '' || form.max_restaurants === null
                ? null
                : Number(form.max_restaurants),
        duration_days:
            form.duration_days === '' || form.duration_days === null
                ? null
                : Number(form.duration_days),
    };
}

function save(): void {
    processing.value = true;

    const options = {
        preserveScroll: true,
        onFinish: () => (processing.value = false),
        onSuccess: () => reset(),
    };

    if (editingId.value) {
        router.patch(`/admin/plans/${editingId.value}`, payload(), options);
    } else {
        router.post('/admin/plans', payload(), options);
    }
}

function remove(plan: AdminPlan): void {
    if (!confirm(`Delete the ${plan.name} plan?`)) {
        return;
    }

    router.delete(`/admin/plans/${plan.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Plans" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <Heading
            title="Subscription plans"
            description="Create plans, set prices and control which features each plan unlocks"
        />

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
            <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-muted-foreground uppercase">
                        <tr>
                            <th class="px-4 py-3 font-medium">Plan</th>
                            <th class="px-4 py-3 font-medium">Price</th>
                            <th class="px-4 py-3 font-medium">Features</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Purchases</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="plan in props.plans"
                            :key="plan.id"
                            class="border-t border-sidebar-border/70 dark:border-sidebar-border"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ plan.name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ plan.plan_type }}
                                    <span v-if="plan.tool">· {{ props.tools[plan.tool] }}</span>
                                    · position
                                    {{ plan.position }} ·
                                    {{ plan.max_links ?? '∞' }} links ·
                                    {{ plan.max_restaurants ?? '∞' }} restaurants ·
                                    {{
                                        plan.duration_days
                                            ? `${plan.duration_days} days`
                                            : 'lifetime'
                                    }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                {{ Number(plan.price) > 0 ? `${plan.price} ${plan.currency}` : 'Free' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex max-w-56 flex-wrap gap-1">
                                    <Badge
                                        v-if="plan.has_custom_domain"
                                        variant="secondary"
                                    >
                                        domain
                                    </Badge>
                                    <Badge v-if="plan.has_analytics" variant="secondary">
                                        analytics
                                    </Badge>
                                    <Badge
                                        v-if="
                                            plan.has_white_label &&
                                            plan.plan_type !== 'tool' &&
                                            plan.plan_type !== 'bundle'
                                        "
                                        variant="secondary"
                                    >
                                        no branding
                                    </Badge>
                                    <span
                                        v-if="
                                            !plan.has_custom_domain &&
                                            !plan.has_analytics &&
                                            !plan.has_white_label
                                        "
                                        class="text-xs text-muted-foreground"
                                    >
                                        basic
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <Badge :variant="plan.is_active ? 'default' : 'secondary'">
                                    {{ plan.is_active ? 'Active' : 'Hidden' }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ plan.purchases_count }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <Button variant="outline" size="sm" @click="edit(plan)">
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                        @click="remove(plan)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="props.plans.length === 0">
                            <td colspan="6" class="px-4 py-6 text-center text-muted-foreground">
                                No plans yet. Create the first one on the right.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Card class="h-fit">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-base">
                        {{ editingId ? 'Edit plan' : 'New plan' }}
                    </CardTitle>
                    <Button v-if="editingId" variant="ghost" size="sm" @click="reset">
                        Cancel
                    </Button>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="save">
                        <div class="grid gap-2">
                            <Label for="plan-name">Name</Label>
                            <Input id="plan-name" v-model="form.name" required />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-2">
                                <Label for="plan-type">Type</Label>
                                <select
                                    id="plan-type"
                                    v-model="form.plan_type"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                >
                                    <option
                                        v-for="type in props.planTypes"
                                        :key="type"
                                        :value="type"
                                    >
                                        {{ type }}
                                    </option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <Label for="plan-currency">Currency</Label>
                                <Input id="plan-currency" v-model="form.currency" maxlength="3" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div v-if="form.plan_type === 'tool'" class="grid gap-2">
                                <Label for="plan-tool">Tool</Label>
                                <select
                                    id="plan-tool"
                                    v-model="form.tool"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                    required
                                >
                                    <option value="" disabled>Choose a tool</option>
                                    <option
                                        v-for="(label, key) in props.tools"
                                        :key="key"
                                        :value="key"
                                    >
                                        {{ label }}
                                    </option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <Label for="plan-price">Price</Label>
                                <Input
                                    id="plan-price"
                                    v-model="form.price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    required
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="plan-position">Position</Label>
                                <Input
                                    id="plan-position"
                                    v-model.number="form.position"
                                    type="number"
                                    min="0"
                                />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="plan-max-links">Link limit (blank = unlimited)</Label>
                            <Input
                                id="plan-max-links"
                                v-model="form.max_links"
                                type="number"
                                min="1"
                                placeholder="Unlimited"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="plan-max-restaurants">
                                Restaurant limit (blank = unlimited)
                            </Label>
                            <Input
                                id="plan-max-restaurants"
                                v-model="form.max_restaurants"
                                type="number"
                                min="1"
                                placeholder="Unlimited"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="plan-duration">Access duration in days (blank = lifetime)</Label>
                            <Input
                                id="plan-duration"
                                v-model="form.duration_days"
                                type="number"
                                min="1"
                                max="3650"
                                placeholder="Lifetime"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="plan-features">Features (one per line)</Label>
                            <textarea
                                id="plan-features"
                                v-model="form.features"
                                rows="4"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                            />
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.is_active" type="checkbox" class="size-4 rounded border-input" />
                            Visible on pricing page
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="form.has_custom_domain"
                                type="checkbox"
                                class="size-4 rounded border-input"
                            />
                            Includes custom domain
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="form.has_analytics"
                                type="checkbox"
                                class="size-4 rounded border-input"
                            />
                            Includes analytics
                        </label>
                        <label
                            v-if="
                                form.plan_type !== 'tool' &&
                                form.plan_type !== 'bundle'
                            "
                            class="flex items-center gap-2 text-sm"
                        >
                            <input
                                v-model="form.has_white_label"
                                type="checkbox"
                                class="size-4 rounded border-input"
                            />
                            Removes platform branding (bio pages, resumes,
                            widgets)
                        </label>

                        <Button type="submit" class="w-full" :disabled="processing">
                            <Plus class="size-4" />
                            {{ editingId ? 'Save changes' : 'Create plan' }}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
