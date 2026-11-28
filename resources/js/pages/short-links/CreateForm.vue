<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

const emit = defineEmits<{
    done: [];
}>();

const inputClass =
    'flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm';

const form = useForm({
    title: '',
    destination: '',
    slug: '',
    mobile_destination: '',
    password: '',
    expires_at: '',
    max_clicks: '',
    is_active: true,
    utm_source: '',
    utm_medium: '',
    utm_campaign: '',
    utm_term: '',
    utm_content: '',
});

function submit(): void {
    form.post('/short-links', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('done');
        },
    });
}
</script>

<template>
    <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
        <div class="grid gap-2 sm:col-span-2">
            <Label for="destination">Destination URL</Label>
            <input
                id="destination"
                v-model="form.destination"
                :class="inputClass"
                placeholder="https://example.com/landing-page"
            />
            <InputError :message="form.errors.destination" />
        </div>

        <div class="grid gap-2">
            <Label for="title">Label (optional)</Label>
            <input
                id="title"
                v-model="form.title"
                :class="inputClass"
                placeholder="Spring campaign"
            />
            <InputError :message="form.errors.title" />
        </div>

        <div class="grid gap-2">
            <Label for="slug">Custom slug (optional)</Label>
            <input
                id="slug"
                v-model="form.slug"
                :class="inputClass"
                placeholder="spring-sale"
            />
            <InputError :message="form.errors.slug" />
        </div>

        <div class="grid gap-2">
            <Label for="expires_at">Expires (optional)</Label>
            <input
                id="expires_at"
                v-model="form.expires_at"
                :class="inputClass"
                type="datetime-local"
            />
            <InputError :message="form.errors.expires_at" />
        </div>

        <div class="grid gap-2">
            <Label for="max_clicks">Click limit (optional)</Label>
            <input
                id="max_clicks"
                v-model="form.max_clicks"
                :class="inputClass"
                type="number"
                min="1"
                placeholder="1000"
            />
            <InputError :message="form.errors.max_clicks" />
        </div>

        <div class="grid gap-2">
            <Label for="password">Password (optional)</Label>
            <input
                id="password"
                v-model="form.password"
                :class="inputClass"
                type="password"
                autocomplete="new-password"
                placeholder="At least 4 characters"
            />
            <InputError :message="form.errors.password" />
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input
                v-model="form.is_active"
                type="checkbox"
                class="size-4 rounded border-input"
            />
            Active immediately
        </label>

        <div class="grid gap-4 rounded-lg border p-4 sm:col-span-2 sm:grid-cols-2">
            <div class="grid gap-2 sm:col-span-2">
                <Label for="mobile_destination">Mobile destination (optional)</Label>
                <input
                    id="mobile_destination"
                    v-model="form.mobile_destination"
                    :class="inputClass"
                    placeholder="https://example.com/mobile-app"
                />
                <InputError :message="form.errors.mobile_destination" />
            </div>

            <div class="grid gap-2">
                <Label for="utm_source">utm_source</Label>
                <input
                    id="utm_source"
                    v-model="form.utm_source"
                    :class="inputClass"
                    placeholder="newsletter"
                />
            </div>

            <div class="grid gap-2">
                <Label for="utm_medium">utm_medium</Label>
                <input
                    id="utm_medium"
                    v-model="form.utm_medium"
                    :class="inputClass"
                    placeholder="email"
                />
            </div>

            <div class="grid gap-2">
                <Label for="utm_campaign">utm_campaign</Label>
                <input
                    id="utm_campaign"
                    v-model="form.utm_campaign"
                    :class="inputClass"
                    placeholder="spring-sale"
                />
            </div>

            <div class="grid gap-2">
                <Label for="utm_term">utm_term</Label>
                <input
                    id="utm_term"
                    v-model="form.utm_term"
                    :class="inputClass"
                    placeholder="keyword"
                />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <Label for="utm_content">utm_content</Label>
                <input
                    id="utm_content"
                    v-model="form.utm_content"
                    :class="inputClass"
                    placeholder="button-a"
                />
            </div>
        </div>

        <div class="sm:col-span-2">
            <Button type="submit" :disabled="form.processing">
                <Plus class="size-4" />
                {{ form.processing ? 'Creating…' : 'Create short link' }}
            </Button>
        </div>
    </form>
</template>
