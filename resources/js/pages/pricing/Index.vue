<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, Check, CreditCard, Layers, Share2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type PricingPlan = {
    id: number;
    name: string;
    type: string;
    plan_type: string;
    tool: string | null;
    tool_label: string | null;
    is_tool: boolean;
    is_bundle: boolean;
    is_subscribed: boolean;
    price: string;
    numeric_price: number;
    currency: string;
    features: string[];
    is_free: boolean;
    has_custom_domain: boolean;
    has_analytics: boolean;
    max_links: number | null;
    duration_days: number | null;
    duration_label: string;
};

const props = defineProps<{
    plans: PricingPlan[];
    paypalConfigured: boolean;
    paymentsEnabled: boolean;
    currentPlanId: number | null;
    subscribedTools: string[];
    hasBundle: boolean;
}>();

const year = new Date().getFullYear();
const buying = ref<number | null>(null);

const automationPlans = computed(() =>
    props.plans.filter((plan) => plan.is_tool || plan.is_bundle),
);

const toolPlans = computed(() => props.plans.filter((plan) => plan.is_tool));

const sitePlans = computed(() =>
    props.plans.filter((plan) => !plan.is_tool && !plan.is_bundle),
);

const productCount = computed(() => toolPlans.value.length);

function buy(plan: PricingPlan): void {
    buying.value = plan.id;

    router.post(
        '/purchase',
        { plan_id: plan.id },
        { onFinish: () => (buying.value = null) },
    );
}

function buyLabel(plan: PricingPlan): string {
    if (plan.is_subscribed) {
        return 'Subscribed';
    }

    if (props.hasBundle) {
        return 'Included in bundle';
    }

    if (!props.paymentsEnabled) {
        return 'Checkout paused';
    }

    return buying.value === plan.id ? 'Redirecting…' : `Buy ${plan.name}`;
}

function buyDisabled(plan: PricingPlan): boolean {
    return (
        buying.value === plan.id ||
        plan.is_subscribed ||
        props.hasBundle ||
        !props.paymentsEnabled
    );
}
</script>

<template>
    <Head :title="'Pricing — ' + $page.props.name" />

    <div class="min-h-screen bg-background text-foreground">
        <header
            class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-6"
        >
            <Link href="/" class="flex items-center gap-2 font-semibold">
                <span
                    class="flex size-8 items-center justify-center rounded-lg bg-indigo-500 text-white"
                >
                    <Share2 class="size-4" />
                </span>
                {{ $page.props.name }}
            </Link>

            <nav class="flex items-center gap-2">
                <template v-if="$page.props.auth.user">
                    <Link
                        href="/dashboard"
                        class="inline-flex items-center gap-1 rounded-md bg-foreground px-4 py-2 text-sm font-medium text-background transition hover:opacity-90"
                    >
                        Go to dashboard
                        <ArrowRight class="size-4" />
                    </Link>
                </template>
                <template v-else>
                    <Link
                        href="/login"
                        class="rounded-md px-4 py-2 text-sm font-medium text-muted-foreground transition hover:text-foreground"
                    >
                        Log in
                    </Link>
                    <Link
                        href="/register"
                        class="inline-flex items-center gap-1 rounded-md bg-foreground px-4 py-2 text-sm font-medium text-background transition hover:opacity-90"
                    >
                        Get started
                    </Link>
                </template>
            </nav>
        </header>

        <main class="mx-auto w-full max-w-6xl px-6 py-12">
            <div class="mx-auto max-w-3xl text-center">
                <h1
                    class="mb-4 text-4xl font-semibold tracking-tight text-balance"
                >
                    Choose your tools
                </h1>
                <p class="mb-8 text-lg text-muted-foreground">
                    Every automation is a separate subscription. Buy only the
                    tools you need, add more at any time, or get all of them in
                    one bundle.
                </p>
                <p
                    v-if="!props.paymentsEnabled"
                    class="mb-8 rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm"
                >
                    Checkout is temporarily paused. Your existing subscriptions
                    stay active.
                </p>
                <p
                    v-else-if="!props.paypalConfigured"
                    class="mb-8 rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm"
                >
                    Checkout is not configured yet. Ask the site
                    administrator to set the PayPal.me username in the admin
                    settings.
                </p>
            </div>

            <section v-if="productCount > 0" class="mt-4">
                <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-2xl font-semibold tracking-tight">
                            Single tools
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            One subscription per tool. The tool appears in your
                            sidebar as soon as it is active.
                        </p>
                    </div>
                    <Badge variant="secondary">
                        {{ props.subscribedTools.length }} active
                    </Badge>
                </div>

                <div
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="plan in automationPlans"
                        :key="plan.id"
                        class="relative flex flex-col rounded-2xl border bg-card p-6 transition hover:border-indigo-500 hover:shadow-lg"
                        :class="
                            plan.is_bundle
                                ? 'border-indigo-500/60 ring-1 ring-indigo-500/40 sm:col-span-2 lg:col-span-3'
                                : ''
                        "
                    >
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <h3 class="text-lg font-medium">
                                    {{ plan.name }}
                                </h3>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        plan.is_bundle
                                            ? 'All automation tools in one subscription'
                                            : plan.features[0] ?? 'Automation tool'
                                    }}
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <Badge v-if="plan.is_subscribed">Active</Badge>
                                <Badge v-else-if="plan.is_bundle" variant="secondary">
                                    Best value
                                </Badge>
                            </div>
                        </div>

                        <p class="text-3xl font-bold">{{ plan.price }}</p>
                        <p class="mb-5 text-sm text-muted-foreground">
                            One-time payment · {{ plan.duration_label }}
                        </p>

                        <ul
                            class="mb-6 space-y-2 text-sm text-muted-foreground"
                            :class="plan.is_bundle ? 'sm:grid sm:grid-cols-2 sm:gap-x-6' : ''"
                        >
                            <li
                                v-for="feature in plan.features"
                                :key="feature"
                                class="flex items-start gap-2"
                            >
                                <Check
                                    class="mt-0.5 size-4 shrink-0 text-indigo-600"
                                />
                                {{ feature }}
                            </li>
                        </ul>

                        <div class="mt-auto">
                            <Button
                                class="w-full"
                                :variant="plan.is_bundle ? 'default' : 'outline'"
                                :disabled="buyDisabled(plan)"
                                @click="buy(plan)"
                            >
                                <Layers v-if="plan.is_bundle" class="size-4" />
                                <CreditCard v-else class="size-4" />
                                {{ buyLabel(plan) }}
                            </Button>
                        </div>
                    </div>
                </div>
            </section>

            <p
                v-if="productCount === 0 && props.hasBundle === false"
                class="mt-8 rounded-lg border px-4 py-6 text-center text-sm text-muted-foreground"
            >
                No automation tools are on sale right now.
            </p>

            <section v-if="sitePlans.length > 0" class="mt-16">
                <div class="mb-6">
                    <h2 class="text-2xl font-semibold tracking-tight">
                        Website plans
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Separate from the automation tools: these plans change
                        your page limits, custom domain and analytics.
                    </p>
                </div>

                <div
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="plan in sitePlans"
                        :key="plan.id"
                        class="flex flex-col rounded-2xl border bg-card p-8 transition hover:border-indigo-500 hover:shadow-lg"
                    >
                        <div class="mb-6 flex items-center gap-3">
                            <h3 class="text-xl font-medium">{{ plan.name }}</h3>
                            <Badge v-if="plan.id === props.currentPlanId">
                                Current plan
                            </Badge>
                            <Badge v-else-if="plan.type !== 'Free'" variant="secondary">
                                {{ plan.type }}
                            </Badge>
                        </div>

                        <p class="text-3xl font-bold">{{ plan.price }}</p>
                        <p class="mb-6 text-sm text-muted-foreground">
                            {{
                                plan.is_free
                                    ? 'Free forever'
                                    : `One-time payment · ${plan.duration_label}`
                            }}
                        </p>

                        <ul class="mb-8 space-y-3 text-sm text-muted-foreground">
                            <li
                                v-for="feature in plan.features"
                                :key="feature"
                                class="flex items-start gap-2"
                            >
                                <Check
                                    class="mt-0.5 size-4 shrink-0 text-indigo-600"
                                />
                                {{ feature }}
                            </li>
                            <li class="flex items-start gap-2">
                                <Check
                                    v-if="plan.has_custom_domain"
                                    class="mt-0.5 size-4 shrink-0 text-indigo-600"
                                />
                                <span
                                    v-else
                                    class="mt-0.5 size-4 shrink-0 text-center text-muted-foreground"
                                    >–</span
                                >
                                Custom domain
                            </li>
                            <li class="flex items-start gap-2">
                                <Check
                                    v-if="plan.has_analytics"
                                    class="mt-0.5 size-4 shrink-0 text-indigo-600"
                                />
                                <span
                                    v-else
                                    class="mt-0.5 size-4 shrink-0 text-center text-muted-foreground"
                                    >–</span
                                >
                                Click analytics
                            </li>
                            <li class="flex items-start gap-2">
                                <Check
                                    class="mt-0.5 size-4 shrink-0 text-indigo-600"
                                />
                                {{
                                    plan.max_links === null
                                        ? 'Unlimited links'
                                        : `Up to ${plan.max_links} links`
                                }}
                            </li>
                        </ul>

                        <div class="mt-auto">
                            <Button
                                v-if="plan.is_free"
                                variant="outline"
                                class="w-full"
                                as-child
                            >
                                <Link href="/register">Start free</Link>
                            </Button>
                            <Button
                                v-else
                                class="w-full"
                                :disabled="
                                    buying === plan.id ||
                                    plan.id === props.currentPlanId ||
                                    !props.paymentsEnabled
                                "
                                @click="buy(plan)"
                            >
                                <CreditCard class="size-4" />
                                {{
                                    plan.id === props.currentPlanId
                                        ? 'Current plan'
                                        : !props.paymentsEnabled
                                          ? 'Checkout paused'
                                          : buying === plan.id
                                            ? 'Redirecting…'
                                            : `Buy ${plan.name}`
                                }}
                            </Button>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t pt-6">
            <div
                class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3 px-6 py-4 text-sm text-muted-foreground"
            >
                <div>
                    <p>© {{ year }} {{ $page.props.name }}</p>
                    <p
                        v-if="$page.props.settings.footer_text"
                        class="text-xs"
                    >
                        {{ $page.props.settings.footer_text }}
                    </p>
                </div>
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
                    <Link href="/privacy" class="transition hover:text-foreground"
                        >Privacy</Link
                    >
                </div>
            </div>
        </footer>
    </div>
</template>
