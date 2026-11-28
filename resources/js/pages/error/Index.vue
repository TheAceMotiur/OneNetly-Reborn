<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Home, Share2 } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    status: number;
}>();

const content = computed(() => {
    switch (props.status) {
        case 403:
            return {
                title: 'Access denied',
                message:
                    'You do not have permission to open this page. If you think this is a mistake, contact support.',
            };
        case 404:
            return {
                title: 'Page not found',
                message:
                    'The page you are looking for does not exist or has been moved.',
            };
        case 419:
            return {
                title: 'Page expired',
                message:
                    'Your session expired for security reasons. Refresh the page and try again.',
            };
        case 429:
            return {
                title: 'Too many requests',
                message:
                    'You are moving a little too fast. Please wait a moment and try again.',
            };
        case 503:
            return {
                title: 'Temporarily unavailable',
                message:
                    'We are doing a quick bit of maintenance. Please check back shortly.',
            };
        default:
            return {
                title: 'Something went wrong',
                message:
                    'An unexpected error occurred. Please try again in a moment.',
            };
    }
});

const page = usePage<{ auth?: { user?: unknown } }>();

const isSignedIn = computed(() => Boolean(page.props.auth?.user));

function goBack(): void {
    window.history.back();
}
</script>

<template>
    <Head :title="content.title" />

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
        </header>

        <main
            class="mx-auto flex w-full max-w-2xl flex-col items-center px-6 py-20 text-center"
        >
            <p class="text-sm font-semibold tracking-widest text-indigo-600 uppercase">
                Error {{ props.status }}
            </p>
            <h1 class="mt-3 text-4xl font-semibold tracking-tight text-balance">
                {{ content.title }}
            </h1>
            <p class="mt-4 max-w-md text-muted-foreground">
                {{ content.message }}
            </p>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                <Link
                    :href="isSignedIn ? '/dashboard' : '/'"
                    class="inline-flex items-center gap-2 rounded-md bg-foreground px-4 py-2 text-sm font-medium text-background transition hover:opacity-90"
                >
                    <Home class="size-4" />
                    {{ isSignedIn ? 'Go to dashboard' : 'Go home' }}
                </Link>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-md border px-4 py-2 text-sm font-medium transition hover:bg-muted"
                    @click="goBack"
                >
                    <ArrowLeft class="size-4" />
                    Go back
                </button>
            </div>
        </main>
    </div>
</template>
