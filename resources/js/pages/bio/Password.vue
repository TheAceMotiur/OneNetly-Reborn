<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ProtectedProfile = {
    username: string;
    display_name: string;
    avatar_url: string | null;
    theme: string;
};

const props = defineProps<{
    profile: ProtectedProfile;
}>();

const accents: Record<string, string> = {
    indigo: 'bg-indigo-500',
    emerald: 'bg-emerald-500',
    rose: 'bg-rose-500',
    amber: 'bg-amber-500',
    sky: 'bg-sky-500',
    slate: 'bg-slate-500',
};

const accent = computed(
    () => accents[props.profile.theme] ?? accents.indigo,
);

const initials = computed(() =>
    props.profile.display_name
        .split(' ')
        .map((part) => part.charAt(0))
        .join('')
        .slice(0, 2)
        .toUpperCase(),
);

const form = useForm({
    password: '',
});

function unlock(): void {
    form.post(`/${encodeURIComponent(props.profile.username)}/unlock`, {
        preserveScroll: true,
        onSuccess: () => form.reset('password'),
    });
}
</script>

<template>
    <Head :title="`Protected page · ${props.profile.display_name}`">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <div
        class="flex min-h-screen items-center justify-center bg-slate-950 px-5 py-12 text-white"
    >
        <div
            class="w-full max-w-sm rounded-2xl border border-white/10 bg-white/5 p-8 text-center shadow-2xl backdrop-blur"
        >
            <div
                :class="[accent]"
                class="mx-auto flex size-20 items-center justify-center overflow-hidden rounded-full text-xl font-semibold shadow-lg ring-2 ring-white/20"
            >
                <img
                    v-if="props.profile.avatar_url"
                    :src="props.profile.avatar_url"
                    :alt="props.profile.display_name"
                    class="size-full object-cover"
                />
                <span v-else>{{ initials }}</span>
            </div>

            <div
                class="mx-auto mt-5 flex size-10 items-center justify-center rounded-full border border-white/15 bg-white/10"
            >
                <Lock class="size-4" />
            </div>

            <h1 class="mt-4 text-xl font-semibold tracking-tight">
                This page is protected
            </h1>
            <p class="mt-2 text-sm leading-relaxed text-slate-400">
                Enter the password to view
                {{ props.profile.display_name }}'s links.
            </p>

            <form class="mt-6 space-y-4 text-left" @submit.prevent="unlock">
                <div class="grid gap-2">
                    <Label for="password" class="text-slate-200">
                        Password
                    </Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        autofocus
                        class="border-white/15 bg-white/10 text-white placeholder:text-slate-500"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <Button
                    type="submit"
                    class="w-full bg-white text-slate-950 hover:bg-slate-200"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Unlocking…' : 'Unlock page' }}
                </Button>
            </form>

            <p class="mt-5 text-xs text-slate-500">
                @{{ props.profile.username }}
            </p>
        </div>
    </div>
</template>
