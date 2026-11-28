<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    Inbox,
    Megaphone,
    MessageCircle,
    Plus,
    RefreshCw,
    Save,
    Send,
    Settings,
    Sparkles,
    Trash2,
    Users,
    Zap,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import AutomationActivityPanel from '@/components/AutomationActivityPanel.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Connection = {
    platform_enabled: boolean;
    enabled: boolean;
    display_number: string | null;
    phone_number_id: string | null;
    business_account_id: string | null;
    has_token: boolean;
    order_updates: boolean;
    reservation_updates: boolean;
    webhook_url: string;
    api_version: string;
};

type Automation = {
    key: string;
    label: string;
    description: string;
    is_enabled: boolean;
    message: string | null;
    template_name: string | null;
    language: string;
};

type Campaign = {
    id: number;
    name: string;
    message: string | null;
    template_name: string | null;
    status: string;
    sent_count: number;
    failed_count: number;
    sent_at: string | null;
    created_at: string | null;
};

type Contact = {
    id: number;
    phone: string;
    name: string | null;
    email: string | null;
    source: string;
    opted_in: boolean;
    last_messaged_at: string | null;
    created_at: string | null;
};

type Message = {
    id: number;
    phone: string;
    name: string | null;
    direction: string;
    type: string;
    body: string | null;
    template_name: string | null;
    status: string;
    error: string | null;
    created_at: string | null;
    created_human: string | null;
};

type AutomationRun = {
    id: number;
    label: string;
    status: string;
    trigger: string;
    contact: string | null;
    rule: string | null;
    detail: string | null;
    created_human: string | null;
};

type KeywordRule = {
    id: number;
    name: string | null;
    keywords: string | null;
    message: string | null;
    is_enabled: boolean;
};

const props = defineProps<{
    profile: { username: string; name: string };
    connection: Connection;
    automations: Automation[];
    campaigns: Campaign[];
    contacts: Contact[];
    messages: Message[];
    keywordRules: KeywordRule[];
    automationActivity: AutomationRun[];
    stats: {
        contacts: number;
        opted_in: number;
        sent: number;
        received: number;
        campaigns: number;
        conversations: number;
        keyword_rules: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'WhatsApp', href: '/whatsapp' }],
    },
});

const tabs = [
    { key: 'overview', label: 'Overview', icon: Sparkles },
    { key: 'campaigns', label: 'Campaigns', icon: Megaphone },
    { key: 'automations', label: 'Automations', icon: Zap },
    { key: 'inbox', label: 'Inbox', icon: Inbox },
    { key: 'contacts', label: 'Contacts', icon: Users },
    { key: 'settings', label: 'Settings', icon: Settings },
];

const activeTab = ref('overview');
const copied = ref(false);

function path(suffix: string): string {
    return `/whatsapp${suffix}`;
}

function copyWebhook(): void {
    navigator.clipboard?.writeText(props.connection.webhook_url);
    copied.value = true;
    window.setTimeout(() => (copied.value = false), 2000);
}

const connected = computed(
    () =>
        props.connection.platform_enabled &&
        props.connection.enabled &&
        props.connection.has_token &&
        Boolean(props.connection.phone_number_id),
);

const settingsForm = useForm({
    enabled: Boolean(props.connection.enabled),
    display_number: props.connection.display_number ?? '',
    phone_number_id: props.connection.phone_number_id ?? '',
    business_account_id: props.connection.business_account_id ?? '',
    access_token: '',
    remove_token: false,
    order_updates: Boolean(props.connection.order_updates),
    reservation_updates: Boolean(props.connection.reservation_updates),
});

function saveSettings(): void {
    settingsForm.put(path('/settings'), { preserveScroll: true });
}

function testConnection(): void {
    router.post(path('/settings/test'), {}, { preserveScroll: true });
}

const campaignForm = useForm({
    name: '',
    message: '',
    template_name: '',
    language: 'en_US',
});

function createCampaign(): void {
    campaignForm.post(path('/campaigns'), {
        preserveScroll: true,
        onSuccess: () => campaignForm.reset(),
    });
}

function sendCampaign(campaign: Campaign): void {
    if (
        !confirm(
            `Send "${campaign.name}" to ${props.stats.opted_in} opted-in contact(s)? This cannot be undone.`,
        )
    ) {
        return;
    }

    router.post(path(`/campaigns/${campaign.id}/send`), {}, { preserveScroll: true });
}

function deleteCampaign(campaign: Campaign): void {
    if (!confirm(`Delete "${campaign.name}"?`)) {
        return;
    }

    router.delete(path(`/campaigns/${campaign.id}`), { preserveScroll: true });
}

const automationsForm = useForm({
    automations: props.automations.map((automation) => ({
        key: automation.key,
        is_enabled: automation.is_enabled,
        message: automation.message ?? '',
        template_name: automation.template_name ?? '',
        language: automation.language ?? 'en_US',
    })),
});

function saveAutomations(): void {
    automationsForm.put(path('/automations'), { preserveScroll: true });
}

const keywordForm = useForm({
    name: '',
    keywords: '',
    message: '',
});

function addKeyword(): void {
    keywordForm.post(path('/automations/keywords'), {
        preserveScroll: true,
        onSuccess: () => keywordForm.reset(),
    });
}

function toggleKeyword(rule: KeywordRule): void {
    router.patch(
        path(`/automations/keywords/${rule.id}`),
        { is_enabled: !rule.is_enabled },
        { preserveScroll: true },
    );
}

function deleteKeyword(rule: KeywordRule): void {
    if (!confirm(`Delete "${rule.name ?? 'this keyword reply'}"?`)) {
        return;
    }

    router.delete(path(`/automations/keywords/${rule.id}`), {
        preserveScroll: true,
    });
}

const contactForm = useForm({
    name: '',
    phone: '',
    email: '',
});

function addContact(): void {
    contactForm.post(path('/contacts'), {
        preserveScroll: true,
        onSuccess: () => contactForm.reset(),
    });
}

function syncContacts(): void {
    router.post(path('/contacts/sync'), {}, { preserveScroll: true });
}

function toggleOptIn(contact: Contact): void {
    router.patch(
        path(`/contacts/${contact.id}`),
        { opted_in: !contact.opted_in },
        { preserveScroll: true },
    );
}

function deleteContact(contact: Contact): void {
    if (!confirm(`Remove ${contact.name || contact.phone} from your contacts?`)) {
        return;
    }

    router.delete(path(`/contacts/${contact.id}`), { preserveScroll: true });
}

const threads = computed(() => {
    const grouped = new Map<
        string,
        { phone: string; name: string | null; messages: Message[] }
    >();

    [...props.messages].reverse().forEach((message) => {
        const thread = grouped.get(message.phone) ?? {
            phone: message.phone,
            name: message.name,
            messages: [],
        };

        thread.messages.push(message);
        thread.name = thread.name ?? message.name;
        grouped.set(message.phone, thread);
    });

    return [...grouped.values()].reverse();
});

const activePhone = ref<string | null>(threads.value[0]?.phone ?? null);

const activeThread = computed(
    () => threads.value.find((thread) => thread.phone === activePhone.value) ?? null,
);

const inboxForm = useForm({
    phone: threads.value[0]?.phone ?? '',
    name: threads.value[0]?.name ?? '',
    body: '',
});

function openThread(phone: string, name: string | null): void {
    activePhone.value = phone;
    inboxForm.phone = phone;
    inboxForm.name = name ?? '';
    inboxForm.body = '';
}

function sendReply(): void {
    inboxForm.post(path('/inbox'), {
        preserveScroll: true,
        onSuccess: () => {
            inboxForm.body = '';
        },
    });
}

const recentMessages = computed(() => props.messages.slice(0, 8));

function statusClass(status: string): string {
    if (['sent', 'delivered', 'read', 'received'].includes(status)) {
        return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
    }

    if (status === 'failed') {
        return 'bg-destructive/10 text-destructive';
    }

    return 'bg-amber-500/10 text-amber-600 dark:text-amber-400';
}

const setupChecks = computed(() => [
    { label: 'Connect your WhatsApp number', done: connected.value },
    {
        label: 'Enable at least one automation',
        done: props.automations.some((automation) => automation.is_enabled),
    },
    { label: 'Add or import contacts', done: props.stats.contacts > 0 },
    { label: 'Send your first campaign', done: props.stats.campaigns > 0 },
]);

const contactImport = reactive({ busy: false });
</script>

<template>
    <Head title="WhatsApp Automation" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <Heading
                    title="WhatsApp Automation"
                    description="Marketing, automations, broadcasts and conversations for your account"
                />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium"
                    :class="
                        connected
                            ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                            : 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400'
                    "
                >
                    <span
                        class="size-2 rounded-full"
                        :class="connected ? 'bg-emerald-500' : 'bg-amber-500'"
                    />
                    {{ connected ? 'Connected' : 'Setup required' }}
                </span>
            </div>
        </div>

        <div
            v-if="!connected"
            class="flex flex-wrap items-start gap-3 rounded-lg border border-amber-500/30 bg-amber-500/10 p-4"
        >
            <AlertTriangle class="mt-0.5 size-5 text-amber-600" />
            <div class="flex-1 space-y-1">
                <p class="text-sm font-medium">
                    {{ props.connection.platform_enabled ? 'Connect WhatsApp to start sending' : 'WhatsApp is disabled platform-wide' }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{
                        props.connection.platform_enabled
                            ? 'Add your Meta WhatsApp Cloud API phone number ID and access token in Settings, then test the connection.'
                            : 'An administrator needs to enable WhatsApp in the platform settings before pages can send messages.'
                    }}
                </p>
            </div>
            <Button size="sm" variant="outline" @click="activeTab = 'settings'">
                <Settings class="size-4" />
                Open settings
            </Button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <Card>
                <CardContent class="space-y-1 p-4">
                    <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Contacts
                    </p>
                    <p class="text-2xl font-semibold">{{ props.stats.contacts }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ props.stats.opted_in }} opted in
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="space-y-1 p-4">
                    <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Sent (30 days)
                    </p>
                    <p class="text-2xl font-semibold">{{ props.stats.sent }}</p>
                    <p class="text-xs text-muted-foreground">Outbound messages</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="space-y-1 p-4">
                    <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Received (30 days)
                    </p>
                    <p class="text-2xl font-semibold">{{ props.stats.received }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ props.stats.conversations }} conversation(s)
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="space-y-1 p-4">
                    <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Campaigns
                    </p>
                    <p class="text-2xl font-semibold">{{ props.stats.campaigns }}</p>
                    <p class="text-xs text-muted-foreground">Broadcasts created</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="space-y-1 p-4">
                    <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Automations
                    </p>
                    <p class="text-2xl font-semibold">
                        {{ props.automations.filter((automation) => automation.is_enabled).length }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        of {{ props.automations.length }} enabled
                    </p>
                </CardContent>
            </Card>
        </div>

        <nav class="flex gap-1 overflow-x-auto rounded-lg border bg-muted/30 p-1">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="inline-flex shrink-0 items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition"
                :class="
                    activeTab === tab.key
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
                @click="activeTab = tab.key"
            >
                <component :is="tab.icon" class="size-4" />
                {{ tab.label }}
            </button>
        </nav>

        <div v-if="activeTab === 'overview'" class="grid items-start gap-6 lg:grid-cols-2">
            <AutomationActivityPanel
                class="lg:col-span-2"
                :items="props.automationActivity"
            />
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Go live checklist</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Everything you need to run WhatsApp from your account.
                    </p>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-for="check in setupChecks"
                        :key="check.label"
                        class="flex items-center gap-3 rounded-lg border p-3"
                    >
                        <CheckCircle2
                            class="size-5"
                            :class="check.done ? 'text-emerald-500' : 'text-muted-foreground/40'"
                        />
                        <span class="text-sm">{{ check.label }}</span>
                        <span
                            class="ml-auto text-xs font-medium"
                            :class="check.done ? 'text-emerald-600' : 'text-muted-foreground'"
                        >
                            {{ check.done ? 'Done' : 'Pending' }}
                        </span>
                    </div>

                    <div class="rounded-lg border bg-muted/30 p-3 text-sm text-muted-foreground">
                        <p class="font-medium text-foreground">How replies work</p>
                        <p class="mt-1">
                            Meta allows free-form replies for 24 hours after a customer messages
                            you. Outside that window, use an approved template in Campaigns or
                            Automations.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-base">Recent activity</CardTitle>
                    <Button size="sm" variant="ghost" @click="activeTab = 'inbox'">
                        Open inbox
                    </Button>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p v-if="recentMessages.length === 0" class="text-sm text-muted-foreground">
                        No WhatsApp messages yet. Messages you send and receive will appear here.
                    </p>
                    <div
                        v-for="message in recentMessages"
                        :key="message.id"
                        class="flex items-start gap-3 rounded-lg border p-3"
                    >
                        <MessageCircle class="mt-0.5 size-4 text-muted-foreground" />
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-medium">
                                    {{ message.name || message.phone }}
                                </p>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[11px] font-medium capitalize"
                                    :class="statusClass(message.status)"
                                >
                                    {{ message.status }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{ message.direction === 'inbound' ? 'in' : 'out' }}
                                </span>
                            </div>
                            <p class="mt-1 truncate text-sm text-muted-foreground">
                                {{ message.body || `[${message.type}]` }}
                            </p>
                        </div>
                        <span class="shrink-0 text-xs text-muted-foreground">
                            {{ message.created_human }}
                        </span>
                    </div>
                </CardContent>
            </Card>

            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="text-base">What you can automate</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div class="rounded-lg border p-4">
                        <p class="text-sm font-medium">Order updates</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Confirm new orders and tell guests when the status changes.
                        </p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <p class="text-sm font-medium">Booking updates</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Acknowledge table requests, confirmations and reminders.
                        </p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <p class="text-sm font-medium">Bulk campaigns</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Send offers or news to opted-in contacts in one click.
                        </p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <p class="text-sm font-medium">Shared inbox</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Read incoming messages and reply without leaving OneNetly.
                        </p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <p class="text-sm font-medium">Contact directory</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Collect numbers from orders and bookings, or add them manually.
                        </p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <p class="text-sm font-medium">Welcome message</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Greet first-time contacts automatically and keep them opted in.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div v-else-if="activeTab === 'campaigns'" class="grid items-start gap-6 lg:grid-cols-[1fr_1.4fr]">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">New campaign</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Use a template if your contacts have not messaged you in the last 24 hours.
                    </p>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="createCampaign">
                        <div class="grid gap-2">
                            <Label for="campaign-name">Campaign name</Label>
                            <Input
                                id="campaign-name"
                                v-model="campaignForm.name"
                                maxlength="80"
                                placeholder="Weekend special"
                                required
                            />
                            <InputError :message="campaignForm.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="campaign-message">Message</Label>
                            <textarea
                                id="campaign-message"
                                v-model="campaignForm.message"
                                rows="5"
                                maxlength="2000"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                placeholder="Hi {name}, enjoy 20% off this weekend at {restaurant}."
                            />
                            <p class="text-sm text-muted-foreground">
                                Use <code class="text-xs">{name}</code> and
                                <code class="text-xs">{restaurant}</code> as placeholders.
                            </p>
                            <InputError :message="campaignForm.errors.message" />
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="campaign-template">Or template name</Label>
                                <Input
                                    id="campaign-template"
                                    v-model="campaignForm.template_name"
                                    maxlength="80"
                                    placeholder="weekend_offer"
                                />
                                <InputError :message="campaignForm.errors.template_name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="campaign-language">Language</Label>
                                <Input
                                    id="campaign-language"
                                    v-model="campaignForm.language"
                                    maxlength="10"
                                    placeholder="en_US"
                                />
                                <InputError :message="campaignForm.errors.language" />
                            </div>
                        </div>

                        <Button type="submit" :disabled="campaignForm.processing">
                            <Plus class="size-4" />
                            {{ campaignForm.processing ? 'Saving…' : 'Save campaign' }}
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Campaigns</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Broadcasts send to every opted-in contact on your account.
                    </p>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p v-if="props.campaigns.length === 0" class="text-sm text-muted-foreground">
                        No campaigns yet. Create one on the left.
                    </p>

                    <div
                        v-for="campaign in props.campaigns"
                        :key="campaign.id"
                        class="space-y-3 rounded-lg border p-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="space-y-1">
                                <p class="text-sm font-medium">{{ campaign.name }}</p>
                                <p class="line-clamp-2 text-sm text-muted-foreground">
                                    {{ campaign.template_name ? `Template: ${campaign.template_name}` : campaign.message }}
                                </p>
                            </div>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                :class="statusClass(campaign.status)"
                            >
                                {{ campaign.status }}
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                            <span>{{ campaign.sent_count }} sent</span>
                            <span>{{ campaign.failed_count }} failed</span>
                            <span v-if="campaign.sent_at">{{ campaign.sent_at }}</span>
                            <span v-else>Created {{ campaign.created_at }}</span>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button
                                size="sm"
                                :disabled="props.stats.opted_in === 0"
                                @click="sendCampaign(campaign)"
                            >
                                <Send class="size-4" />
                                Send
                            </Button>
                            <Button size="sm" variant="outline" @click="deleteCampaign(campaign)">
                                <Trash2 class="size-4" />
                                Delete
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div v-else-if="activeTab === 'automations'">
            <form class="space-y-4" @submit.prevent="saveAutomations">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-muted-foreground">
                        Placeholders: <code class="text-xs">{customer}</code>,
                        <code class="text-xs">{restaurant}</code>,
                        <code class="text-xs">{reference}</code>,
                        <code class="text-xs">{status}</code>,
                        <code class="text-xs">{total}</code>,
                        <code class="text-xs">{track_url}</code>,
                        <code class="text-xs">{party}</code>,
                        <code class="text-xs">{date}</code>,
                        <code class="text-xs">{time}</code>
                    </p>
                    <Button type="submit" :disabled="automationsForm.processing">
                        <Save class="size-4" />
                        {{ automationsForm.processing ? 'Saving…' : 'Save automations' }}
                    </Button>
                </div>

                <div class="grid items-start gap-4 lg:grid-cols-2">
                    <Card
                        v-for="(automation, index) in automationsForm.automations"
                        :key="automation.key"
                    >
                        <CardHeader class="space-y-1">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <CardTitle class="text-base">
                                        {{ props.automations[index]?.label }}
                                    </CardTitle>
                                    <p class="text-sm text-muted-foreground">
                                        {{ props.automations[index]?.description }}
                                    </p>
                                </div>
                                <label class="flex items-center gap-2 text-sm">
                                    <input
                                        v-model="automation.is_enabled"
                                        type="checkbox"
                                        class="size-4 rounded border-input"
                                    />
                                    On
                                </label>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="grid gap-2">
                                <Label :for="`automation-${automation.key}`">Message</Label>
                                <textarea
                                    :id="`automation-${automation.key}`"
                                    v-model="automation.message"
                                    rows="3"
                                    maxlength="1000"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                />
                                <InputError
                                    :message="automationsForm.errors[`automations.${index}.message`]"
                                />
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label :for="`automation-template-${automation.key}`">
                                        Template name (optional)
                                    </Label>
                                    <Input
                                        :id="`automation-template-${automation.key}`"
                                        v-model="automation.template_name"
                                        maxlength="80"
                                        placeholder="Uses the message above when blank"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label :for="`automation-language-${automation.key}`">
                                        Template language
                                    </Label>
                                    <Input
                                        :id="`automation-language-${automation.key}`"
                                        v-model="automation.language"
                                        maxlength="10"
                                        placeholder="en_US"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="automationsForm.processing">
                        <Save class="size-4" />
                        {{ automationsForm.processing ? 'Saving…' : 'Save automations' }}
                    </Button>
                </div>
            </form>

            <div class="mt-6 grid items-start gap-4 lg:grid-cols-[340px_1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Add a keyword reply</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            Answers inbound messages before the welcome automation. The first
                            matching rule wins.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <form class="space-y-4" @submit.prevent="addKeyword">
                            <div class="grid gap-2">
                                <Label for="whatsapp-keyword-name">Rule name</Label>
                                <Input
                                    id="whatsapp-keyword-name"
                                    v-model="keywordForm.name"
                                    maxlength="80"
                                    placeholder="Menu questions"
                                    required
                                />
                                <InputError :message="keywordForm.errors.name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="whatsapp-keyword-words">Keywords</Label>
                                <Input
                                    id="whatsapp-keyword-words"
                                    v-model="keywordForm.keywords"
                                    maxlength="500"
                                    placeholder="menu, food, eat"
                                    required
                                />
                                <InputError :message="keywordForm.errors.keywords" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="whatsapp-keyword-message">Reply</Label>
                                <textarea
                                    id="whatsapp-keyword-message"
                                    v-model="keywordForm.message"
                                    rows="4"
                                    maxlength="1000"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                    placeholder="Hi {customer}, here is our menu: {restaurant}"
                                    required
                                />
                                <InputError :message="keywordForm.errors.message" />
                            </div>
                            <Button type="submit" :disabled="keywordForm.processing">
                                <Plus class="size-4" />
                                Add keyword reply
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Keyword replies</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            {{ props.keywordRules.length }} rule(s) configured
                        </p>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p
                            v-if="props.keywordRules.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            No keyword replies yet. Add one on the left.
                        </p>

                        <div
                            v-for="rule in props.keywordRules"
                            :key="rule.id"
                            class="space-y-2 rounded-lg border p-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-medium">{{ rule.name }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ rule.keywords }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        rule.is_enabled
                                            ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                    @click="toggleKeyword(rule)"
                                >
                                    {{ rule.is_enabled ? 'On' : 'Off' }}
                                </button>
                            </div>
                            <p class="text-sm text-muted-foreground">{{ rule.message }}</p>
                            <Button size="sm" variant="outline" @click="deleteKeyword(rule)">
                                <Trash2 class="size-4" />
                                Delete
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <div v-else-if="activeTab === 'inbox'" class="grid items-start gap-4 lg:grid-cols-[280px_1fr]">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Conversations</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2">
                    <p v-if="threads.length === 0" class="text-sm text-muted-foreground">
                        No conversations yet.
                    </p>
                    <button
                        v-for="thread in threads"
                        :key="thread.phone"
                        type="button"
                        class="w-full rounded-lg border p-3 text-left transition hover:bg-muted/50"
                        :class="activePhone === thread.phone ? 'border-primary/50 bg-muted/50' : ''"
                        @click="openThread(thread.phone, thread.name)"
                    >
                        <p class="truncate text-sm font-medium">
                            {{ thread.name || thread.phone }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ thread.messages[thread.messages.length - 1]?.body || 'Media message' }}
                        </p>
                    </button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ activeThread ? activeThread.name || activeThread.phone : 'New message' }}
                    </CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Free-form replies work within 24 hours of the customer's last message.
                    </p>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="max-h-[420px] space-y-3 overflow-y-auto rounded-lg border bg-muted/20 p-3">
                        <p
                            v-if="!activeThread || activeThread.messages.length === 0"
                            class="py-8 text-center text-sm text-muted-foreground"
                        >
                            Select a conversation or start a new message below.
                        </p>
                        <div
                            v-for="message in activeThread?.messages ?? []"
                            :key="message.id"
                            class="flex"
                            :class="message.direction === 'outbound' ? 'justify-end' : 'justify-start'"
                        >
                            <div
                                class="max-w-[80%] space-y-1 rounded-lg px-3 py-2 text-sm"
                                :class="
                                    message.direction === 'outbound'
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-background'
                                "
                            >
                                <p class="whitespace-pre-line">
                                    {{ message.body || `[${message.type}]` }}
                                </p>
                                <p
                                    class="text-[11px] opacity-70"
                                    :class="message.direction === 'outbound' ? 'text-right' : ''"
                                >
                                    {{ message.created_human }}
                                    <span v-if="message.direction === 'outbound'">
                                        · {{ message.status }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <form class="space-y-3" @submit.prevent="sendReply">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="inbox-phone">Phone number</Label>
                                <Input
                                    id="inbox-phone"
                                    v-model="inboxForm.phone"
                                    placeholder="15551234567"
                                    required
                                />
                                <InputError :message="inboxForm.errors.phone" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="inbox-name">Contact name (optional)</Label>
                                <Input id="inbox-name" v-model="inboxForm.name" maxlength="80" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="inbox-body">Message</Label>
                            <textarea
                                id="inbox-body"
                                v-model="inboxForm.body"
                                rows="3"
                                maxlength="4000"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                placeholder="Type your reply…"
                                required
                            />
                            <InputError :message="inboxForm.errors.body" />
                        </div>

                        <Button type="submit" :disabled="inboxForm.processing">
                            <Send class="size-4" />
                            {{ inboxForm.processing ? 'Sending…' : 'Send message' }}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>

        <div v-else-if="activeTab === 'contacts'" class="grid items-start gap-4 lg:grid-cols-[340px_1fr]">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Add a contact</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="addContact">
                        <div class="grid gap-2">
                            <Label for="contact-name">Name</Label>
                            <Input id="contact-name" v-model="contactForm.name" maxlength="80" />
                            <InputError :message="contactForm.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="contact-phone">WhatsApp number</Label>
                            <Input
                                id="contact-phone"
                                v-model="contactForm.phone"
                                placeholder="15551234567"
                                required
                            />
                            <p class="text-sm text-muted-foreground">
                                Include the country code.
                            </p>
                            <InputError :message="contactForm.errors.phone" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="contact-email">Email (optional)</Label>
                            <Input id="contact-email" v-model="contactForm.email" type="email" />
                            <InputError :message="contactForm.errors.email" />
                        </div>
                        <Button type="submit" :disabled="contactForm.processing">
                            <Plus class="size-4" />
                            Add contact
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row flex-wrap items-center justify-between gap-2">
                    <div>
                        <CardTitle class="text-base">Contacts</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            {{ props.stats.opted_in }} of {{ props.stats.contacts }} opted in
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="contactImport.busy"
                        @click="syncContacts"
                    >
                        <RefreshCw class="size-4" />
                        Import from orders & bookings
                    </Button>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p v-if="props.contacts.length === 0" class="text-sm text-muted-foreground">
                        No contacts yet. Import the numbers already on your orders and bookings.
                    </p>

                    <div
                        v-for="contact in props.contacts"
                        :key="contact.id"
                        class="flex flex-wrap items-center gap-3 rounded-lg border p-3"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ contact.name || 'Unnamed contact' }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ contact.phone }} · {{ contact.source }}
                                <span v-if="contact.last_messaged_at">
                                    · last messaged {{ contact.last_messaged_at }}
                                </span>
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="
                                contact.opted_in
                                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                    : 'bg-muted text-muted-foreground'
                            "
                            @click="toggleOptIn(contact)"
                        >
                            {{ contact.opted_in ? 'Opted in' : 'Opted out' }}
                        </button>
                        <Button
                            size="icon"
                            variant="ghost"
                            @click="deleteContact(contact)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div v-else-if="activeTab === 'settings'" class="grid items-start gap-6 lg:grid-cols-[1.4fr_1fr]">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">WhatsApp Cloud API</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Credentials are stored encrypted on your account.
                    </p>
                </CardHeader>
                <CardContent>
                    <form class="space-y-5" @submit.prevent="saveSettings">
                        <label class="flex items-start gap-3 rounded-lg border p-4">
                            <input
                                v-model="settingsForm.enabled"
                                type="checkbox"
                                class="mt-0.5 size-4 rounded border-input"
                            />
                            <span class="space-y-1">
                                <span class="block text-sm font-medium">Enable WhatsApp</span>
                                <span class="block text-sm text-muted-foreground">
                                    Turn automations, campaigns and inbox sending on for your account.
                                </span>
                            </span>
                        </label>
                        <InputError :message="settingsForm.errors.enabled" />

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="display-number">Display number</Label>
                                <Input
                                    id="display-number"
                                    v-model="settingsForm.display_number"
                                    placeholder="+1 555 123 4567"
                                />
                                <InputError :message="settingsForm.errors.display_number" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="phone-number-id">Phone number ID</Label>
                                <Input
                                    id="phone-number-id"
                                    v-model="settingsForm.phone_number_id"
                                    placeholder="1234567890"
                                />
                                <InputError :message="settingsForm.errors.phone_number_id" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="business-account-id">WhatsApp Business Account ID</Label>
                            <Input
                                id="business-account-id"
                                v-model="settingsForm.business_account_id"
                                placeholder="Optional"
                            />
                            <InputError :message="settingsForm.errors.business_account_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="access-token">Access token</Label>
                            <Input
                                id="access-token"
                                v-model="settingsForm.access_token"
                                type="password"
                                autocomplete="new-password"
                                :placeholder="
                                    props.connection.has_token
                                        ? 'A token is saved — leave blank to keep it'
                                        : 'Paste your Meta access token'
                                "
                            />
                            <p class="text-sm text-muted-foreground">
                                Stored encrypted. Create a permanent token in Meta Business
                                Settings → WhatsApp → API setup.
                            </p>
                            <InputError :message="settingsForm.errors.access_token" />
                        </div>

                        <label
                            v-if="props.connection.has_token"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input
                                v-model="settingsForm.remove_token"
                                type="checkbox"
                                class="size-4 rounded border-input"
                            />
                            Remove the saved token
                        </label>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="flex items-start gap-3 rounded-lg border p-4">
                                <input
                                    v-model="settingsForm.order_updates"
                                    type="checkbox"
                                    class="mt-0.5 size-4 rounded border-input"
                                />
                                <span class="text-sm">
                                    Order updates
                                    <span class="block text-muted-foreground">
                                        Send order received and status messages.
                                    </span>
                                </span>
                            </label>
                            <label class="flex items-start gap-3 rounded-lg border p-4">
                                <input
                                    v-model="settingsForm.reservation_updates"
                                    type="checkbox"
                                    class="mt-0.5 size-4 rounded border-input"
                                />
                                <span class="text-sm">
                                    Booking updates
                                    <span class="block text-muted-foreground">
                                        Send booking received, status and reminders.
                                    </span>
                                </span>
                            </label>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button type="submit" :disabled="settingsForm.processing">
                                <Save class="size-4" />
                                {{ settingsForm.processing ? 'Saving…' : 'Save settings' }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="!props.connection.has_token"
                                @click="testConnection"
                            >
                                <RefreshCw class="size-4" />
                                Test connection
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <div class="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Webhook</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p class="text-sm text-muted-foreground">
                            Add this callback URL in Meta → WhatsApp → Configuration, then
                            subscribe to the <code class="text-xs">messages</code> field.
                        </p>
                        <div class="flex items-center gap-2">
                            <code class="min-w-0 flex-1 truncate rounded-md border bg-muted/40 px-3 py-2 text-xs">
                                {{ props.connection.webhook_url }}
                            </code>
                            <Button size="sm" variant="outline" @click="copyWebhook">
                                {{ copied ? 'Copied' : 'Copy' }}
                            </Button>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Verify token and app secret are managed by an administrator in
                            Admin → Settings → WhatsApp.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Connection details</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">Platform</span>
                            <span>{{ props.connection.platform_enabled ? 'Enabled' : 'Disabled' }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">Page</span>
                            <span>{{ props.connection.enabled ? 'Enabled' : 'Disabled' }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">Token</span>
                            <span>{{ props.connection.has_token ? 'Saved' : 'Missing' }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">API version</span>
                            <span>{{ props.connection.api_version }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Need a number?</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm text-muted-foreground">
                        <p>
                            Use a phone number that is not already on the WhatsApp app, or migrate
                            it to the Cloud API in Meta Business Settings.
                        </p>
                        <p>
                            Meta gives test numbers for development before you connect a real
                            business number.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
