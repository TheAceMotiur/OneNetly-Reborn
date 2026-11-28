<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, Trash2 } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes/admin';
import { index as profilesIndex, update } from '@/routes/admin/profiles';

type AdminProfile = {
    id: number;
    username: string;
    custom_domain: string | null;
    display_name: string | null;
    template: string;
    is_published: boolean;
    created_at: string;
    links_count: number;
    links_sum_clicks: number | null;
    user: {
        id: number;
        name: string;
        email: string;
    };
};

const props = defineProps<{
    profiles: {
        data: AdminProfile[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
        from: number | null;
        to: number | null;
    };
    stats: {
        total: number;
        published: number;
        customDomains: number;
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
                title: 'Pages',
                href: profilesIndex(),
            },
        ],
    },
});

function togglePublished(profile: AdminProfile): void {
    router.patch(
        update.url(profile.id),
        { is_published: !profile.is_published },
        { preserveScroll: true },
    );
}

function saveDomain(profile: AdminProfile, event: Event): void {
    const input = event.target as HTMLInputElement;
    const value = input.value.trim().toLowerCase();

    router.patch(
        update.url(profile.id),
        { custom_domain: value || null },
        { preserveScroll: true },
    );
}

function removeProfile(profile: AdminProfile): void {
    if (
        !confirm(
            `Delete the page @${profile.username} and all of its links? This cannot be undone.`,
        )
    ) {
        return;
    }

    router.delete(`/admin/profiles/${profile.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Pages" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <Heading
            title="Bio pages"
            description="Review, publish or remove the bio pages of every account"
        />

        <div class="flex flex-wrap gap-4 text-sm text-muted-foreground">
            <p>
                <span class="font-semibold text-foreground">
                    {{ props.stats.total }}
                </span>
                pages
            </p>
            <p>
                <span class="font-semibold text-foreground">
                    {{ props.stats.published }}
                </span>
                published
            </p>
            <p>
                <span class="font-semibold text-foreground">
                    {{ props.stats.customDomains }}
                </span>
                custom domains
            </p>
        </div>

        <div
            class="rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-sm">
                <thead
                    class="text-left text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">Page</th>
                        <th class="px-4 py-3 font-medium">Owner</th>
                        <th class="px-4 py-3 font-medium">Custom domain</th>
                        <th class="px-4 py-3 font-medium">Links</th>
                        <th class="px-4 py-3 font-medium">Clicks</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="profile in props.profiles.data"
                        :key="profile.id"
                        class="border-t border-sidebar-border/70 dark:border-sidebar-border"
                    >
                        <td class="px-4 py-3">
                            <a
                                :href="`/${profile.username}`"
                                target="_blank"
                                class="inline-flex items-center gap-1 font-medium underline-offset-4 hover:underline"
                            >
                                @{{ profile.username }}
                                <ExternalLink class="size-3" />
                            </a>
                            <p class="text-xs text-muted-foreground">
                                {{ profile.display_name ?? '—' }} ·
                                {{ profile.template }}
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <p>{{ profile.user.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ profile.user.email }}
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <input
                                :value="profile.custom_domain ?? ''"
                                placeholder="example.com"
                                class="h-8 w-40 rounded-md border border-input bg-transparent px-2 text-xs"
                                @change="saveDomain(profile, $event)"
                            />
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ profile.links_count }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ profile.links_sum_clicks ?? 0 }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="
                                    profile.is_published
                                        ? 'default'
                                        : 'secondary'
                                "
                            >
                                {{ profile.is_published ? 'Public' : 'Hidden' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :data-test="`toggle-published-${profile.id}`"
                                    @click="togglePublished(profile)"
                                >
                                    {{
                                        profile.is_published
                                            ? 'Hide'
                                            : 'Publish'
                                    }}
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive"
                                    :data-test="`delete-profile-${profile.id}`"
                                    @click="removeProfile(profile)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="props.profiles.data.length === 0">
                        <td
                            colspan="7"
                            class="px-4 py-6 text-center text-muted-foreground"
                        >
                            No bio pages have been created yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            class="flex items-center justify-between text-sm text-muted-foreground"
        >
            <p>
                Showing {{ props.profiles.from ?? 0 }}–{{
                    props.profiles.to ?? 0
                }}
                of {{ props.profiles.total }}
            </p>

            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    :disabled="!props.profiles.prev_page_url"
                >
                    <Link
                        v-if="props.profiles.prev_page_url"
                        :href="props.profiles.prev_page_url"
                    >
                        Previous
                    </Link>
                    <span v-else>Previous</span>
                </Button>

                <span>
                    Page {{ props.profiles.current_page }} of
                    {{ props.profiles.last_page }}
                </span>

                <Button
                    variant="outline"
                    size="sm"
                    as-child
                    :disabled="!props.profiles.next_page_url"
                >
                    <Link
                        v-if="props.profiles.next_page_url"
                        :href="props.profiles.next_page_url"
                    >
                        Next
                    </Link>
                    <span v-else>Next</span>
                </Button>
            </div>
        </div>
    </div>
</template>
