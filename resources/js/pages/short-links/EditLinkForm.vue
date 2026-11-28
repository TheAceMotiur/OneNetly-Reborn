<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type EditableLink = {
    id: number;
    title: string | null;
    slug: string;
    destination: string;
    mobile_destination: string | null;
    protected: boolean;
    expires_at: string | null;
    max_clicks: number | null;
    is_active: boolean;
    utm: {
        source: string | null;
        medium: string | null;
        campaign: string | null;
        term: string | null;
        content: string | null;
    };
};

const props = defineProps<{
    link: EditableLink;
}>();

const emit = defineEmits<{
    close: [];
}>();

const inputClass =
    'flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm';

function toLocalInput(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const pad = (part: number): string => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

const form = useForm({
    title: props.link.title ?? '',
    destination: props.link.destination,
    slug: props.link.slug,
    mobile_destination: props.link.mobile_destination ?? '',
    password: '',
    remove_password: false,
    expires_at: toLocalInput(props.link.expires_at),
    max_clicks:
        props.link.max_clicks !== null ? String(props.link.max_clicks) : '',
    is_active: props.link.is_active,
    utm_source: props.link.utm.source ?? '',
    utm_medium: props.link.utm.medium ?? '',
    utm_campaign: props.link.utm.campaign ?? '',
    utm_term: props.link.utm.term ?? '',
    utm_content: props.link.utm.content ?? '',
});

function submit(): void {
    form.patch(`/short-links/${props.link.id}`, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <form
        class="grid gap-4 border-t bg-muted/30 p-4 sm:grid-cols-2"
        @submit.prevent="submit"
    >
        <div class="grid gap-2 sm:col-span-2">
            <Label for="edit-destination">Destination URL</Label>
            <input id="edit-destination" v-model="form.destination" :class="inputClass" />
            <InputError :message="form.errors.destination" />
        </div>

        <div class="grid gap-2">
            <Label for="edit-title">Label</Label>
            <input id="edit-title" v-model="form.title" :class="inputClass" />
            <InputError :message="form.errors.title" />
        </div>

        <div class="grid gap-2">
            <Label for="edit-slug">Slug</Label>
            <input id="edit-slug" v-model="form.slug" :class="inputClass" />
            <InputError :message="form.errors.slug" />
        </div>

        <div class="grid gap-2">
            <Label for="edit-mobile">Mobile destination</Label>
            <input
                id="edit-mobile"
                v-model="form.mobile_destination"
                :class="inputClass"
            />
            <InputError :message="form.errors.mobile_destination" />
        </div>

        <div class="grid gap-2">
            <Label for="edit-expires">Expires</Label>
            <input
                id="edit-expires"
                v-model="form.expires_at"
                :class="inputClass"
                type="datetime-local"
            />
            <InputError :message="form.errors.expires_at" />
        </div>

        <div class="grid gap-2">
            <Label for="edit-limit">Click limit</Label>
            <input
                id="edit-limit"
                v-model="form.max_clicks"
                :class="inputClass"
                type="number"
                min="1"
            />
            <InputError :message="form.errors.max_clicks" />
        </div>

        <div class="grid gap-2">
            <Label for="edit-password">Password</Label>
            <input
                id="edit-password"
                v-model="form.password"
                :class="inputClass"
                type="password"
                autocomplete="new-password"
            />
            <InputError :message="form.errors.password" />
            <label v-if="props.link.protected" class="flex items-center gap-2 text-xs">
                <input
                    v-model="form.remove_password"
                    type="checkbox"
                    class="size-4 rounded border-input"
                />
                Remove the password
            </label>
        </div>

        <div class="grid gap-2">
            <Label for="edit-source">utm_source</Label>
            <input id="edit-source" v-model="form.utm_source" :class="inputClass" />
        </div>

        <div class="grid gap-2">
            <Label for="edit-medium">utm_medium</Label>
            <input id="edit-medium" v-model="form.utm_medium" :class="inputClass" />
        </div>

        <div class="grid gap-2">
            <Label for="edit-campaign">utm_campaign</Label>
            <input id="edit-campaign" v-model="form.utm_campaign" :class="inputClass" />
        </div>

        <div class="grid gap-2">
            <Label for="edit-term">utm_term</Label>
            <input id="edit-term" v-model="form.utm_term" :class="inputClass" />
        </div>

        <div class="grid gap-2 sm:col-span-2">
            <Label for="edit-content">utm_content</Label>
            <input id="edit-content" v-model="form.utm_content" :class="inputClass" />
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input
                v-model="form.is_active"
                type="checkbox"
                class="size-4 rounded border-input"
            />
            Active
        </label>

        <div class="flex items-center gap-2 sm:col-span-2">
            <Button type="submit" :disabled="form.processing">Save changes</Button>
            <Button type="button" variant="ghost" @click="emit('close')">Cancel</Button>
        </div>
    </form>
</template>
