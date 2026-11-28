<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Check, Star, Trash2 } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import RestaurantTabs from '@/components/RestaurantTabs.vue';
import { Button } from '@/components/ui/button';

type Review = {
    id: number;
    name: string;
    rating: number;
    comment: string | null;
    is_approved: boolean;
    created_at: string | null;
};

const props = defineProps<{
    restaurant: { username: string; name: string };
    reviews: Review[];
    stats: { total: number; pending: number; average: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Reviews', href: '/restaurant' }],
    },
});

function setApproved(review: Review, approved: boolean): void {
    router.patch(
        `/restaurant/${encodeURIComponent(props.restaurant.username)}/reviews/${review.id}`,
        { is_approved: approved },
        { preserveScroll: true },
    );
}

function remove(review: Review): void {
    if (!confirm(`Delete the review from ${review.name}?`)) {
        return;
    }

    router.delete(
        `/restaurant/${encodeURIComponent(props.restaurant.username)}/reviews/${review.id}`,
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="`${props.restaurant.name} reviews`" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <Heading
            :title="`${props.restaurant.name} reviews`"
            description="Approve guest reviews before they appear on the public menu"
        />

        <RestaurantTabs :restaurant="props.restaurant" active="reviews" />

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Published</p>
                <p class="mt-1 text-2xl font-semibold">{{ props.stats.total - props.stats.pending }}</p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Waiting</p>
                <p class="mt-1 text-2xl font-semibold">{{ props.stats.pending }}</p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-xs text-muted-foreground">Average rating</p>
                <p class="mt-1 text-2xl font-semibold">{{ props.stats.average || '—' }}</p>
            </div>
        </div>

        <div class="space-y-3">
            <article
                v-for="review in props.reviews"
                :key="review.id"
                class="rounded-xl border p-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="font-semibold">{{ review.name }}</p>
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                                :class="
                                    review.is_approved
                                        ? 'bg-emerald-500/10 text-emerald-700'
                                        : 'bg-amber-500/10 text-amber-700'
                                "
                            >
                                {{ review.is_approved ? 'Published' : 'Pending' }}
                            </span>
                        </div>
                        <div class="mt-1 flex items-center gap-1">
                            <Star
                                v-for="star in 5"
                                :key="star"
                                class="size-3.5"
                                :class="
                                    star <= review.rating
                                        ? 'fill-amber-400 text-amber-400'
                                        : 'text-muted-foreground/30'
                                "
                            />
                            <span class="ml-1 text-xs text-muted-foreground">
                                {{ review.created_at }}
                            </span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            v-if="!review.is_approved"
                            size="sm"
                            @click="setApproved(review, true)"
                        >
                            <Check class="size-4" />
                            Approve
                        </Button>
                        <Button
                            v-else
                            size="sm"
                            variant="outline"
                            @click="setApproved(review, false)"
                        >
                            Hide
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            class="text-destructive"
                            @click="remove(review)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
                <p v-if="review.comment" class="mt-3 text-sm leading-relaxed">
                    {{ review.comment }}
                </p>
            </article>

            <p
                v-if="props.reviews.length === 0"
                class="rounded-xl border border-dashed px-6 py-16 text-center text-sm text-muted-foreground"
            >
                No reviews yet. Guests can leave one on your public menu.
            </p>
        </div>
    </div>
</template>
