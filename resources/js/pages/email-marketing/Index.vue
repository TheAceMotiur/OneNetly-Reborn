<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    Download,
    Mail,
    Megaphone,
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
import { computed, ref } from 'vue';
import AutomationActivityPanel from '@/components/AutomationActivityPanel.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Campaign = {
    id: number;
    name: string;
    subject: string;
    body: string;
    status: string;
    sent_count: number;
    failed_count: number;
    sent_at: string | null;
    created_at: string | null;
};

type Subscriber = {
    id: number;
    email: string;
    source: string;
    created_at: string | null;
};

type Automation = {
    key: string;
    label: string;
    description: string;
    is_enabled: boolean;
    subject: string;
    body: string;
};

type SettingsData = {
    enabled: boolean;
    reply_to: string | null;
    footer: string | null;
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

const props = defineProps<{
    profile: { username: string; name: string };
    campaigns: Campaign[];
    subscribers: Subscriber[];
    automation: Automation;
    settings: SettingsData;
    automationActivity: AutomationRun[];
    stats: {
        subscribers: number;
        campaigns: number;
        emails_sent: number;
        sent_30_days: number;
        welcome_enabled: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Email Marketing', href: '/email-marketing' }],
    },
});

const tabs = [
    { key: 'overview', label: 'Overview', icon: Sparkles },
    { key: 'campaigns', label: 'Campaigns', icon: Megaphone },
    { key: 'audience', label: 'Audience', icon: Users },
    { key: 'automations', label: 'Automations', icon: Zap },
    { key: 'settings', label: 'Settings', icon: Settings },
];

const activeTab = ref('overview');

function path(suffix: string): string {
    return `/email-marketing${suffix}`;
}

const campaignForm = useForm({
    name: '',
    subject: '',
    body: '',
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
            `Send "${campaign.name}" to ${props.stats.subscribers} subscriber(s)? This cannot be undone.`,
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

const subscriberForm = useForm({ email: '' });

function addSubscriber(): void {
    subscriberForm.post(path('/subscribers'), {
        preserveScroll: true,
        onSuccess: () => subscriberForm.reset(),
    });
}

function syncSubscribers(): void {
    router.post(path('/subscribers/sync'), {}, { preserveScroll: true });
}

function removeSubscriber(subscriber: Subscriber): void {
    if (!confirm(`Remove ${subscriber.email}?`)) {
        return;
    }

    router.delete(path(`/subscribers/${subscriber.id}`), { preserveScroll: true });
}

const automationForm = useForm({
    is_enabled: props.automation.is_enabled,
    subject: props.automation.subject,
    body: props.automation.body,
});

function saveAutomation(): void {
    automationForm.put(path('/automations'), { preserveScroll: true });
}

const settingsForm = useForm({
    enabled: Boolean(props.settings.enabled),
    reply_to: props.settings.reply_to ?? '',
    footer: props.settings.footer ?? '',
});

function saveSettings(): void {
    settingsForm.put(path('/settings'), { preserveScroll: true });
}

const recentSubscribers = computed(() => props.subscribers.slice(0, 6));

const setupChecks = computed(() => [
    { label: 'Turn on email marketing', done: props.settings.enabled },
    { label: 'Keep the welcome email enabled', done: props.automation.is_enabled },
    { label: 'Grow your audience', done: props.stats.subscribers > 0 },
    { label: 'Send your first campaign', done: props.stats.campaigns > 0 },
]);

function statusClass(status: string): string {
    if (status === 'sent') {
        return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
    }

    if (status === 'failed') {
        return 'bg-destructive/10 text-destructive';
    }

    return 'bg-amber-500/10 text-amber-600 dark:text-amber-400';
}
</script>

<template>
    <Head title="Email Marketing" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <Heading
                    title="Email Marketing"
                    description="Campaigns, subscribers and automated welcome emails for your account"
                />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium"
                    :class="
                        props.settings.enabled
                            ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                            : 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400'
                    "
                >
                    <span
                        class="size-2 rounded-full"
                        :class="props.settings.enabled ? 'bg-emerald-500' : 'bg-amber-500'"
                    />
                    {{ props.settings.enabled ? 'Active' : 'Paused' }}
                </span>
            </div>
        </div>

        <div
            v-if="!props.settings.enabled"
            class="flex flex-wrap items-start gap-3 rounded-lg border border-amber-500/30 bg-amber-500/10 p-4"
        >
            <AlertTriangle class="mt-0.5 size-5 text-amber-600" />
            <div class="flex-1 space-y-1">
                <p class="text-sm font-medium">Email marketing is paused</p>
                <p class="text-sm text-muted-foreground">
                    Campaigns can still be prepared, but the welcome email will not send until
                    you enable email marketing in Settings.
                </p>
            </div>
            <Button size="sm" variant="outline" @click="activeTab = 'settings'">
                <Settings class="size-4" />
                Open settings
            </Button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Card>
                <CardContent class="space-y-1 p-4">
                    <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Subscribers
                    </p>
                    <p class="text-2xl font-semibold">{{ props.stats.subscribers }}</p>
                    <p class="text-xs text-muted-foreground">Opted-in addresses</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="space-y-1 p-4">
                    <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Campaigns
                    </p>
                    <p class="text-2xl font-semibold">{{ props.stats.campaigns }}</p>
                    <p class="text-xs text-muted-foreground">Created</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="space-y-1 p-4">
                    <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Emails sent
                    </p>
                    <p class="text-2xl font-semibold">{{ props.stats.emails_sent }}</p>
                    <p class="text-xs text-muted-foreground">Across all campaigns</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="space-y-1 p-4">
                    <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Last 30 days
                    </p>
                    <p class="text-2xl font-semibold">{{ props.stats.sent_30_days }}</p>
                    <p class="text-xs text-muted-foreground">Campaign emails sent</p>
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
                        The essentials for a healthy email list.
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
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-base">Newest subscribers</CardTitle>
                    <Button size="sm" variant="ghost" @click="activeTab = 'audience'">
                        Manage audience
                    </Button>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p
                        v-if="recentSubscribers.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No subscribers yet. Add addresses or import them from orders and bookings.
                    </p>
                    <div
                        v-for="subscriber in recentSubscribers"
                        :key="subscriber.id"
                        class="flex items-center justify-between gap-3 rounded-lg border p-3"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ subscriber.email }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ subscriber.source }} · {{ subscriber.created_at }}
                            </p>
                        </div>
                        <Mail class="size-4 shrink-0 text-muted-foreground" />
                    </div>
                </CardContent>
            </Card>

            <Card class="lg:col-span-2">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle class="text-base">Recent campaigns</CardTitle>
                    <Button size="sm" variant="ghost" @click="activeTab = 'campaigns'">
                        New campaign
                    </Button>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p v-if="props.campaigns.length === 0" class="text-sm text-muted-foreground">
                        No campaigns yet. Create one to reach your list.
                    </p>
                    <div
                        v-for="campaign in props.campaigns.slice(0, 5)"
                        :key="campaign.id"
                        class="flex flex-wrap items-center gap-3 rounded-lg border p-3"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ campaign.name }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ campaign.subject }}
                            </p>
                        </div>
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                            :class="statusClass(campaign.status)"
                        >
                            {{ campaign.status }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {{ campaign.sent_count }} sent
                        </span>
                    </div>
                </CardContent>
            </Card>

            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="text-base">How it works</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-lg border p-4">
                        <p class="text-sm font-medium">Collect addresses</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Your public page signup, orders and bookings all feed the audience.
                        </p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <p class="text-sm font-medium">Welcome automatically</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Every new subscriber can receive a branded welcome email.
                        </p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <p class="text-sm font-medium">Send campaigns</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Draft an email, review it, then send it to the whole list.
                        </p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <p class="text-sm font-medium">Stay compliant</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Every email includes a one-click unsubscribe link.
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
                        Save it as a draft, then send when you are ready.
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
                            <Label for="campaign-subject">Subject line</Label>
                            <Input
                                id="campaign-subject"
                                v-model="campaignForm.subject"
                                maxlength="150"
                                :placeholder="`This weekend at ${props.profile.name}`"
                                required
                            />
                            <InputError :message="campaignForm.errors.subject" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="campaign-body">Email body</Label>
                            <textarea
                                id="campaign-body"
                                v-model="campaignForm.body"
                                rows="8"
                                maxlength="10000"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                placeholder="Tell your subscribers what is new…"
                                required
                            />
                            <InputError :message="campaignForm.errors.body" />
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
                        Sends one email per subscriber, with an unsubscribe link.
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
                                <p class="text-sm text-muted-foreground">
                                    {{ campaign.subject }}
                                </p>
                            </div>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                :class="statusClass(campaign.status)"
                            >
                                {{ campaign.status }}
                            </span>
                        </div>

                        <p class="line-clamp-2 text-sm text-muted-foreground">
                            {{ campaign.body }}
                        </p>

                        <div class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                            <span>{{ campaign.sent_count }} sent</span>
                            <span>{{ campaign.failed_count }} failed</span>
                            <span v-if="campaign.sent_at">{{ campaign.sent_at }}</span>
                            <span v-else>Created {{ campaign.created_at }}</span>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button
                                size="sm"
                                :disabled="props.stats.subscribers === 0"
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

        <div v-else-if="activeTab === 'audience'" class="grid items-start gap-4 lg:grid-cols-[340px_1fr]">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Add a subscriber</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="addSubscriber">
                        <div class="grid gap-2">
                            <Label for="subscriber-email">Email address</Label>
                            <Input
                                id="subscriber-email"
                                v-model="subscriberForm.email"
                                type="email"
                                placeholder="guest@example.com"
                                required
                            />
                            <InputError :message="subscriberForm.errors.email" />
                        </div>
                        <Button type="submit" :disabled="subscriberForm.processing">
                            <Plus class="size-4" />
                            Add subscriber
                        </Button>
                    </form>

                    <p class="mt-4 text-sm text-muted-foreground">
                        New subscribers receive the welcome email when it is enabled.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row flex-wrap items-center justify-between gap-2">
                    <div>
                        <CardTitle class="text-base">Audience</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            {{ props.stats.subscribers }} subscribed address(es)
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button size="sm" variant="outline" @click="syncSubscribers">
                            <RefreshCw class="size-4" />
                            Import from orders & bookings
                        </Button>
                        <Button size="sm" variant="outline" as-child>
                            <a :href="path('/subscribers/export')">
                                <Download class="size-4" />
                                Export CSV
                            </a>
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p v-if="props.subscribers.length === 0" class="text-sm text-muted-foreground">
                        No subscribers yet. Add an address or import the ones already on your
                        orders and bookings.
                    </p>

                    <div
                        v-for="subscriber in props.subscribers"
                        :key="subscriber.id"
                        class="flex flex-wrap items-center gap-3 rounded-lg border p-3"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ subscriber.email }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ subscriber.source }} · joined {{ subscriber.created_at }}
                            </p>
                        </div>
                        <Button size="icon" variant="ghost" @click="removeSubscriber(subscriber)">
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div v-else-if="activeTab === 'automations'">
            <form class="space-y-4" @submit.prevent="saveAutomation">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-muted-foreground">
                        Placeholders: <code class="text-xs">{page}</code> and
                        <code class="text-xs">{email}</code>.
                    </p>
                    <Button type="submit" :disabled="automationForm.processing">
                        <Save class="size-4" />
                        {{ automationForm.processing ? 'Saving…' : 'Save automation' }}
                    </Button>
                </div>

                <Card>
                    <CardHeader class="space-y-1">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <CardTitle class="text-base">
                                    {{ props.automation.label }}
                                </CardTitle>
                                <p class="text-sm text-muted-foreground">
                                    {{ props.automation.description }}
                                </p>
                            </div>
                            <label class="flex items-center gap-2 text-sm">
                                <input
                                    v-model="automationForm.is_enabled"
                                    type="checkbox"
                                    class="size-4 rounded border-input"
                                />
                                On
                            </label>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <Label for="welcome-subject">Subject line</Label>
                            <Input
                                id="welcome-subject"
                                v-model="automationForm.subject"
                                maxlength="150"
                                required
                            />
                            <InputError :message="automationForm.errors.subject" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="welcome-body">Email body</Label>
                            <textarea
                                id="welcome-body"
                                v-model="automationForm.body"
                                rows="8"
                                maxlength="10000"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                required
                            />
                            <InputError :message="automationForm.errors.body" />
                        </div>
                    </CardContent>
                </Card>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="automationForm.processing">
                        <Save class="size-4" />
                        {{ automationForm.processing ? 'Saving…' : 'Save automation' }}
                    </Button>
                </div>
            </form>
        </div>

        <div v-else-if="activeTab === 'settings'" class="grid items-start gap-6 lg:grid-cols-[1.4fr_1fr]">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Marketing settings</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        These apply to campaigns and the welcome email from your account.
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
                                <span class="block text-sm font-medium">
                                    Enable email marketing
                                </span>
                                <span class="block text-sm text-muted-foreground">
                                    Allows the welcome automation to send to new subscribers.
                                </span>
                            </span>
                        </label>
                        <InputError :message="settingsForm.errors.enabled" />

                        <div class="grid gap-2">
                            <Label for="reply-to">Reply-to address</Label>
                            <Input
                                id="reply-to"
                                v-model="settingsForm.reply_to"
                                type="email"
                                placeholder="hello@example.com"
                            />
                            <p class="text-sm text-muted-foreground">
                                Optional. Replies from subscribers go to this address.
                            </p>
                            <InputError :message="settingsForm.errors.reply_to" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="footer">Footer note</Label>
                            <textarea
                                id="footer"
                                v-model="settingsForm.footer"
                                rows="3"
                                maxlength="1000"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                placeholder="Visit us at 12 Main Street, open daily 9am–10pm."
                            />
                            <p class="text-sm text-muted-foreground">
                                Added below the email body, above the unsubscribe link.
                            </p>
                            <InputError :message="settingsForm.errors.footer" />
                        </div>

                        <Button type="submit" :disabled="settingsForm.processing">
                            <Save class="size-4" />
                            {{ settingsForm.processing ? 'Saving…' : 'Save settings' }}
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <div class="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Sending email</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm text-muted-foreground">
                        <p>
                            Campaigns use the platform SMTP configuration in Admin → Settings →
                            Email and the sender name/address set there.
                        </p>
                        <p>
                            Sending runs immediately; large lists may take a moment. Failed
                            addresses are counted on the campaign.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Where subscribers come from</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm text-muted-foreground">
                        <p>The signup form on your public page.</p>
                        <p>Guest email addresses on orders and table bookings.</p>
                        <p>Addresses you add manually from the Audience tab.</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Compliance</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm text-muted-foreground">
                        <p>
                            Every campaign and welcome email includes an unsubscribe link that
                            removes the address immediately.
                        </p>
                        <p>Only email people who have opted in.</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
