<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';

type AdminPurchase = {
    id: number;
    customer: string | null;
    email: string | null;
    plan_name: string | null;
    amount: string;
    status: string;
    transaction_id: string | null;
    created_at: string | null;
    payment_date: string | null;
    expires_at: string | null;
    access: string;
};

const props = defineProps<{
    purchases: {
        data: AdminPurchase[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
        from: number | null;
        to: number | null;
    };
    statuses: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Purchases', href: '/admin/purchases' },
        ],
    },
});

const drafts = ref<
    Record<number, { status: string; transaction_id: string; expires_at: string }>
>(
    Object.fromEntries(
        props.purchases.data.map((purchase) => [
            purchase.id,
            {
                status: purchase.status,
                transaction_id: purchase.transaction_id ?? '',
                expires_at: purchase.expires_at ?? '',
            },
        ]),
    ),
);

function save(purchase: AdminPurchase): void {
    router.patch(
        `/admin/purchases/${purchase.id}`,
        {
            status: drafts.value[purchase.id].status,
            paypal_transaction_id:
                drafts.value[purchase.id].transaction_id || null,
            expires_at: drafts.value[purchase.id].expires_at || null,
        },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Purchases" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <Heading
            title="Purchases"
            description="Confirm PayPal payments and manage each order's status"
        />

        <div class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-muted-foreground uppercase">
                    <tr>
                        <th class="px-4 py-3 font-medium">Customer</th>
                        <th class="px-4 py-3 font-medium">Plan</th>
                        <th class="px-4 py-3 font-medium">Amount</th>
                        <th class="px-4 py-3 font-medium">Created</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Access until</th>
                        <th class="px-4 py-3 font-medium">PayPal reference</th>
                        <th class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="purchase in props.purchases.data"
                        :key="purchase.id"
                        class="border-t border-sidebar-border/70 dark:border-sidebar-border align-top"
                    >
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ purchase.customer ?? 'Unknown' }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ purchase.email }}
                            </p>
                        </td>
                        <td class="px-4 py-3">{{ purchase.plan_name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ purchase.amount }}</td>
                        <td class="px-4 py-3 text-xs text-muted-foreground">
                            {{ purchase.created_at }}
                        </td>
                        <td class="px-4 py-3">
                            <select
                                v-model="drafts[purchase.id].status"
                                class="h-8 rounded-md border border-input bg-transparent px-2 text-xs"
                            >
                                <option
                                    v-for="status in props.statuses"
                                    :key="status"
                                    :value="status"
                                >
                                    {{ status }}
                                </option>
                            </select>
                        </td>
                        <td class="px-4 py-3">
                            <input
                                v-model="drafts[purchase.id].expires_at"
                                type="date"
                                class="h-8 rounded-md border border-input bg-transparent px-2 text-xs"
                            />
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ purchase.access }}
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <input
                                v-model="drafts[purchase.id].transaction_id"
                                placeholder="Transaction ID"
                                class="h-8 w-40 rounded-md border border-input bg-transparent px-2 font-mono text-xs"
                            />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button size="sm" @click="save(purchase)">Save</Button>
                        </td>
                    </tr>
                    <tr v-if="props.purchases.data.length === 0">
                        <td colspan="8" class="px-4 py-6 text-center text-muted-foreground">
                            No purchases yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between text-sm text-muted-foreground">
            <p>
                Showing {{ props.purchases.from ?? 0 }}–{{ props.purchases.to ?? 0 }}
                of {{ props.purchases.total }}
            </p>
            <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link v-if="props.purchases.prev_page_url" :href="props.purchases.prev_page_url">
                        Previous
                    </Link>
                    <span v-else>Previous</span>
                </Button>
                <span>Page {{ props.purchases.current_page }} of {{ props.purchases.last_page }}</span>
                <Button variant="outline" size="sm" as-child>
                    <Link v-if="props.purchases.next_page_url" :href="props.purchases.next_page_url">
                        Next
                    </Link>
                    <span v-else>Next</span>
                </Button>
            </div>
        </div>
    </div>
</template>
