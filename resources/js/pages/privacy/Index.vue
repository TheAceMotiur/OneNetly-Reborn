<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Share2 } from '@lucide/vue';

defineProps<{
    title: string;
}>();

const year = new Date().getFullYear();
</script>

<template>
    <Head :title="'Privacy Policy — ' + $page.props.name" />

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
                        {{ $page.props.name }}'s Privacy Policy describes how we collect, use,
                        disclose, and safeguard the information of users of the
                        {{ $page.props.name }} website.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-medium">
                        1. Information We Collect
                    </h2>
                    <ul class="list-disc space-y-1 pl-5 text-sm">
                        <li>
                            Personal information you provide directly, such as
                            name, email address, and payment details when
                            purchasing a plan.
                        </li>
                        <li>
                            Automatically collected information, such as IP
                            address, browser type, and usage patterns via
                            cookies and similar technologies.
                        </li>
                        <li>
                            Link click data and analytics to improve our service
                            and provide click tracking.
                        </li>
                    </ul>

                    <h2 class="mt-8 mb-4 text-xl font-medium">
                        2. How We Use Your Information
                    </h2>
                    <ul class="list-disc space-y-1 pl-5 text-sm">
                        <li>
                            To provide and maintain our service, including
                            monitoring the usage of your bio page.
                        </li>
                        <li>
                            To process payments and manage your subscription
                            plan.
                        </li>
                        <li>
                            To send administrative emails, such as welcome
                            emails, billing receipts, and security notices.
                        </li>
                        <li>
                            To detect, prevent, and address technical issues.
                        </li>
                    </ul>

                    <h2 class="mt-8 mb-4 text-xl font-medium">
                        3. Data Sharing
                    </h2>
                    <ul class="list-disc space-y-1 pl-5 text-sm">
                        <li>
                            We may share your information with service providers
                            who perform functions on our behalf.
                        </li>
                        <li>
                            We may share your information if required by law or
                            to protect the rights, property, or safety of
                            {{ $page.props.name }}.
                        </li>
                        <li>
                            We do not sell your personal information to third
                            parties.
                        </li>
                    </ul>

                    <h2 class="mt-8 mb-4 text-xl font-medium">
                        4. International Data Transfer
                    </h2>
                    <p>
                        Your information may be transferred to — and maintained
                        on — computers located outside of your state, province,
                        country, or other governmental jurisdiction where the
                        data protection laws may differ from those of your
                        jurisdiction.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-medium">5. Security</h2>
                    <p>
                        We strive to use commercially acceptable means of
                        protecting your personal information. But remember that
                        no method of electronic transmission or storage is 100%
                        secure.
                    </p>

                    <h2 class="mt-8 mb-4 text-xl font-medium">
                        6. Your Rights
                    </h2>
                    <ul class="list-disc space-y-1 pl-5 text-sm">
                        <li>
                            You have the right to access, correct, or delete your
                            personal information.
                        </li>
                        <li>You have the right to data portability.</li>
                        <li>
                            You have the right to withdraw consent at any time.
                        </li>
                    </ul>

                    <h2 class="mt-8 mb-4 text-xl font-medium">
                        7. Children's Privacy
                    </h2>
                    <p>
                        Our service is not directed to anyone under the age of
                        13. We do not knowingly collect personal identifiable
                        information from children under 13 years of age. In the
                        case we do collect such data, we will delete it
                        immediately.
                    </p>

                    <div class="mt-8 rounded-lg bg-muted/20 p-4">
                        <p class="mb-2 text-sm text-muted-foreground">
                            Last updated: November 25, 2026
                        </p>
                        <p class="text-xs text-muted-foreground">
                            By using {{ $page.props.name }}, you agree to these practices. If
                            you do not agree to these practices, please
                            discontinue use of the website immediately.
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
