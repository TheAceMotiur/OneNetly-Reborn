<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    Check,
    Code2,
    Copy,
    ExternalLink,
    KeyRound,
    Plus,
    Webhook,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard, login, register } from '@/routes';

type DeveloperRestaurant = {
    username: string;
    name: string;
    public_url: string;
    is_published: boolean;
    accepting_orders: boolean;
    token_count: number;
    has_webhook: boolean;
};

type SelectedRestaurant = {
    username: string;
    name: string;
    public_url: string;
    menu_url: string;
    is_published: boolean;
    ordering_enabled: boolean;
    accepting_orders: boolean;
    token_count: number;
    has_webhook: boolean;
};

type ApiToken = {
    id: number;
    name: string;
    prefix: string;
    last_used_human: string | null;
    created_human: string | null;
    destroy_url: string;
};

type WebhookDelivery = {
    id: number;
    event: string;
    success: boolean;
    status: number | null;
    error: string | null;
    attempts: number;
    created_human: string | null;
};

type IntegrationSettings = {
    webhook_url: string | null;
    has_secret: boolean;
    accepting_orders: boolean;
    api_base: string;
    tokens: ApiToken[];
    deliveries: WebhookDelivery[];
    urls: {
        update: string;
        test: string;
        tokens: string;
    };
};

const props = defineProps<{
    restaurants: DeveloperRestaurant[];
    selected: SelectedRestaurant | null;
    pos: IntegrationSettings | null;
}>();

const page = usePage<{ auth?: { user?: unknown } }>();

const isAuthenticated = computed(() => Boolean(page.props.auth?.user));

const manageLinks = [
    { id: 'overview', title: 'Overview' },
    { id: 'api-keys', title: 'API keys' },
    { id: 'webhooks', title: 'Webhooks' },
];

const docLinks = [
    { href: '/docs#pos-auth', title: 'Authentication' },
    { href: '/docs#api-menu', title: 'Menu endpoint' },
    { href: '/docs#api-orders', title: 'Orders endpoint' },
    { href: '/docs#api-order-update', title: 'Order status' },
    { href: '/docs#api-items', title: 'Item availability' },
    { href: '/docs#webhooks', title: 'Webhook payloads' },
    { href: '/docs#signatures', title: 'Signature verification' },
    { href: '/docs#errors', title: 'Errors & limits' },
];

const newToken = computed(
    () =>
        (page.flash as { pos_token?: string } | undefined)?.pos_token ?? null,
);

const generatedSecret = computed(
    () =>
        (page.flash as { pos_secret?: string } | undefined)?.pos_secret ??
        null,
);

const copied = ref<string | null>(null);

const year = new Date().getFullYear();

async function copy(value: string | null): Promise<void> {
    if (!value) {
        return;
    }

    try {
        await navigator.clipboard.writeText(value);
        copied.value = value;

        window.setTimeout(() => {
            if (copied.value === value) {
                copied.value = null;
            }
        }, 2000);
    } catch {
        copied.value = null;
    }
}

function selectRestaurant(event: Event): void {
    const username = (event.target as HTMLSelectElement).value;

    router.get(
        '/developers',
        { restaurant: username },
        { preserveScroll: true, preserveState: false },
    );
}

const webhookForm = useForm({
    webhook_url: props.pos?.webhook_url ?? '',
    webhook_secret: '',
    accepting_orders: props.selected?.accepting_orders ?? true,
});

const tokenForm = useForm({
    name: '',
});

watch(
    () => props.selected?.username,
    () => {
        webhookForm.webhook_url = props.pos?.webhook_url ?? '';
        webhookForm.webhook_secret = '';
        webhookForm.accepting_orders = props.selected?.accepting_orders ?? true;
        webhookForm.clearErrors();
        tokenForm.clearErrors();
    },
);

function saveWebhook(): void {
    if (props.pos === null) {
        return;
    }

    webhookForm.patch(props.pos.urls.update, {
        preserveScroll: true,
        onSuccess: () => {
            webhookForm.webhook_secret = '';
        },
    });
}

function sendTestWebhook(): void {
    if (props.pos === null) {
        return;
    }

    router.post(props.pos.urls.test, {}, { preserveScroll: true });
}

function createToken(): void {
    if (props.pos === null) {
        return;
    }

    tokenForm.post(props.pos.urls.tokens, {
        preserveScroll: true,
        onSuccess: () => {
            tokenForm.reset();
        },
    });
}

function revokeToken(token: ApiToken): void {
    if (
        !confirm(
            `Revoke "${token.name}"? Any integration using it stops working.`,
        )
    ) {
        return;
    }

    router.delete(token.destroy_url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Developer Console" />

    <div class="min-h-screen bg-background text-foreground">
        <header
            class="sticky top-0 z-40 border-b border-border/70 bg-background/90 backdrop-blur"
        >
            <div
                class="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-5"
            >
                <div class="flex items-center gap-3">
                    <Link href="/" class="flex items-center gap-2">
                        <AppLogo />
                    </Link>
                    <span class="h-5 w-px bg-border" />
                    <span
                        class="inline-flex items-center gap-1.5 text-sm font-medium"
                    >
                        <Code2 class="size-4" />
                        Developer Console
                    </span>
                </div>

                <nav class="flex items-center gap-4 text-sm">
                    <Link
                        href="/docs"
                        class="hidden text-muted-foreground transition hover:text-foreground sm:inline"
                    >
                        Docs
                    </Link>
                    <Link
                        v-if="isAuthenticated"
                        :href="dashboard()"
                        class="rounded-lg border px-3 py-1.5 font-medium transition hover:bg-muted"
                    >
                        Dashboard
                    </Link>
                    <Link
                        v-else
                        :href="login()"
                        class="rounded-lg border px-3 py-1.5 font-medium transition hover:bg-muted"
                    >
                        Sign in
                    </Link>
                </nav>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl px-5 py-10">
            <section class="max-w-3xl">
                <p
                    class="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase"
                >
                    {{ String($page.props.name ?? 'OneNetly') }} developer
                    platform
                </p>
                <h1 class="mt-2 text-4xl font-semibold tracking-tight">
                    Developer Console
                </h1>
                <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                    Manage API keys and webhooks for every restaurant you run.
                    Keys are scoped per restaurant, shown once, and revocable at
                    any time. The API reference, payloads and verification
                    samples live in the docs.
                </p>
                <div class="mt-5 flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full border px-3 py-1 font-medium">
                        REST API v1
                    </span>
                    <span class="rounded-full border px-3 py-1 font-medium">
                        Per-restaurant keys
                    </span>
                    <span class="rounded-full border px-3 py-1 font-medium">
                        HMAC-SHA256 webhooks
                    </span>
                </div>
                <div class="mt-6 flex flex-wrap gap-3 text-sm">
                    <Link
                        href="/docs"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-foreground px-4 py-2 font-medium text-background transition hover:opacity-90"
                    >
                        <BookOpen class="size-4" />
                        Read the docs
                    </Link>
                    <Link
                        href="/docs#api-menu"
                        class="inline-flex items-center gap-1.5 rounded-lg border px-4 py-2 font-medium transition hover:bg-muted"
                    >
                        API reference
                        <ArrowRight class="size-4" />
                    </Link>
                </div>
            </section>

            <section
                v-if="!isAuthenticated"
                class="mt-10 grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]"
            >
                <div class="rounded-2xl border p-6 sm:p-8">
                    <span
                        class="flex size-11 items-center justify-center rounded-xl border bg-muted/40"
                    >
                        <KeyRound class="size-5" />
                    </span>
                    <h2 class="mt-4 text-xl font-semibold">
                        Sign in to manage your integration
                    </h2>
                    <p class="mt-2 text-sm text-muted-foreground">
                        API keys and webhooks belong to your restaurants. Sign
                        in, choose a restaurant, and generate credentials in
                        seconds. No SDK or approval process required.
                    </p>
                    <ul class="mt-4 space-y-1.5 text-sm text-muted-foreground">
                        <li>Generate and revoke API keys per restaurant.</li>
                        <li>Connect a signed webhook for new orders.</li>
                        <li>Test delivery and inspect the delivery log.</li>
                    </ul>
                    <div class="mt-6 flex flex-wrap gap-2">
                        <Button as-child>
                            <Link :href="login()">Sign in</Link>
                        </Button>
                        <Button variant="outline" as-child>
                            <Link :href="register()">Create account</Link>
                        </Button>
                    </div>
                </div>

                <div class="rounded-2xl border p-6">
                    <h2 class="text-sm font-semibold">Documentation</h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Everything about the API already lives in the docs.
                    </p>
                    <ul class="mt-4 space-y-1 text-sm">
                        <li v-for="link in docLinks" :key="link.href">
                            <Link
                                :href="link.href"
                                class="flex items-center justify-between gap-3 rounded-md px-2 py-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                            >
                                {{ link.title }}
                                <ArrowRight class="size-3.5" />
                            </Link>
                        </li>
                    </ul>
                </div>
            </section>

            <div
                v-else
                class="mt-10 grid items-start gap-10 lg:grid-cols-[200px_minmax(0,1fr)]"
            >
                <aside
                    class="hidden lg:sticky lg:top-24 lg:block lg:max-h-[calc(100vh-8rem)] lg:overflow-y-auto lg:pr-4"
                >
                    <p
                        class="text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase"
                    >
                        Manage
                    </p>
                    <nav class="mt-2 space-y-0.5 text-sm">
                        <a
                            v-for="link in manageLinks"
                            :key="link.id"
                            :href="`#${link.id}`"
                            class="block rounded-md px-2 py-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        >
                            {{ link.title }}
                        </a>
                    </nav>

                    <p
                        class="mt-6 text-[11px] font-semibold tracking-[0.2em] text-muted-foreground uppercase"
                    >
                        Documentation
                    </p>
                    <nav class="mt-2 space-y-0.5 text-sm">
                        <Link
                            v-for="link in docLinks"
                            :key="link.href"
                            :href="link.href"
                            class="flex items-center justify-between gap-2 rounded-md px-2 py-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        >
                            {{ link.title }}
                            <ArrowRight class="size-3.5 shrink-0" />
                        </Link>
                    </nav>
                </aside>

                <div class="min-w-0 space-y-8">
                    <section id="overview" class="scroll-mt-24">
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h2 class="text-xl font-semibold tracking-tight">
                                    Overview
                                </h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Pick a restaurant to see its keys and
                                    webhook. Each restaurant is isolated.
                                </p>
                            </div>
                            <Link
                                href="/restaurant"
                                class="text-xs font-medium text-muted-foreground underline underline-offset-4 hover:text-foreground"
                            >
                                Manage restaurants
                            </Link>
                        </div>

                        <div
                            v-if="props.restaurants.length && props.selected"
                            class="mt-4 space-y-4 rounded-xl border p-5"
                        >
                            <div class="grid max-w-sm gap-2">
                                <Label for="console-restaurant">
                                    Restaurant
                                </Label>
                                <select
                                    id="console-restaurant"
                                    :value="props.selected.username"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    @change="selectRestaurant"
                                >
                                    <option
                                        v-for="restaurant in props.restaurants"
                                        :key="restaurant.username"
                                        :value="restaurant.username"
                                    >
                                        {{ restaurant.name }} ({{
                                            restaurant.username
                                        }})
                                    </option>
                                </select>
                                <p class="text-xs text-muted-foreground">
                                    Switching here changes the credentials shown
                                    below. Keys never work across restaurants.
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2 text-xs">
                                <span class="rounded-full border px-2.5 py-1">
                                    {{ props.selected?.token_count ?? 0 }}
                                    {{
                                        props.selected?.token_count === 1
                                            ? 'API key'
                                            : 'API keys'
                                    }}
                                </span>
                                <span class="rounded-full border px-2.5 py-1">
                                    {{
                                        props.selected?.has_webhook
                                            ? 'Webhook connected'
                                            : 'No webhook yet'
                                    }}
                                </span>
                                <span class="rounded-full border px-2.5 py-1">
                                    {{
                                        props.selected?.accepting_orders
                                            ? 'Accepting orders'
                                            : 'Ordering paused'
                                    }}
                                </span>
                                <a
                                    :href="props.selected?.public_url"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 font-medium transition hover:bg-muted"
                                >
                                    View menu
                                    <ExternalLink class="size-3" />
                                </a>
                            </div>

                            <div
                                v-if="props.pos"
                                class="rounded-lg border bg-muted/30 px-3 py-2 text-xs"
                            >
                                <span class="text-muted-foreground"
                                    >API base URL</span
                                >
                                <code class="mt-1 block break-all">{{
                                    props.pos?.api_base
                                }}</code>
                            </div>
                        </div>

                        <div
                            v-else
                            class="mt-4 flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-6 py-12 text-center text-muted-foreground"
                        >
                            <KeyRound class="size-7" />
                            <p class="font-medium text-foreground">
                                No restaurant yet
                            </p>
                            <p class="max-w-sm text-sm">
                                Add a restaurant first, then come back here to
                                generate keys and connect a POS.
                            </p>
                            <Button class="mt-2" as-child>
                                <Link href="/restaurant">
                                    Add a restaurant
                                </Link>
                            </Button>
                        </div>
                    </section>

                    <section
                        v-if="props.selected && props.pos"
                        id="api-keys"
                        class="scroll-mt-24"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <h2
                                class="flex items-center gap-2 text-xl font-semibold tracking-tight"
                            >
                                <KeyRound class="size-5" />
                                API keys
                            </h2>
                            <Link
                                href="/docs#pos-auth"
                                class="text-xs font-medium text-muted-foreground underline underline-offset-4 hover:text-foreground"
                            >
                                Token docs
                            </Link>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Keys let a POS read the menu, pull orders, update
                            statuses and mark items sold out. A key only works
                            for {{ props.selected?.name }}.
                        </p>

                        <div class="mt-4 space-y-4 rounded-xl border p-5">
                            <div
                                v-if="newToken"
                                class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-xs dark:border-emerald-500/30 dark:bg-emerald-500/10"
                            >
                                <p class="font-medium">
                                    New key (shown once — copy it now)
                                </p>
                                <code class="mt-1 block break-all">{{
                                    newToken
                                }}</code>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    class="mt-2"
                                    @click="copy(newToken)"
                                >
                                    <Check
                                        v-if="copied === newToken"
                                        class="size-4"
                                    />
                                    <Copy v-else class="size-4" />
                                    {{
                                        copied === newToken
                                            ? 'Copied'
                                            : 'Copy key'
                                    }}
                                </Button>
                            </div>

                            <ul
                                v-if="props.pos?.tokens.length"
                                class="divide-y rounded-lg border text-sm"
                            >
                                <li
                                    v-for="token in props.pos?.tokens ?? []"
                                    :key="token.id"
                                    class="flex items-center justify-between gap-3 px-3 py-2"
                                >
                                    <div class="min-w-0">
                                        <p class="truncate font-medium">
                                            {{ token.name }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ token.prefix }}… ·
                                            {{
                                                token.last_used_human ??
                                                'never used'
                                            }}
                                            <span v-if="token.created_human">
                                                · created
                                                {{ token.created_human }}
                                            </span>
                                        </p>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                        @click="revokeToken(token)"
                                    >
                                        Revoke
                                    </Button>
                                </li>
                            </ul>
                            <p
                                v-else
                                class="text-sm text-muted-foreground"
                            >
                                No API keys yet. Generate one to connect a POS
                                or integration.
                            </p>

                            <form
                                class="flex flex-wrap gap-2"
                                @submit.prevent="createToken"
                            >
                                <Input
                                    v-model="tokenForm.name"
                                    class="min-w-0 flex-1"
                                    placeholder="Bar POS"
                                    maxlength="60"
                                    required
                                />
                                <Button
                                    type="submit"
                                    :disabled="tokenForm.processing"
                                >
                                    <Plus class="size-4" />
                                    Generate key
                                </Button>
                            </form>
                            <InputError :message="tokenForm.errors.name" />
                        </div>
                    </section>

                    <section
                        v-if="props.selected && props.pos"
                        id="webhooks"
                        class="scroll-mt-24"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <h2
                                class="flex items-center gap-2 text-xl font-semibold tracking-tight"
                            >
                                <Webhook class="size-5" />
                                Webhooks
                            </h2>
                            <Link
                                href="/docs#webhooks"
                                class="text-xs font-medium text-muted-foreground underline underline-offset-4 hover:text-foreground"
                            >
                                Payload docs
                            </Link>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            New orders are POSTed to your endpoint as signed
                            JSON. Any POS or automation that accepts HTTPS
                            webhooks can connect.
                        </p>

                        <div class="mt-4 space-y-4 rounded-xl border p-5">
                            <form
                                class="space-y-3"
                                @submit.prevent="saveWebhook"
                            >
                                <label
                                    class="flex items-start gap-2 rounded-lg border p-3"
                                >
                                    <input
                                        v-model="webhookForm.accepting_orders"
                                        type="checkbox"
                                        class="mt-0.5 size-4 rounded border-input"
                                    />
                                    <span class="grid gap-0.5">
                                        <span class="text-sm font-medium">
                                            Accepting orders
                                        </span>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            Pause this to keep the menu online
                                            but stop new table orders.
                                        </span>
                                    </span>
                                </label>

                                <div class="grid gap-2">
                                    <Label for="console-webhook-url">
                                        Webhook URL
                                    </Label>
                                    <Input
                                        id="console-webhook-url"
                                        v-model="webhookForm.webhook_url"
                                        placeholder="https://your-pos.example/hooks/onenetly"
                                    />
                                    <InputError
                                        :message="
                                            webhookForm.errors.webhook_url
                                        "
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="console-webhook-secret">
                                        Signing secret
                                    </Label>
                                    <Input
                                        id="console-webhook-secret"
                                        v-model="webhookForm.webhook_secret"
                                        :placeholder="
                                            props.pos?.has_secret
                                                ? 'Stored — leave blank to keep it'
                                                : 'Generated on first save'
                                        "
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        HMAC-SHA256 signature sent as
                                        X-OneNetly-Signature. Leave blank to
                                        generate a new one.
                                    </p>
                                    <InputError
                                        :message="
                                            webhookForm.errors.webhook_secret
                                        "
                                    />
                                </div>

                                <div
                                    v-if="generatedSecret"
                                    class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs dark:border-amber-500/30 dark:bg-amber-500/10"
                                >
                                    <p class="font-medium">
                                        Signing secret (shown once — copy it
                                        now)
                                    </p>
                                    <code class="mt-1 block break-all">{{
                                        generatedSecret
                                    }}</code>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        class="mt-2"
                                        @click="copy(generatedSecret)"
                                    >
                                        <Check
                                            v-if="copied === generatedSecret"
                                            class="size-4"
                                        />
                                        <Copy v-else class="size-4" />
                                        {{
                                            copied === generatedSecret
                                                ? 'Copied'
                                                : 'Copy secret'
                                        }}
                                    </Button>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        type="submit"
                                        size="sm"
                                        :disabled="webhookForm.processing"
                                    >
                                        Save settings
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        @click="sendTestWebhook"
                                    >
                                        Send test webhook
                                    </Button>
                                </div>
                            </form>

                            <div
                                v-if="props.pos?.deliveries.length"
                                class="space-y-2 border-t pt-4"
                            >
                                <p class="text-sm font-medium">
                                    Recent webhook deliveries
                                </p>
                                <ul class="space-y-1.5 text-xs">
                                    <li
                                        v-for="delivery in props.pos
                                            ?.deliveries ?? []"
                                        :key="delivery.id"
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <span class="text-muted-foreground">
                                            {{ delivery.event }} ·
                                            {{ delivery.created_human }}
                                        </span>
                                        <span
                                            class="font-medium"
                                            :class="
                                                delivery.success
                                                    ? 'text-emerald-600 dark:text-emerald-400'
                                                    : 'text-red-600 dark:text-red-400'
                                            "
                                        >
                                            {{
                                                delivery.success
                                                    ? (delivery.status ??
                                                      'delivered')
                                                    : (delivery.status ??
                                                      delivery.error ??
                                                      'failed')
                                            }}
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <section
                        class="rounded-xl border bg-muted/20 p-5"
                    >
                        <h2 class="text-sm font-semibold">
                            Everything else lives in the docs
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            The console manages credentials only. Endpoints,
                            payloads, retries and signature samples are
                            documented once, in the docs.
                        </p>
                        <div class="mt-4 grid gap-2 sm:grid-cols-2">
                            <Link
                                v-for="link in docLinks"
                                :key="link.href"
                                :href="link.href"
                                class="flex items-center justify-between gap-3 rounded-lg border bg-background px-3 py-2 text-sm transition hover:bg-muted"
                            >
                                {{ link.title }}
                                <ArrowRight class="size-3.5" />
                            </Link>
                        </div>
                    </section>
                </div>
            </div>
        </main>

        <footer class="border-t">
            <div
                class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3 px-5 py-6 text-sm text-muted-foreground"
            >
                <p>
                    © {{ year }}
                    {{ String($page.props.name ?? 'OneNetly') }}
                </p>
                <div class="flex gap-4">
                    <Link href="/docs" class="transition hover:text-foreground"
                        >Docs</Link
                    >
                    <Link
                        href="/developers"
                        class="transition hover:text-foreground"
                        >Developers</Link
                    >
                    <Link href="/terms" class="transition hover:text-foreground"
                        >Terms</Link
                    >
                    <Link
                        href="/privacy"
                        class="transition hover:text-foreground"
                        >Privacy</Link
                    >
                    <Link href="/" class="transition hover:text-foreground"
                        >Home</Link
                    >
                </div>
            </div>
        </footer>
    </div>
</template>
