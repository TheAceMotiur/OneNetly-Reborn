<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Eye,
    Share2,
    TrendingDown,
    TrendingUp,
    Trophy,
} from '@lucide/vue';
import { computed } from 'vue';

type LeaderboardEntry = {
    rank: number;
    username: string;
    display_name: string;
    avatar_url: string | null;
    url: string;
    views: number;
    previous_views: number;
    change: number | null;
};

const props = defineProps<{
    entries: LeaderboardEntry[];
    totals: {
        views: number;
        pages: number;
        range: string;
    };
}>();

const numberFormat = new Intl.NumberFormat('en-US');
const year = new Date().getFullYear();

const topViews = computed(() => props.entries[0]?.views ?? 0);

function formatNumber(value: number): string {
    return numberFormat.format(value);
}

function barWidth(views: number): string {
    if (topViews.value <= 0) {
        return '0%';
    }

    return `${Math.max(4, Math.round((views / topViews.value) * 100))}%`;
}

function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);

    return (parts[0]?.[0] ?? '?').toUpperCase() + (parts[1]?.[0] ?? '').toUpperCase();
}

function changeLabel(change: number | null): string {
    if (change === null) {
        return 'New';
    }

    return `${change > 0 ? '+' : ''}${change}%`;
}

function changeClass(change: number | null): string {
    return (change ?? 0) >= 0
        ? 'text-emerald-600 dark:text-emerald-400'
        : 'text-rose-600 dark:text-rose-400';
}

function rankClass(rank: number): string {
    if (rank === 1) {
        return 'border-amber-300 bg-amber-100 text-amber-700 dark:border-amber-400/40 dark:bg-amber-400/10 dark:text-amber-300';
    }

    if (rank === 2) {
        return 'border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-400/40 dark:bg-slate-400/10 dark:text-slate-300';
    }

    if (rank === 3) {
        return 'border-orange-300 bg-orange-100 text-orange-700 dark:border-orange-400/40 dark:bg-orange-400/10 dark:text-orange-300';
    }

    return 'border-border bg-muted text-muted-foreground';
}
</script>

<template>
    <Head :title="'BioLink leaderboard — ' + $page.props.name" />

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
                    class="rounded-md px-4 py-2 text-sm font-medium text-foreground"
                >
                    Leaderboard
                </Link>
                <Link
                    href="/pricing"
                    class="hidden rounded-md px-4 py-2 text-sm font-medium text-muted-foreground transition hover:text-foreground sm:inline-flex"
                >
                    Pricing
                </Link>

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

        <main class="mx-auto w-full max-w-6xl px-6 pb-20">
            <section class="py-12 text-center lg:py-16">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full border bg-muted px-3 py-1 text-xs font-medium text-muted-foreground"
                >
                    <Trophy class="size-3.5" />
                    Last 7 days · {{ props.totals.range }}
                </span>
                <h1
                    class="mt-4 text-4xl font-semibold tracking-tight text-balance sm:text-5xl"
                >
                    The BioLink leaderboard
                </h1>
                <p class="mx-auto mt-3 max-w-2xl text-lg text-muted-foreground">
                    Every week we rank the bio pages pulling the most visitors.
                    Publish a page, share your link and climb the board.
                </p>
            </section>

            <section class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border bg-card p-5">
                    <p class="flex items-center gap-2 text-sm text-muted-foreground">
                        <Eye class="size-4" />
                        Views this week
                    </p>
                    <p class="mt-2 text-3xl font-semibold tracking-tight">
                        {{ formatNumber(props.totals.views) }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-5">
                    <p
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <TrendingUp class="size-4" />
                        Pages on the board
                    </p>
                    <p class="mt-2 text-3xl font-semibold tracking-tight">
                        {{ formatNumber(props.totals.pages) }}
                    </p>
                </div>
                <div class="rounded-xl border bg-card p-5">
                    <p
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <Trophy class="size-4" />
                        This week's leader
                    </p>
                    <p
                        v-if="props.entries[0]"
                        class="mt-2 truncate text-xl font-semibold tracking-tight"
                    >
                        {{ props.entries[0].display_name }}
                    </p>
                    <p v-else class="mt-2 text-xl font-semibold text-muted-foreground">
                        No views yet
                    </p>
                </div>
            </section>

            <section
                class="mt-8 overflow-hidden rounded-2xl border bg-card shadow-sm"
            >
                <div
                    v-if="props.entries.length === 0"
                    class="flex flex-col items-center gap-3 px-6 py-16 text-center"
                >
                    <span
                        class="flex size-12 items-center justify-center rounded-full bg-muted"
                    >
                        <Trophy class="size-5 text-muted-foreground" />
                    </span>
                    <div class="space-y-1">
                        <p class="font-medium">The board is wide open</p>
                        <p class="text-sm text-muted-foreground">
                            No page views have been recorded in the last seven
                            days. Be the first name up here.
                        </p>
                    </div>
                    <Link
                        href="/register"
                        class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-500"
                    >
                        Create your page
                        <ArrowRight class="size-4" />
                    </Link>
                </div>

                <ol v-else class="divide-y">
                    <li
                        v-for="entry in props.entries"
                        :key="entry.username"
                    >
                        <a
                            :href="entry.url"
                            class="flex items-center gap-3 px-4 py-3 transition hover:bg-muted/50 sm:gap-4 sm:px-5"
                        >
                            <span
                                class="flex size-8 shrink-0 items-center justify-center rounded-lg border text-sm font-semibold tabular-nums"
                                :class="rankClass(entry.rank)"
                            >
                                {{ entry.rank }}
                            </span>

                            <span
                                class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-muted text-sm font-semibold text-muted-foreground"
                            >
                                <img
                                    v-if="entry.avatar_url"
                                    :src="entry.avatar_url"
                                    alt=""
                                    class="size-full object-cover"
                                />
                                <template v-else>
                                    {{ initials(entry.display_name) }}
                                </template>
                            </span>

                            <span class="min-w-0 flex-1">
                                <span
                                    class="flex flex-wrap items-center gap-x-2 gap-y-1"
                                >
                                    <span class="truncate font-medium">
                                        {{ entry.display_name }}
                                    </span>
                                    <span
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        @{{ entry.username }}
                                    </span>
                                    <span
                                        v-if="entry.change === null"
                                        class="rounded-full bg-indigo-500/10 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-400"
                                    >
                                        New
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-0.5 text-[11px] font-medium"
                                        :class="changeClass(entry.change)"
                                    >
                                        <TrendingUp
                                            v-if="(entry.change ?? 0) >= 0"
                                            class="size-3"
                                        />
                                        <TrendingDown v-else class="size-3" />
                                        {{ changeLabel(entry.change) }}
                                    </span>
                                </span>

                                <span
                                    class="mt-2 block h-1.5 overflow-hidden rounded-full bg-muted"
                                    aria-hidden="true"
                                >
                                    <span
                                        class="block h-full rounded-full bg-indigo-500"
                                        :style="{ width: barWidth(entry.views) }"
                                    />
                                </span>
                            </span>

                            <span
                                class="flex shrink-0 items-center gap-1.5 text-sm font-semibold tabular-nums"
                            >
                                <Eye class="size-4 text-muted-foreground" />
                                {{ formatNumber(entry.views) }}
                            </span>
                        </a>
                    </li>
                </ol>
            </section>

            <p class="mt-4 text-center text-xs text-muted-foreground">
                Counted from real visitors to published pages. Password
                protected pages are left out, and touches without a page view
                are not counted.
            </p>
        </main>

        <footer class="border-t">
            <div
                class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3 px-6 py-6 text-sm text-muted-foreground"
            >
                <p>© {{ year }} {{ $page.props.name }}</p>
                <div class="flex gap-4">
                    <Link href="/" class="transition hover:text-foreground">
                        Home
                    </Link>
                    <Link
                        href="/pricing"
                        class="transition hover:text-foreground"
                    >
                        Pricing
                    </Link>
                    <Link href="/docs" class="transition hover:text-foreground">
                        Docs
                    </Link>
                </div>
            </div>
        </footer>
    </div>
</template>
