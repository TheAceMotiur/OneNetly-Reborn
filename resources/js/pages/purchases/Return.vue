<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Clock, Share2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';

defineProps<{
    purchase: {
        id: number;
        plan_name: string;
        amount: string;
        status: string;
    };
}>();

const year = new Date().getFullYear();
</script>

<template>
    <Head :title="'Payment received — ' + $page.props.name" />

    <div class="min-h-screen bg-background text-foreground">
        <header
            class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-6"
        >
            <Link href="/pricing" class="flex items-center gap-2 font-semibold">
                <span
                    class="flex size-8 items-center justify-center rounded-lg bg-indigo-500 text-white"
                >
                    <Share2 class="size-4" />
                </span>
                {{ $page.props.name }}
            </Link>
            <Link
                href="/dashboard"
                class="rounded-md bg-foreground px-4 py-2 text-sm font-medium text-background transition hover:opacity-90"
            >
                Go to dashboard
            </Link>
        </header>

        <main class="mx-auto w-full max-w-2xl px-6 py-16">
            <div class="rounded-2xl border bg-card p-8 text-center">
                <Clock class="mx-auto mb-4 size-12 text-amber-500" />
                <h1 class="text-2xl font-semibold">Payment received</h1>
                <p class="mt-2 text-muted-foreground">
                    Thanks! Your payment for the
                    <span class="font-medium text-foreground">{{
                        purchase.plan_name
                    }}</span>
                    plan is waiting for confirmation.
                </p>

                <dl class="mx-auto mt-8 grid max-w-sm grid-cols-2 gap-4 text-left">
                    <div>
                        <dt class="text-sm text-muted-foreground">Amount</dt>
                        <dd class="font-semibold">{{ purchase.amount }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">Status</dt>
                        <dd class="font-semibold">{{ purchase.status }}</dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-sm text-muted-foreground">Order</dt>
                        <dd class="font-mono">#{{ purchase.id }}</dd>
                    </div>
                </dl>

                <p class="mt-6 text-sm text-muted-foreground">
                    The site owner verifies each PayPal payment manually. Your
                    plan and its features activate as soon as it is confirmed.
                </p>

                <p
                    v-if="$page.props.settings.payment_note"
                    class="mt-4 rounded-lg border bg-muted/40 px-4 py-3 text-left text-sm whitespace-pre-line text-muted-foreground"
                >
                    {{ $page.props.settings.payment_note }}
                </p>

                <p
                    v-if="$page.props.settings.support_email"
                    class="mt-4 text-sm text-muted-foreground"
                >
                    Questions? Contact
                    <a
                        :href="
                            'mailto:' +
                            ($page.props.settings.support_email ?? '')
                        "
                        class="font-medium text-foreground underline underline-offset-4"
                    >
                        {{ $page.props.settings.support_email }}
                    </a>
                </p>

                <Button class="mt-6" as-child>
                    <Link href="/analytics">Go to analytics</Link>
                </Button>
            </div>
        </main>

        <footer class="border-t py-4 text-center text-sm text-muted-foreground">
            <p>© {{ year }} {{ $page.props.name }}</p>
            <p
                v-if="$page.props.settings.footer_text"
                class="mt-1 text-xs"
            >
                {{ $page.props.settings.footer_text }}
            </p>
        </footer>
    </div>
</template>
