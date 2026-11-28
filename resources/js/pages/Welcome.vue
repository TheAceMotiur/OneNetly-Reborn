<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Link2,
    MousePointerClick,
    Palette,
    Share2,
} from '@lucide/vue';
import { dashboard, login, register } from '@/routes';

const features = [
    {
        title: 'One page, every link',
        description:
            'Collect your socials, store, portfolio and newsletter in a single page you can share anywhere.',
        icon: Link2,
    },
    {
        title: 'Click tracking',
        description:
            'Every link goes through your own short URL, so you can see what your audience actually opens.',
        icon: MousePointerClick,
    },
    {
        title: 'Your own look',
        description:
            'Add a photo, a short bio and an accent color that matches your brand in a couple of minutes.',
        icon: Palette,
    },
];

const demoLinks = ['My portfolio', 'Latest video', 'Newsletter'];

const year = new Date().getFullYear();
</script>

<template>
    <Head :title="$page.props.name + ' — one link for everything you do'" />

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
                    href="/leaderboard"
                    class="rounded-md px-4 py-2 text-sm font-medium text-muted-foreground transition hover:text-foreground"
                >
                    Leaderboard
                </Link>

                <template v-if="$page.props.auth.user">
                    <Link
                        :href="dashboard()"
                        class="inline-flex items-center gap-1 rounded-md bg-foreground px-4 py-2 text-sm font-medium text-background transition hover:opacity-90"
                    >
                        Go to dashboard
                        <ArrowRight class="size-4" />
                    </Link>
                </template>
                <template v-else>
                    <Link
                        href="/pricing"
                        class="rounded-md px-4 py-2 text-sm font-medium text-muted-foreground transition hover:text-foreground"
                    >
                        Pricing
                    </Link>
                    <Link
                        :href="login()"
                        class="rounded-md px-4 py-2 text-sm font-medium text-muted-foreground transition hover:text-foreground"
                    >
                        Log in
                    </Link>
                    <Link
                        :href="register()"
                        class="inline-flex items-center gap-1 rounded-md bg-foreground px-4 py-2 text-sm font-medium text-background transition hover:opacity-90"
                    >
                        Get started
                        <ArrowRight class="size-4" />
                    </Link>
                </template>
            </nav>
        </header>

        <main class="mx-auto w-full max-w-6xl px-6">
            <section
                class="grid items-center gap-12 py-16 lg:grid-cols-2 lg:py-24"
            >
                <div class="space-y-6">
                    <h1
                        class="text-4xl font-semibold tracking-tight text-balance sm:text-5xl"
                    >
                        One link for everything you do online.
                    </h1>
                    <p class="max-w-xl text-lg text-muted-foreground">
                        Build a bio page in minutes, add the links that matter
                        and share a single address on every profile. See which
                        links people click along the way.
                    </p>
                    <div class="flex flex-wrap items-center gap-3">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-500"
                        >
                            Open my page
                            <ArrowRight class="size-4" />
                        </Link>
                        <Link
                            v-else
                            :href="register()"
                            class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-500"
                        >
                            Create your page
                            <ArrowRight class="size-4" />
                        </Link>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Free to start. No credit card required.
                    </p>
                </div>

                <div class="flex justify-center lg:justify-end">
                    <div
                        class="w-full max-w-sm rounded-2xl border bg-card p-6 shadow-xl shadow-black/5"
                    >
                        <div
                            class="flex flex-col items-center gap-3 text-center"
                        >
                            <div
                                class="flex size-20 items-center justify-center rounded-full bg-indigo-500 text-xl font-semibold text-white"
                            >
                                JD
                            </div>
                            <div class="space-y-1">
                                <p class="font-semibold">Jamie Doe</p>
                                <p class="text-sm text-muted-foreground">
                                    @jamiedoe
                                </p>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                Designer, maker and occasional writer. All my
                                projects live here.
                            </p>
                        </div>
                        <div class="mt-6 space-y-2">
                            <div
                                v-for="label in demoLinks"
                                :key="label"
                                class="rounded-lg border bg-background px-4 py-3 text-center text-sm font-medium"
                            >
                                {{ label }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 pb-20 md:grid-cols-3">
                <div
                    v-for="feature in features"
                    :key="feature.title"
                    class="rounded-xl border bg-card p-6"
                >
                    <span
                        class="mb-4 flex size-10 items-center justify-center rounded-lg bg-muted"
                    >
                        <component :is="feature.icon" class="size-5" />
                    </span>
                    <h2 class="mb-1 font-medium">{{ feature.title }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ feature.description }}
                    </p>
                </div>
            </section>
        </main>

        <footer class="border-t">
            <div
                class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3 px-6 py-6 text-sm text-muted-foreground"
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
                    <a
                        v-if="$page.props.settings.support_email"
                        :href="
                            'mailto:' +
                            ($page.props.settings.support_email ?? '')
                        "
                        class="transition hover:text-foreground"
                    >
                        Contact
                    </a>
                    <Link href="/docs" class="transition hover:text-foreground">Docs</Link>
                    <Link href="/developers" class="transition hover:text-foreground">Developers</Link>
                    <Link href="/pricing" class="transition hover:text-foreground">Pricing</Link>
                    <Link href="/terms" class="transition hover:text-foreground">Terms</Link>
                    <Link href="/privacy" class="transition hover:text-foreground">Privacy</Link>
                </div>
            </div>
        </footer>
    </div>
</template>
