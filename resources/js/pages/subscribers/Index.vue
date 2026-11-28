<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Mail, Send, Trash2 } from '@lucide/vue';
import { reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import RestaurantTabs from '@/components/RestaurantTabs.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Subscriber = {
    id: number;
    email: string;
    source: string;
    created_at: string | null;
};

const props = defineProps<{
    restaurant: { username: string; name: string };
    subscribers: Subscriber[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Subscribers', href: '/restaurant' }],
    },
});

const broadcast = reactive({ subject: '', message: '' });
const broadcastErrors = ref<Record<string, string>>({});
const sendingBroadcast = ref(false);

function sendBroadcast(): void {
    if (
        !confirm(
            `Email all ${props.subscribers.length} subscriber(s)? This cannot be undone.`,
        )
    ) {
        return;
    }

    sendingBroadcast.value = true;
    broadcastErrors.value = {};

    router.post(
        `/subscribers/${encodeURIComponent(props.restaurant.username)}/email`,
        { ...broadcast },
        {
            preserveScroll: true,
            onSuccess: () => {
                broadcast.subject = '';
                broadcast.message = '';
            },
            onError: (errors) => {
                broadcastErrors.value = errors;
            },
            onFinish: () => {
                sendingBroadcast.value = false;
            },
        },
    );
}

function remove(subscriber: Subscriber): void {
    if (!confirm(`Remove ${subscriber.email}?`)) {
        return;
    }

    router.delete(
        `/subscribers/${encodeURIComponent(props.restaurant.username)}/${subscriber.id}`,
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="`${props.restaurant.name} subscribers`" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                :title="`${props.restaurant.name} subscribers`"
                description="Email addresses collected from your public page"
            />
            <Button variant="outline" as-child>
                <a :href="`/subscribers/${encodeURIComponent(props.restaurant.username)}/export`">
                    <Download class="size-4" />
                    Export CSV
                </a>
            </Button>
        </div>

        <RestaurantTabs :restaurant="props.restaurant" active="subscribers" />

        <Card>
            <CardHeader>
                <CardTitle class="text-base">Email subscribers</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="grid gap-3" @submit.prevent="sendBroadcast">
                    <div class="grid gap-2">
                        <Label for="broadcast-subject">Subject</Label>
                        <Input
                            id="broadcast-subject"
                            v-model="broadcast.subject"
                            maxlength="120"
                            placeholder="What's new this week"
                            required
                        />
                        <InputError :message="broadcastErrors.subject" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="broadcast-message">Message</Label>
                        <textarea
                            id="broadcast-message"
                            v-model="broadcast.message"
                            rows="5"
                            maxlength="5000"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:border-ring"
                            placeholder="Write a short update for your audience…"
                            required
                        />
                        <InputError :message="broadcastErrors.message" />
                    </div>
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <p class="text-xs text-muted-foreground">
                            Sends one email to each of your
                            {{ props.subscribers.length }} subscriber(s), with an
                            unsubscribe link.
                        </p>
                        <Button
                            type="submit"
                            :disabled="
                                sendingBroadcast ||
                                props.subscribers.length === 0
                            "
                        >
                            <Send class="size-4" />
                            {{
                                sendingBroadcast
                                    ? 'Sending…'
                                    : 'Send email'
                            }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <div class="overflow-hidden rounded-xl border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted/40 text-xs text-muted-foreground uppercase">
                    <tr>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">Joined</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="subscriber in props.subscribers" :key="subscriber.id">
                        <td class="px-4 py-3 font-medium">{{ subscriber.email }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ subscriber.source }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ subscriber.created_at }}</td>
                        <td class="px-4 py-3 text-right">
                            <Button
                                size="sm"
                                variant="ghost"
                                class="text-destructive"
                                @click="remove(subscriber)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div
                v-if="props.subscribers.length === 0"
                class="flex flex-col items-center gap-2 px-6 py-16 text-center text-sm text-muted-foreground"
            >
                <Mail class="size-7" />
                No subscribers yet. The signup form is on your public page.
            </div>
        </div>
    </div>
</template>
