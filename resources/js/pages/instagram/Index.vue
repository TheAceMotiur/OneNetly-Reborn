<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Camera,
    CheckCircle2,
    Inbox,
    MessageCircle,
    MessageSquare,
    Plus,
    RefreshCw,
    Save,
    Send,
    Settings,
    Sparkles,
    Trash2,
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

type Connection = {
    platform_enabled: boolean;
    enabled: boolean;
    username: string | null;
    account_id: string | null;
    has_token: boolean;
    webhook_url: string;
    graph_version: string;
};

type Automation = {
    key: string;
    label: string;
    description: string;
    is_enabled: boolean;
    message: string;
    keywords: string;
};

type KeywordRule = {
    id: number;
    name: string | null;
    keywords: string | null;
    message: string | null;
    is_enabled: boolean;
};

type Message = {
    id: number;
    igsid: string | null;
    name: string | null;
    direction: string;
    body: string | null;
    status: string;
    error: string | null;
    created_at: string | null;
    created_human: string | null;
};

type Activity = {
    id: number;
    name: string | null;
    igsid: string | null;
    channel: string;
    direction: string;
    body: string | null;
    status: string;
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

const props = defineProps<{
    profile: { username: string; name: string };
    connection: Connection;
    automations: Automation[];
    keywordRules: KeywordRule[];
    messages: Message[];
    activity: Activity[];
    automationActivity: AutomationRun[];
    stats: {
        conversations: number;
        received: number;
        sent: number;
        keyword_rules: number;
        automations: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Instagram', href: '/instagram' }],
    },
});

const tabs = [
    { key: 'overview', label: 'Overview', icon: Sparkles },
    { key: 'automations', label: 'Automations', icon: Zap },
    { key: 'inbox', label: 'Inbox', icon: Inbox },
    { key: 'settings', label: 'Settings', icon: Settings },
];

const activeTab = ref('overview');
const copied = ref(false);

function path(suffix: string): string {
    return `/instagram${suffix}`;
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
        Boolean(props.connection.account_id),
);

const settingsForm = useForm({
    enabled: Boolean(props.connection.enabled),
    username: props.connection.username ?? '',
    account_id: props.connection.account_id ?? '',
    access_token: '',
    remove_token: false,
});

function saveSettings(): void {
    settingsForm.put(path('/settings'), { preserveScroll: true });
}

function testConnection(): void {
    router.post(path('/settings/test'), {}, { preserveScroll: true });
}

const automationForm = useForm({
    automations: props.automations.map((automation) => ({
        key: automation.key,
        is_enabled: automation.is_enabled,
        message: automation.message,
        keywords: automation.keywords,
    })),
});

function saveAutomations(): void {
    automationForm.put(path('/automations'), { preserveScroll: true });
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

const threads = computed(() => {
    const grouped = new Map<
        string,
        { igsid: string; name: string | null; messages: Message[] }
    >();

    [...props.messages].reverse().forEach((message) => {
        if (!message.igsid) {
            return;
        }

        const thread = grouped.get(message.igsid) ?? {
            igsid: message.igsid,
            name: message.name,
            messages: [],
        };

        thread.messages.push(message);
        thread.name = thread.name ?? message.name;
        grouped.set(message.igsid, thread);
    });

    return [...grouped.values()].reverse();
});

const activeIgsid = ref<string | null>(threads.value[0]?.igsid ?? null);

const activeThread = computed(
    () => threads.value.find((thread) => thread.igsid === activeIgsid.value) ?? null,
);

const inboxForm = useForm({
    igsid: threads.value[0]?.igsid ?? '',
    name: threads.value[0]?.name ?? '',
    body: '',
});

function openThread(igsid: string, name: string | null): void {
    activeIgsid.value = igsid;
    inboxForm.igsid = igsid;
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
    { label: 'Connect your Instagram account', done: connected.value },
    { label: 'Enable a welcome or comment automation', done: props.stats.automations > 0 },
    { label: 'Add a keyword reply', done: props.stats.keyword_rules > 0 },
    { label: 'Receive a direct message', done: props.stats.conversations > 0 },
]);
</script>

<template>
    <Head title="Instagram Automation" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <Heading
                    title="Instagram Automation"
                    description="Direct message auto-replies, comment replies and keyword answers for your account"
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
                    {{
                        props.connection.platform_enabled
                            ? 'Connect your Instagram account to start replying'
                            : 'Instagram is disabled platform-wide'
                    }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{
                        props.connection.platform_enabled
                            ? 'Add your Instagram account ID and access token in Settings, then subscribe the webhook in Meta.'
                            : 'An administrator needs to enable Instagram in the platform settings before pages can send replies.'
                    }}
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
                        Conversations
                    </p>
                    <p class="text-2xl font-semibold">{{ props.stats.conversations }}</p>
                    <p class="text-xs text-muted-foreground">Direct message threads</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="space-y-1 p-4">
                    <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Received (30 days)
                    </p>
                    <p class="text-2xl font-semibold">{{ props.stats.received }}</p>
                    <p class="text-xs text-muted-foreground">Messages and comments</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="space-y-1 p-4">
                    <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Sent (30 days)
                    </p>
                    <p class="text-2xl font-semibold">{{ props.stats.sent }}</p>
                    <p class="text-xs text-muted-foreground">Replies and automations</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="space-y-1 p-4">
                    <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Automations
                    </p>
                    <p class="text-2xl font-semibold">{{ props.stats.automations }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ props.stats.keyword_rules }} keyword rule(s)
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
                        Everything needed to automate Instagram.
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
                <CardHeader>
                    <CardTitle class="text-base">Recent activity</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p v-if="props.activity.length === 0" class="text-sm text-muted-foreground">
                        No Instagram activity yet. Messages, comments and replies appear here.
                    </p>
                    <div
                        v-for="item in props.activity"
                        :key="item.id"
                        class="flex items-start gap-3 rounded-lg border p-3"
                    >
                        <component
                            :is="item.channel === 'comment' ? MessageSquare : MessageCircle"
                            class="mt-0.5 size-4 text-muted-foreground"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-medium">
                                    {{ item.name || item.igsid || 'Instagram user' }}
                                </p>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[11px] font-medium capitalize"
                                    :class="statusClass(item.status)"
                                >
                                    {{ item.status }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    {{ item.channel }} · {{ item.direction }}
                                </span>
                            </div>
                            <p class="mt-1 truncate text-sm text-muted-foreground">
                                {{ item.body || 'Empty message' }}
                            </p>
                        </div>
                        <span class="shrink-0 text-xs text-muted-foreground">
                            {{ item.created_human }}
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
                        <p class="text-sm font-medium">First-message welcome</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Greet every new Instagram conversation automatically.
                        </p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <p class="text-sm font-medium">Comment replies</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Thank people when they comment on your posts.
                        </p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <p class="text-sm font-medium">Keyword answers</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Answer menu, hours, booking or delivery questions instantly.
                        </p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <p class="text-sm font-medium">Shared inbox</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Read direct message threads and reply from OneNetly.
                        </p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <p class="text-sm font-medium">24-hour window</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Replies work while the customer's last message is under 24 hours old.
                        </p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <p class="text-sm font-medium">Page-level setup</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Each page connects its own Instagram account and token.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div v-else-if="activeTab === 'automations'" class="space-y-6">
            <form class="space-y-4" @submit.prevent="saveAutomations">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-muted-foreground">
                        Placeholders: <code class="text-xs">{customer}</code> and
                        <code class="text-xs">{account}</code>.
                    </p>
                    <Button type="submit" :disabled="automationForm.processing">
                        <Save class="size-4" />
                        {{ automationForm.processing ? 'Saving…' : 'Save automations' }}
                    </Button>
                </div>

                <div class="grid items-start gap-4 lg:grid-cols-2">
                    <Card
                        v-for="(automation, index) in automationForm.automations"
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
                            <div
                                v-if="
                                    automation.key === 'comment' ||
                                    automation.key === 'comment_dm'
                                "
                                class="grid gap-2"
                            >
                                <Label :for="`automation-keywords-${automation.key}`">
                                    Keywords (comma separated)
                                </Label>
                                <Input
                                    :id="`automation-keywords-${automation.key}`"
                                    v-model="automation.keywords"
                                    maxlength="500"
                                    placeholder="menu, price, link"
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        automation.key === 'comment_dm'
                                            ? 'Required. The DM is only sent when a comment contains one of these words.'
                                            : 'Leave blank to reply to every comment.'
                                    }}
                                </p>
                                <InputError
                                    :message="
                                        automationForm.errors[`automations.${index}.keywords`]
                                    "
                                />
                            </div>
                            <textarea
                                v-model="automation.message"
                                rows="4"
                                maxlength="1000"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                            />
                            <InputError
                                :message="automationForm.errors[`automations.${index}.message`]"
                            />
                        </CardContent>
                    </Card>
                </div>
            </form>

            <div class="grid items-start gap-4 lg:grid-cols-[340px_1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Add a keyword reply</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            The first matching rule wins. Separate keywords with commas.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <form class="space-y-4" @submit.prevent="addKeyword">
                            <div class="grid gap-2">
                                <Label for="instagram-keyword-name">Rule name</Label>
                                <Input
                                    id="instagram-keyword-name"
                                    v-model="keywordForm.name"
                                    maxlength="80"
                                    placeholder="Menu questions"
                                    required
                                />
                                <InputError :message="keywordForm.errors.name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="instagram-keywords">Keywords</Label>
                                <Input
                                    id="instagram-keywords"
                                    v-model="keywordForm.keywords"
                                    maxlength="500"
                                    placeholder="menu, food, eat"
                                    required
                                />
                                <InputError :message="keywordForm.errors.keywords" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="instagram-keyword-message">Reply</Label>
                                <textarea
                                    id="instagram-keyword-message"
                                    v-model="keywordForm.message"
                                    rows="4"
                                    maxlength="1000"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                    placeholder="Hi {customer}, here is our menu: {account}"
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
                        No Instagram conversations yet.
                    </p>
                    <button
                        v-for="thread in threads"
                        :key="thread.igsid"
                        type="button"
                        class="w-full rounded-lg border p-3 text-left transition hover:bg-muted/50"
                        :class="activeIgsid === thread.igsid ? 'border-primary/50 bg-muted/50' : ''"
                        @click="openThread(thread.igsid, thread.name)"
                    >
                        <p class="truncate text-sm font-medium">
                            {{ thread.name || thread.igsid }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ thread.messages[thread.messages.length - 1]?.body || 'Empty message' }}
                        </p>
                    </button>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ activeThread ? activeThread.name || activeThread.igsid : 'New message' }}
                    </CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Replies work within 24 hours of the customer's last Instagram message.
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
                                <p class="whitespace-pre-line">{{ message.body || 'Empty message' }}</p>
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
                                <Label for="instagram-igsid">Instagram-scoped ID</Label>
                                <Input
                                    id="instagram-igsid"
                                    v-model="inboxForm.igsid"
                                    placeholder="IGSID from a conversation"
                                    required
                                />
                                <InputError :message="inboxForm.errors.igsid" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="instagram-name">Contact name (optional)</Label>
                                <Input id="instagram-name" v-model="inboxForm.name" maxlength="120" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="instagram-body">Message</Label>
                            <textarea
                                id="instagram-body"
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

        <div v-else-if="activeTab === 'settings'" class="grid items-start gap-6 lg:grid-cols-[1.4fr_1fr]">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Instagram account</CardTitle>
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
                                <span class="block text-sm font-medium">Enable Instagram</span>
                                <span class="block text-sm text-muted-foreground">
                                    Turn automations, comment replies and the inbox on for this
                                    page.
                                </span>
                            </span>
                        </label>
                        <InputError :message="settingsForm.errors.enabled" />

                        <div class="grid gap-2">
                            <Label for="instagram-username">Username</Label>
                            <Input
                                id="instagram-username"
                                v-model="settingsForm.username"
                                placeholder="myrestaurant"
                            />
                            <p class="text-sm text-muted-foreground">
                                Used in automation messages and display only.
                            </p>
                            <InputError :message="settingsForm.errors.username" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="instagram-account-id">Instagram account ID</Label>
                            <Input
                                id="instagram-account-id"
                                v-model="settingsForm.account_id"
                                placeholder="17841400000000000"
                            />
                            <InputError :message="settingsForm.errors.account_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="instagram-token">Access token</Label>
                            <Input
                                id="instagram-token"
                                v-model="settingsForm.access_token"
                                type="password"
                                autocomplete="new-password"
                                :placeholder="
                                    props.connection.has_token
                                        ? 'A token is saved — leave blank to keep it'
                                        : 'Paste your Instagram access token'
                                "
                            />
                            <p class="text-sm text-muted-foreground">
                                Stored encrypted. Use a Page access token with Instagram messaging
                                permissions.
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
                            Add this callback URL in Meta → Instagram → Webhooks, then subscribe
                            to <code class="text-xs">messages</code> and
                            <code class="text-xs">comments</code>.
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
                            Verify token and app secret are managed in Admin → Settings →
                            Instagram.
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
                            <span>
                                {{ props.connection.platform_enabled ? 'Enabled' : 'Disabled' }}
                            </span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">Account</span>
                            <span>{{ props.connection.enabled ? 'Enabled' : 'Disabled' }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">Token</span>
                            <span>{{ props.connection.has_token ? 'Saved' : 'Missing' }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">Graph version</span>
                            <span>{{ props.connection.graph_version }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Requirements</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm text-muted-foreground">
                        <p>
                            The Instagram account must be a professional account linked to a
                            Facebook Page.
                        </p>
                        <p>
                            Permissions include
                            <code class="text-xs">instagram_basic</code>,
                            <code class="text-xs">instagram_manage_messages</code> and
                            <code class="text-xs">instagram_manage_comments</code>.
                        </p>
                        <p>Meta reviews these permissions before they work in production.</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
