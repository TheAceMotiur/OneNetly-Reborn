<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes/admin';
import { index as usersIndex, update } from '@/routes/admin/users';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    email_verified_at: string | null;
    created_at: string;
    plan_id: number | null;
    plan_name: string | null;
};

const props = defineProps<{
    plans: Array<{ id: number; name: string; plan_type: string }>;
    users: {
        data: AdminUser[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
        from: number | null;
        to: number | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin',
                href: dashboard(),
            },
            {
                title: 'Users',
                href: usersIndex(),
            },
        ],
    },
});

const planDrafts = ref<Record<number, string>>(
    Object.fromEntries(
        props.users.data.map((user) => [
            user.id,
            user.plan_id ? String(user.plan_id) : '',
        ]),
    ),
);

function assignPlan(user: AdminUser): void {
    const planId = planDrafts.value[user.id];

    if (!planId) {
        return;
    }

    router.post(
        `/admin/users/${user.id}/plan`,
        { plan_id: Number(planId) },
        { preserveScroll: true },
    );
}

function toggleAdmin(user: AdminUser): void {
    router.patch(
        update.url(user.id),
        { is_admin: !user.is_admin },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Users" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <Heading
            title="Users"
            description="Grant or revoke administrator access for an account"
        />

        <div
            class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-sm">
                <thead
                    class="text-left text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Email</th>
                        <th class="px-4 py-3 font-medium">Verified</th>
                        <th class="px-4 py-3 font-medium">Plan</th>
                        <th class="px-4 py-3 font-medium">Role</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in users.data"
                        :key="user.id"
                        class="border-t border-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3">{{ user.name }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ user.email }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ user.email_verified_at ? 'Yes' : 'No' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <select
                                    v-model="planDrafts[user.id]"
                                    class="h-8 w-28 rounded-md border border-input bg-transparent px-2 text-xs"
                                >
                                    <option value="">No plan</option>
                                    <option
                                        v-for="plan in props.plans"
                                        :key="plan.id"
                                        :value="String(plan.id)"
                                    >
                                        {{ plan.name }}
                                    </option>
                                </select>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="!planDrafts[user.id]"
                                    @click="assignPlan(user)"
                                >
                                    Assign
                                </Button>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ user.plan_name ?? 'Free' }}
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="
                                    user.is_admin ? 'default' : 'secondary'
                                "
                            >
                                {{ user.is_admin ? 'Admin' : 'User' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button
                                variant="outline"
                                size="sm"
                                :data-test="`toggle-admin-${user.id}`"
                                @click="toggleAdmin(user)"
                            >
                                {{
                                    user.is_admin
                                        ? 'Revoke admin'
                                        : 'Make admin'
                                }}
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="users.data.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-6 text-center text-muted-foreground"
                        >
                            No users found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <p>
                Showing {{ users.from ?? 0 }}–{{ users.to ?? 0 }} of
                {{ users.total }}
            </p>

            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    :disabled="!users.prev_page_url"
                >
                    <Link
                        v-if="users.prev_page_url"
                        :href="users.prev_page_url"
                    >
                        Previous
                    </Link>
                    <span v-else>Previous</span>
                </Button>

                <span
                    >Page {{ users.current_page }} of
                    {{ users.last_page }}</span
                >

                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    :disabled="!users.next_page_url"
                >
                    <Link
                        v-if="users.next_page_url"
                        :href="users.next_page_url"
                    >
                        Next
                    </Link>
                    <span v-else>Next</span>
                </Button>
            </div>
        </div>
    </div>
</template>
