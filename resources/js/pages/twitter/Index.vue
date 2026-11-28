<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarDays,
    CheckCircle2,
    Clock,
    ExternalLink,
    MessageCircle,
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
    user_id: string | null;
    has_access_token: boolean;
    has_refresh_token: boolean;
    token_expires_at: string | null;
};

type Automation = {
    key: string;
    label: string;
    description: string;
    is_enabled: boolean;
    message: string;
};

type KeywordRule = {
    id: number;
    name: string | null;
    keywords: string | null;
    message: string | null;
    is_enabled: boolean;
};

type Post = {
    id: number;
    body: string;
    status: string;
    scheduled_at: string | null;
    published_at: string | null;
    external_id: string | null;
    error: string | null;
    created_human: string | null;
};

type Mention = {
    id: number;
    tweet_id: string;
    author_username: string | null;
    body: string | null;
    replied: boolean;
    reply_tweet_id: string | null;
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
    posts: Post[];
    mentions: Mention[];
    automationActivity: AutomationRun[];
    stats: {
        posts: number;
        scheduled: number;
        mentions: number;
        replied: number;
        keyword_rules: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Twitter', href: '/twitter' }],
    },
});

const tabs = [
    { key: 'overview', label: 'Overview', icon: Sparkles },
    { key: 'composer', label: 'Composer', icon: Send },
    { key: 'automations', label: 'Automations', icon: Zap },
    { key: 'mentions', label: 'Mentions', icon: MessageCircle },
    { key: 'settings', label: 'Settings', icon: Settings },
];

const activeTab = ref('overview');
const scheduleAt = ref<Record<number, string>>({});

function path(suffix: string): string {
    return `/twitter${suffix}`;
}

const connected = computed(
    () =>
        props.connection.platform_enabled &&
        props.connection.enabled &&
        props.connection.has_access_token &&
        Boolean(props.connection.user_id),
);

const settingsForm = useForm({
    enabled: Boolean(props.connection.enabled),
    username: props.connection.username ?? '',
    user_id: props.connection.user_id ?? '',
    access_token: '',
    refresh_token: '',
    remove_token: false,
});

function saveSettings(): void {
    settingsForm.put(path('/settings'), { preserveScroll: true });
}

function testConnection(): void {
    router.post(path('/settings/test'), {}, { preserveScroll: true });
}

const composer = useForm({
    body: '',
    publish_now: false,
});

const characterCount = computed(() => composer.body.length);
const overLimit = computed(() => characterCount.value > 280);

function saveDraft(): void {
    composer.publish_now = false;
    composer.post(path('/posts'), {
        preserveScroll: true,
        onSuccess: () => composer.reset(),
    });
}

function publishNow(): void {
    composer.publish_now = true;
    composer.post(path('/posts'), {
        preserveScroll: true,
        onSuccess: () => composer.reset(),
    });
}

function publishPost(post: Post): void {
    router.post(path(`/posts/${post.id}/publish`), {}, { preserveScroll: true });
}

function schedulePost(post: Post): void {
    router.post(
        path(`/posts/${post.id}/schedule`),
        { scheduled_at: scheduleAt.value[post.id] ?? '' },
        { preserveScroll: true },
    );
}

function deletePost(post: Post): void {
    if (!confirm('Delete this post?')) {
        return;
    }

    router.delete(path(`/posts/${post.id}`), { preserveScroll: true });
}

const automationForm = useForm({
    automations: props.automations.map((automation) => ({
        key: automation.key,
        is_enabled: automation.is_enabled,
        message: automation.message,
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

function syncMentions(): void {
    router.post(path('/sync'), {}, { preserveScroll: true });
}

function statusClass(status: string): string {
    if (status === 'published') {
        return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
    }

    if (status === 'failed') {
        return 'bg-destructive/10 text-destructive';
    }

    if (status === 'scheduled') {
        return 'bg-sky-500/10 text-sky-600 dark:text-sky-400';
    }

    return 'bg-amber-500/10 text-amber-600 dark:text-amber-400';
}

function tweetUrl(mention: Mention): string {
    if (mention.author_username) {
        return `https://x.com/${mention.author_username}/status/${mention.tweet_id}`;
    }

    return `https://x.com/i/status/${mention.tweet_id}`;
}

const setupChecks = computed(() => [
    { label: 'Connect your X account', done: connected.value },
    { label: 'Keep the mention auto-reply enabled', done: props.stats.keyword_rules >= 0 },
    { label: 'Sync your mentions', done: props.stats.mentions > 0 },
    { label: 'Publish your first tweet', done: props.stats.posts > 0 },
]);
</script>

<template>
    <Head title="Twitter Automation" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <Heading
                    title="Twitter Automation"
                    description="Publish tweets, schedule posts and reply to mentions automatically"
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

                <Button size="sm" variant="outline" @click="syncMentions">
                    <RefreshCw class="size-4" />
                    Sync mentions
                </Button>

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
                            ? 'Connect your X account to start posting'
                            : 'Twitter is disabled platform-wide'
                    }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{
                        props.connection.platform_enabled
                            ? 'Add your user ID and an OAuth 2.0 user access token in Settings. Mentions are polled every five minutes.'
                            : 'An administrator needs to enable Twitter in the platform settings before pages can post or reply.'
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
                        Published
                    </p>
                    <p class="text-2xl font-semibold">{{ props.stats.posts }}</p>
                    <p class="text-xs text-muted-foreground">Tweets sent from OneNetly</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="space-y-1 p-4">
                    <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Scheduled
                    </p>
                    <p class="text-2xl font-semibold">{{ props.stats.scheduled }}</p>
                    <p class="text-xs text-muted-foreground">Waiting to be published</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="space-y-1 p-4">
                    <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Mentions
                    </p>
                    <p class="text-2xl font-semibold">{{ props.stats.mentions }}</p>
                    <p class="text-xs text-muted-foreground">{{ props.stats.replied }} replied</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="space-y-1 p-4">
                    <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        Keyword rules
                    </p>
                    <p class="text-2xl font-semibold">{{ props.stats.keyword_rules }}</p>
                    <p class="text-xs text-muted-foreground">Mention replies</p>
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
                        Everything needed to automate X.
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
                    <CardTitle class="text-base">Recent mentions</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p v-if="props.mentions.length === 0" class="text-sm text-muted-foreground">
                        No mentions yet. They appear after the next sync.
                    </p>
                    <div
                        v-for="mention in props.mentions.slice(0, 5)"
                        :key="mention.id"
                        class="flex items-start gap-3 rounded-lg border p-3"
                    >
                        <MessageCircle class="mt-0.5 size-4 text-muted-foreground" />
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">
                                @{{ mention.author_username || 'unknown' }}
                            </p>
                            <p class="mt-1 truncate text-sm text-muted-foreground">
                                {{ mention.body }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium"
                            :class="
                                mention.replied
                                    ? 'bg-emerald-500/10 text-emerald-600'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            {{ mention.replied ? 'Replied' : 'New' }}
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
                        <p class="text-sm font-medium">Publish and schedule</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Write a tweet and send it now or at a chosen time.
                        </p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <p class="text-sm font-medium">Mention auto-replies</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Thank people who mention you, automatically.
                        </p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <p class="text-sm font-medium">Keyword answers</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Answer menu, hours or booking questions in replies.
                        </p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <p class="text-sm font-medium">Polling every 5 minutes</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            The scheduler checks mentions on your behalf.
                        </p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <p class="text-sm font-medium">Mention history</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            See who mentioned you and whether they were answered.
                        </p>
                    </div>
                    <div class="rounded-lg border p-4">
                        <p class="text-sm font-medium">Per-page accounts</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Each page connects its own X account and token.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div v-else-if="activeTab === 'composer'" class="grid items-start gap-6 lg:grid-cols-[1.3fr_1fr]">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Write a tweet</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Up to 280 characters. Links count toward the limit.
                    </p>
                </CardHeader>
                <CardContent class="space-y-4">
                    <form class="space-y-4" @submit.prevent="saveDraft">
                        <div class="grid gap-2">
                            <textarea
                                v-model="composer.body"
                                rows="7"
                                maxlength="280"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                placeholder="What's happening?"
                            />
                            <div class="flex items-center justify-between text-xs">
                                <InputError :message="composer.errors.body" />
                                <span
                                    :class="
                                        overLimit
                                            ? 'font-medium text-destructive'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{ characterCount }}/280
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button
                                type="submit"
                                variant="outline"
                                :disabled="composer.processing || overLimit"
                            >
                                <Save class="size-4" />
                                Save draft
                            </Button>
                            <Button
                                type="button"
                                :disabled="composer.processing || overLimit || !connected"
                                @click="publishNow"
                            >
                                <Send class="size-4" />
                                Publish now
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">How it behaves</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 text-sm text-muted-foreground">
                    <p>
                        Drafts stay here until you publish them. Scheduled tweets are
                        sent by <code class="text-xs">twitter:publish-scheduled</code>,
                        which runs every minute.
                    </p>
                    <p>
                        X may reject duplicate posts or posts containing too many
                        cashtags. The exact error is shown on the post.
                    </p>
                    <p>
                        Replies use the original tweet ID, so conversation threading
                        is preserved.
                    </p>
                </CardContent>
            </Card>

            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="text-base">Your posts</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        {{ props.posts.length }} stored post(s)
                    </p>
                </CardHeader>
                <CardContent class="space-y-3">
                    <p v-if="props.posts.length === 0" class="text-sm text-muted-foreground">
                        Nothing here yet. Write your first tweet above.
                    </p>

                    <div
                        v-for="post in props.posts"
                        :key="post.id"
                        class="space-y-3 rounded-lg border p-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <p class="max-w-2xl whitespace-pre-line text-sm">
                                {{ post.body }}
                            </p>
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-medium capitalize"
                                :class="statusClass(post.status)"
                            >
                                {{ post.status }}
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                            <span>{{ post.created_human }}</span>
                            <span v-if="post.scheduled_at" class="inline-flex items-center gap-1">
                                <CalendarDays class="size-3.5" />
                                {{ post.scheduled_at }}
                            </span>
                            <span v-if="post.published_at" class="inline-flex items-center gap-1">
                                <Clock class="size-3.5" />
                                {{ post.published_at }}
                            </span>
                            <a
                                v-if="post.external_id"
                                :href="`https://x.com/i/status/${post.external_id}`"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1 text-primary hover:underline"
                            >
                                View on X
                                <ExternalLink class="size-3" />
                            </a>
                        </div>

                        <p v-if="post.error" class="text-xs text-destructive">
                            {{ post.error }}
                        </p>

                        <div
                            v-if="post.status !== 'published'"
                            class="flex flex-wrap items-end gap-2"
                        >
                            <div class="grid gap-1">
                                <Label :for="`schedule-${post.id}`" class="text-xs">
                                    Schedule at
                                </Label>
                                <Input
                                    :id="`schedule-${post.id}`"
                                    v-model="scheduleAt[post.id]"
                                    type="datetime-local"
                                    class="h-8 w-56 text-xs"
                                />
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                :disabled="!scheduleAt[post.id]"
                                @click="schedulePost(post)"
                            >
                                <CalendarDays class="size-4" />
                                Schedule
                            </Button>
                            <Button size="sm" :disabled="!connected" @click="publishPost(post)">
                                <Send class="size-4" />
                                Publish now
                            </Button>
                            <Button size="sm" variant="ghost" @click="deletePost(post)">
                                <Trash2 class="size-4" />
                            </Button>
                        </div>

                        <Button
                            v-else
                            size="sm"
                            variant="ghost"
                            @click="deletePost(post)"
                        >
                            <Trash2 class="size-4" />
                            Remove from list
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div v-else-if="activeTab === 'automations'" class="space-y-6">
            <form class="space-y-4" @submit.prevent="saveAutomations">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-muted-foreground">
                        Placeholders: <code class="text-xs">{username}</code>,
                        <code class="text-xs">{account}</code> and
                        <code class="text-xs">{text}</code>.
                    </p>
                    <Button type="submit" :disabled="automationForm.processing">
                        <Save class="size-4" />
                        {{ automationForm.processing ? 'Saving…' : 'Save automation' }}
                    </Button>
                </div>

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
                        <textarea
                            v-model="automation.message"
                            rows="3"
                            maxlength="280"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        />
                        <InputError
                            :message="automationForm.errors[`automations.${index}.message`]"
                        />
                    </CardContent>
                </Card>
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
                                <Label for="twitter-keyword-name">Rule name</Label>
                                <Input
                                    id="twitter-keyword-name"
                                    v-model="keywordForm.name"
                                    maxlength="80"
                                    placeholder="Menu questions"
                                    required
                                />
                                <InputError :message="keywordForm.errors.name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="twitter-keywords">Keywords</Label>
                                <Input
                                    id="twitter-keywords"
                                    v-model="keywordForm.keywords"
                                    maxlength="500"
                                    placeholder="menu, food, eat"
                                    required
                                />
                                <InputError :message="keywordForm.errors.keywords" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="twitter-keyword-message">Reply</Label>
                                <textarea
                                    id="twitter-keyword-message"
                                    v-model="keywordForm.message"
                                    rows="3"
                                    maxlength="280"
                                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                                    placeholder="Hi @{username}, here is our menu: {account}"
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

        <div v-else-if="activeTab === 'mentions'" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm text-muted-foreground">
                    Mentions are polled every five minutes by
                    <code class="text-xs">twitter:sync-mentions</code>.
                </p>
                <Button variant="outline" @click="syncMentions">
                    <RefreshCw class="size-4" />
                    Sync now
                </Button>
            </div>

            <Card>
                <CardContent class="space-y-3 pt-6">
                    <p v-if="props.mentions.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        No mentions found yet.
                    </p>

                    <div
                        v-for="mention in props.mentions"
                        :key="mention.id"
                        class="space-y-2 rounded-lg border p-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <MessageCircle class="size-4 text-muted-foreground" />
                                <p class="text-sm font-medium">
                                    @{{ mention.author_username || 'unknown' }}
                                </p>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                                    :class="
                                        mention.replied
                                            ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    {{ mention.replied ? 'Replied' : 'Awaiting reply' }}
                                </span>
                            </div>
                            <span class="text-xs text-muted-foreground">
                                {{ mention.created_human }}
                            </span>
                        </div>

                        <p class="whitespace-pre-line text-sm">{{ mention.body }}</p>

                        <a
                            :href="tweetUrl(mention)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                        >
                            Open on X
                            <ExternalLink class="size-3" />
                        </a>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div v-else-if="activeTab === 'settings'" class="grid items-start gap-6 lg:grid-cols-[1.4fr_1fr]">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">X account</CardTitle>
                    <p class="text-sm text-muted-foreground">
                        Tokens are encrypted and stored on your account.
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
                                <span class="block text-sm font-medium">Enable Twitter</span>
                                <span class="block text-sm text-muted-foreground">
                                    Turn publishing, scheduling and mention automation on for
                                    your account.
                                </span>
                            </span>
                        </label>
                        <InputError :message="settingsForm.errors.enabled" />

                        <div class="grid gap-2">
                            <Label for="twitter-username">Username</Label>
                            <Input
                                id="twitter-username"
                                v-model="settingsForm.username"
                                placeholder="myrestaurant"
                            />
                            <p class="text-sm text-muted-foreground">
                                Used in automation messages and display only.
                            </p>
                            <InputError :message="settingsForm.errors.username" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="twitter-user-id">Numeric user ID</Label>
                            <Input
                                id="twitter-user-id"
                                v-model="settingsForm.user_id"
                                placeholder="1234567890"
                            />
                            <p class="text-sm text-muted-foreground">
                                Find it with the X API <code class="text-xs">/2/users/me</code>
                                endpoint.
                            </p>
                            <InputError :message="settingsForm.errors.user_id" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="twitter-access-token">OAuth 2.0 access token</Label>
                            <Input
                                id="twitter-access-token"
                                v-model="settingsForm.access_token"
                                type="password"
                                autocomplete="new-password"
                                :placeholder="
                                    props.connection.has_access_token
                                        ? 'A token is saved — leave blank to keep it'
                                        : 'Paste the user access token'
                                "
                            />
                            <p class="text-sm text-muted-foreground">
                                Needs <code class="text-xs">tweet.read</code>,
                                <code class="text-xs">tweet.write</code>,
                                <code class="text-xs">users.read</code> and
                                <code class="text-xs">offline.access</code>.
                            </p>
                            <InputError :message="settingsForm.errors.access_token" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="twitter-refresh-token">Refresh token (optional)</Label>
                            <Input
                                id="twitter-refresh-token"
                                v-model="settingsForm.refresh_token"
                                type="password"
                                autocomplete="new-password"
                                :placeholder="
                                    props.connection.has_refresh_token
                                        ? 'A refresh token is saved — leave blank to keep it'
                                        : 'Paste the refresh token'
                                "
                            />
                            <p class="text-sm text-muted-foreground">
                                Used with the OAuth client in Admin → Settings → Twitter / X to
                                renew access automatically.
                            </p>
                            <InputError :message="settingsForm.errors.refresh_token" />
                        </div>

                        <label
                            v-if="props.connection.has_access_token"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input
                                v-model="settingsForm.remove_token"
                                type="checkbox"
                                class="size-4 rounded border-input"
                            />
                            Remove the saved tokens
                        </label>

                        <div class="flex flex-wrap gap-2">
                            <Button type="submit" :disabled="settingsForm.processing">
                                <Save class="size-4" />
                                {{ settingsForm.processing ? 'Saving…' : 'Save settings' }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="!props.connection.has_access_token"
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
                        <CardTitle class="text-base">Scheduled jobs</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Mention polling</span>
                            <span>Every 5 minutes</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Scheduled posts</span>
                            <span>Every minute</span>
                        </div>
                        <p class="text-muted-foreground">
                            These run through Laravel's scheduler. A cron entry for
                            <code class="text-xs">php artisan schedule:run</code> is required.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Connection details</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">Account</span>
                            <span>
                                {{ props.connection.username ? `@${props.connection.username}` : '—' }}
                            </span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">User ID</span>
                            <span>{{ props.connection.user_id || '—' }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">Access token</span>
                            <span>
                                {{ props.connection.has_access_token ? 'Saved' : 'Missing' }}
                            </span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">Refresh token</span>
                            <span>
                                {{ props.connection.has_refresh_token ? 'Saved' : 'None' }}
                            </span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">Token expiry</span>
                            <span>{{ props.connection.token_expires_at || '—' }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Requirements</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm text-muted-foreground">
                        <p>
                            Create an app in the X developer portal with OAuth 2.0
                            Authorization Code + PKCE enabled.
                        </p>
                        <p>
                            Request <code class="text-xs">tweet.read</code>,
                            <code class="text-xs">tweet.write</code>,
                            <code class="text-xs">users.read</code> and
                            <code class="text-xs">offline.access</code>.
                        </p>
                        <p>
                            X's free tier is write-limited. Posting and mention polling may
                            require a paid API plan.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
