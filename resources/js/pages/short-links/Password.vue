<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    slug: string;
    title: string | null;
}>();

const form = useForm({
    password: '',
});

function unlock(): void {
    form.post(`/s/${encodeURIComponent(props.slug)}/unlock`, {
        preserveScroll: true,
        onSuccess: () => form.reset('password'),
    });
}
</script>

<template>
    <Head :title="props.title ? `Protected link · ${props.title}` : 'Protected link'">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <div
        class="flex min-h-screen items-center justify-center bg-slate-950 px-5 py-12 text-white"
    >
        <div
            class="w-full max-w-sm rounded-2xl border border-white/10 bg-white/5 p-8 text-center shadow-2xl backdrop-blur"
        >
            <div
                class="mx-auto flex size-12 items-center justify-center rounded-full border border-white/15 bg-white/10"
            >
                <Lock class="size-5" />
            </div>

            <h1 class="mt-4 text-xl font-semibold tracking-tight">
                This link is protected
            </h1>
            <p class="mt-2 text-sm leading-relaxed text-slate-400">
                {{
                    props.title
                        ? `Enter the password to open “${props.title}”.`
                        : 'Enter the password to continue to the destination.'
                }}
            </p>

            <form class="mt-6 space-y-4 text-left" @submit.prevent="unlock">
                <div class="grid gap-2">
                    <Label for="password" class="text-slate-200">Password</Label>
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
                    {{ form.processing ? 'Opening…' : 'Open link' }}
                </Button>
            </form>

            <p class="mt-5 text-xs text-slate-500">/s/{{ props.slug }}</p>
        </div>
    </div>
</template>
