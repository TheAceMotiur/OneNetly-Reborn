<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Mail, MailOpen, Trash2 } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Message = {
    id: number;
    name: string;
    email: string;
    message: string;
    read: boolean;
    page: string;
    page_url: string;
    created_human: string | null;
    created_at: string | null;
    read_url: string;
    delete_url: string;
};

const props = defineProps<{
    messages: Message[];
    unread: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Inbox', href: '/inbox' }],
    },
});

function toggleRead(message: Message): void {
    router.patch(message.read_url, {}, { preserveScroll: true });
}

function destroy(message: Message): void {
    if (!confirm(`Delete the message from ${message.name}?`)) {
        return;
    }

    router.delete(message.delete_url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Inbox" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <Heading
            title="Inbox"
            :description="
                props.unread > 0
                    ? `${props.unread} unread contact message${props.unread === 1 ? '' : 's'}`
                    : 'Contact messages from your public pages'
            "
        />

        <div v-if="props.messages.length" class="grid gap-3">
            <Card
                v-for="message in props.messages"
                :key="message.id"
                :class="message.read ? 'opacity-80' : 'border-primary/30'"
            >
                <CardContent class="space-y-3 pt-5">
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold">
                                    {{ message.name }}
                                </p>
                                <span
                                    v-if="!message.read"
                                    class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary"
                                >
                                    New
                                </span>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                <a
                                    :href="`mailto:${message.email}`"
                                    class="hover:underline"
                                    >{{ message.email }}</a
                                >
                                · to
                                <a
                                    :href="message.page_url"
                                    target="_blank"
                                    class="hover:underline"
                                    >{{ message.page }}</a
                                >
                                · {{ message.created_human }}
                            </p>
                        </div>
                        <div class="flex gap-1.5">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="toggleRead(message)"
                            >
                                <MailOpen v-if="!message.read" class="size-4" />
                                <Mail v-else class="size-4" />
                                {{ message.read ? 'Mark unread' : 'Mark read' }}
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-destructive"
                                aria-label="Delete message"
                                @click="destroy(message)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>

                    <p class="whitespace-pre-line text-sm leading-relaxed">
                        {{ message.message }}
                    </p>
                    <p class="text-[11px] text-muted-foreground">
                        {{ message.created_at }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div
            v-else
            class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-6 py-16 text-center text-muted-foreground"
        >
            <Mail class="size-8" />
            <p class="font-medium text-foreground">No messages yet</p>
            <p class="max-w-sm text-sm">
                Turn on the contact form on your page and messages will appear
                here instead of only being emailed to you.
            </p>
        </div>
    </div>
</template>
