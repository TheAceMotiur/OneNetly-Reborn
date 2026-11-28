<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Share2 } from '@lucide/vue';

defineProps<{
    title: string;
}>();

const year = new Date().getFullYear();
</script>

<template>
    <Head :title="'Terms of Service — ' + $page.props.name" />

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
                <Link
                    v-if="$page.props.auth.user"
                    href="/dashboard"
                    class="inline-flex items-center gap-1 rounded-md bg-foreground px-4 py-2 text-sm font-medium text-background transition hover:opacity-90"
                >
                    Go to dashboard
                    <ArrowRight class="size-4" />
                </Link>
            </nav>
        </header>

        <main class="mx-auto w-full max-w-6xl px-6 py-12">
            <div class="mx-auto max-w-3xl">
                <h1
                    class="mb-8 text-4xl font-semibold tracking-tight text-balance"
                >
                    {{ title }}
                </h1>

                <div class="prose max-w-none space-y-4 text-base">
                    <p>
                        These terms and conditions outline the rules and
                        regulations for the use of {{ $page.props.name }}'s website, located
                        at onenetly.com.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-medium">
                        1. Introduction
                    </h2>
                    <p>
                        By accessing this website, we assume you accept these
                        terms and conditions. Do not continue to use {{ $page.props.name }} if
                        you do not accept all the terms and conditions stated on
                        this page.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-medium">2. Privacy</h2>
                    <p>
                        Our Privacy Policy sets out the types of personal
                        information that {{ $page.props.name }} collects and how we use it. By
                        using the {{ $page.props.name }} website, you consent to the data
                        practices described in our Privacy Policy.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-medium">
                        3. Intellectual Property
                    </h2>
                    <p>
                        Unless otherwise stated, {{ $page.props.name }} and/or its licensors
                        own the intellectual property rights for all material on
                        {{ $page.props.name }}. All intellectual property rights are reserved.
                        You may view and/or print pages from onenetly.com for
                        your own personal use subject to restrictions set in
                        these terms and conditions.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-medium">
                        4. Links To Other Websites
                    </h2>
                    <p>
                        {{ $page.props.name }} may contain links to third-party websites that
                        are not provided or maintained by or associated with
                        {{ $page.props.name }}. Please be aware that {{ $page.props.name }} does not
                        guarantee the accuracy, relevance, timeliness, or
                        completeness of any information on third-party websites.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-medium">
                        5. Payments and Refunds
                    </h2>
                    <p>
                        Paid plans are billed once through PayPal. Payment
                        confirmation is reviewed by the site owner. If a payment
                        cannot be verified it will be marked as failed and no
                        plan will be activated.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-medium">
                        6. Termination
                    </h2>
                    <p>
                        {{ $page.props.name }} may terminate your access to {{ $page.props.name }} at any
                        time, without notice, for any reason whatsoever.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-medium">
                        7. Governing Law
                    </h2>
                    <p>
                        These terms and conditions shall be governed by and
                        construed in accordance with the laws of the
                        jurisdiction in which {{ $page.props.name }} operates.
                    </p>

                    <div class="mt-8 rounded-lg bg-muted/20 p-4">
                        <p class="mb-2 text-sm text-muted-foreground">
                            Last updated: November 25, 2026
                        </p>
                        <p class="text-xs text-muted-foreground">
                            By using {{ $page.props.name }}, you agree to these terms. If you
                            do not agree to these terms, please discontinue use
                            of the website immediately.
                        </p>
                    </div>
                </div>
            </div>
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
