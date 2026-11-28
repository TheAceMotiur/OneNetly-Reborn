<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Receipt } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';

defineProps<{
    purchases: Array<{
        id: number;
        plan_name: string;
        plan_type: string;
        amount: string;
        status: string;
        status_class: string;
        transaction_id: string | null;
        payment_date: string | null;
        access: string;
        created_at: string | null;
    }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Purchases', href: '/purchases' }],
    },
});
</script>

<template>
    <Head title="Purchases" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <Heading
            title="Purchases"
            description="Every plan payment made with your account"
        />

        <div
            v-if="purchases.length"
            class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-muted-foreground uppercase">
                    <tr>
                        <th class="px-4 py-3 font-medium">Plan</th>
                        <th class="px-4 py-3 font-medium">Amount</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Access</th>
                        <th class="px-4 py-3 font-medium">PayPal reference</th>
                        <th class="px-4 py-3 font-medium">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="purchase in purchases"
                        :key="purchase.id"
                        class="border-t border-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3 font-medium">
                            {{ purchase.plan_name }}
                            <p class="text-xs text-muted-foreground">
                                {{ purchase.plan_type }}
                            </p>
                        </td>
                        <td class="px-4 py-3">{{ purchase.amount }}</td>
                        <td class="px-4 py-3">
                            <span :class="purchase.status_class" class="font-medium">
                                {{ purchase.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ purchase.access }}
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-muted-foreground">
                            {{ purchase.transaction_id ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ purchase.payment_date ?? purchase.created_at }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-else
            class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-6 py-16 text-center text-muted-foreground"
        >
            <Receipt class="size-8" />
            <p class="font-medium text-foreground">No purchases yet</p>
            <p class="text-sm">
                Buy a plan from the pricing page to unlock custom domains and
                analytics.
            </p>
        </div>
    </div>
</template>
