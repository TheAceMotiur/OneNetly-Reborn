<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ExternalLink,
    LayoutTemplate,
    Lock,
    Pencil,
    Plus,
    Trash2,
    Upload,
    UserPlus,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SocialIcon from '@/components/SocialIcon.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit as bioEdit, update } from '@/routes/bio';
import {
    destroy as destroySocial,
    store as storeSocial,
    update as updateSocial,
} from '@/routes/socials';

type BioProfile = {
    username: string;
    custom_domain: string | null;
    custom_domain_verified: boolean;
    custom_domain_record: string | null;
    display_name: string | null;
    bio: string | null;
    meta_title: string | null;
    meta_description: string | null;
    theme: string;
    template: string;
    business_type: string;
    company: string | null;
    job_title: string | null;
    contact_email: string | null;
    phone: string | null;
    contact_website: string | null;
    address: string | null;
    is_published: boolean;
    show_contact_form: boolean;
    has_password: boolean;
    avatar_url: string | null;
    public_url: string;
};

type PageOption = {
    username: string;
    name: string;
    role: string;
};

type Collaborator = {
    id: number;
    name: string;
    email: string;
    role: string;
};

type SocialItem = {
    id: number;
    platform: string;
    label: string;
    url: string;
    href: string;
};

const props = defineProps<{
    profile: BioProfile;
    canUseCustomDomain: boolean;
    customDomainsEnabled: boolean;
    themes: string[];
    templates: string[];
    businessTypes: Record<string, string>;
    socials: SocialItem[];
    platforms: Record<string, string>;
    pages: PageOption[];
    activePage: string;
    canEdit: boolean;
    canManageCollaborators: boolean;
    collaborators: Collaborator[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My page',
                href: bioEdit(),
            },
        ],
    },
});

const themeSwatches: Record<string, string> = {
    indigo: 'bg-indigo-500',
    emerald: 'bg-emerald-500',
    rose: 'bg-rose-500',
    amber: 'bg-amber-500',
    sky: 'bg-sky-500',
    slate: 'bg-slate-500',
};

const templateLabels: Record<string, string> = {
    classic: 'Classic',
    gradient: 'Gradient',
    dark: 'Dark',
    soft: 'Soft',
    minimal: 'Minimal',
    aurora: 'Aurora',
    paper: 'Editorial',
    glass: 'Glass',
};

const templatePreviews: Record<string, string> = {
    classic: 'bg-gradient-to-b from-muted/60 to-background text-foreground',
    gradient:
        'bg-gradient-to-br from-indigo-100 via-fuchsia-50 to-sky-100 text-slate-900 dark:from-indigo-950 dark:via-slate-950 dark:to-sky-950 dark:text-slate-100',
    dark: 'bg-slate-950 text-slate-100',
    soft: 'bg-amber-50 text-stone-900 dark:bg-stone-950 dark:text-stone-100',
    minimal: 'bg-white text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100',
    aurora: 'bg-gradient-to-b from-slate-950 via-indigo-950 to-slate-900 text-white',
    paper: 'bg-[#f5f0e6] text-stone-900 dark:bg-[#1c1917] dark:text-stone-100',
    glass: 'bg-gradient-to-br from-cyan-500 via-blue-600 to-violet-700 text-white',
};

const formState = reactive({
    username: props.profile.username,
    custom_domain: props.profile.custom_domain ?? '',
    display_name: props.profile.display_name ?? '',
    bio: props.profile.bio ?? '',
    meta_title: props.profile.meta_title ?? '',
    meta_description: props.profile.meta_description ?? '',
    theme: props.profile.theme,
    template: props.profile.template,
    business_type: props.profile.business_type,
    company: props.profile.company ?? '',
    job_title: props.profile.job_title ?? '',
    contact_email: props.profile.contact_email ?? '',
    phone: props.profile.phone ?? '',
    contact_website: props.profile.contact_website ?? '',
    address: props.profile.address ?? '',
    is_published: props.profile.is_published,
    show_contact_form: props.profile.show_contact_form,
    page_password: '',
    remove_page_password: false,
});

const inputClass =
    'flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm';

const avatar = ref<File | null>(null);
const removeAvatarRequested = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(props.profile.avatar_url);
const errors = ref<Record<string, string>>({});
const processing = ref(false);
const saved = ref(false);

const verifyingDomain = ref(false);

const ownUsername = computed(
    () => props.pages.find((page) => page.role === 'owner')?.username ?? '',
);

const pageSuffix = computed(() =>
    props.activePage === ownUsername.value
        ? ''
        : `?page=${encodeURIComponent(props.activePage)}`,
);

function switchPage(event: Event): void {
    const username = (event.target as HTMLSelectElement).value;

    router.visit(`/bio?page=${encodeURIComponent(username)}`);
}

const collaboratorForm = reactive({ email: '', role: 'editor' });
const collaboratorErrors = ref<Record<string, string>>({});
const addingCollaborator = ref(false);

function addCollaborator(): void {
    router.post(
        `/bio/collaborators${pageSuffix.value}`,
        { ...collaboratorForm },
        {
            preserveScroll: true,
            onStart: () => {
                addingCollaborator.value = true;
            },
            onFinish: () => {
                addingCollaborator.value = false;
            },
            onError: (value) => {
                collaboratorErrors.value = value;
            },
            onSuccess: () => {
                collaboratorErrors.value = {};
                collaboratorForm.email = '';
                collaboratorForm.role = 'editor';
            },
        },
    );
}

function removeCollaborator(member: Collaborator): void {
    if (!confirm(`Remove ${member.name} from this page?`)) {
        return;
    }

    router.delete(`/bio/collaborators/${member.id}${pageSuffix.value}`, {
        preserveScroll: true,
    });
}

function verifyDomain(): void {
    router.post(
        '/bio/domain/verify',
        {},
        {
            preserveScroll: true,
            onStart: () => {
                verifyingDomain.value = true;
            },
            onFinish: () => {
                verifyingDomain.value = false;
            },
        },
    );
}

const socialDraft = reactive({
    platform: 'instagram',
    url: '',
});

const socialErrors = ref<Record<string, string>>({});
const addingSocial = ref(false);

function addSocial(): void {
    router.post(
        `${storeSocial.url()}${pageSuffix.value}`,
        {
            platform: socialDraft.platform,
            url: socialDraft.url,
        },
        {
            preserveScroll: true,
            onStart: () => {
                addingSocial.value = true;
            },
            onFinish: () => {
                addingSocial.value = false;
            },
            onError: (formErrors) => {
                socialErrors.value = formErrors;
            },
            onSuccess: () => {
                socialErrors.value = {};
                socialDraft.url = '';
            },
        },
    );
}

function removeSocial(social: SocialItem): void {
    router.delete(`${destroySocial.url(social.id)}${pageSuffix.value}`, {
        preserveScroll: true,
    });
}

const editingSocialId = ref<number | null>(null);
const socialEditErrors = ref<Record<string, string>>({});
const savingSocial = ref(false);

const socialEdit = reactive({
    platform: 'instagram',
    url: '',
});

function startEditingSocial(social: SocialItem): void {
    editingSocialId.value = social.id;
    socialEdit.platform = social.platform;
    socialEdit.url = social.url;
    socialEditErrors.value = {};
}

function cancelEditingSocial(): void {
    editingSocialId.value = null;
    socialEditErrors.value = {};
}

function saveSocial(social: SocialItem): void {
    router.patch(
        `${updateSocial.url(social.id)}${pageSuffix.value}`,
        {
            platform: socialEdit.platform,
            url: socialEdit.url,
        },
        {
            preserveScroll: true,
            onStart: () => {
                savingSocial.value = true;
            },
            onFinish: () => {
                savingSocial.value = false;
            },
            onError: (formErrors) => {
                socialEditErrors.value = formErrors;
            },
            onSuccess: () => {
                socialEditErrors.value = {};
                editingSocialId.value = null;
            },
        },
    );
}

const accent = computed(
    () => themeSwatches[formState.theme] ?? themeSwatches.indigo,
);

const initials = computed(() =>
    (formState.display_name || formState.username)
        .split(' ')
        .map((part) => part.charAt(0))
        .join('')
        .slice(0, 2)
        .toUpperCase(),
);

function onAvatarSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    avatar.value = file;
    removeAvatarRequested.value = false;
    previewUrl.value = file
        ? URL.createObjectURL(file)
        : props.profile.avatar_url;
}

function removeAvatar(): void {
    avatar.value = null;
    removeAvatarRequested.value = true;
    previewUrl.value = null;

    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function submit(): void {
    const data: Record<string, string | boolean | File> = {
        ...formState,
        remove_avatar: removeAvatarRequested.value ? '1' : '0',
    };

    if (avatar.value) {
        data.avatar = avatar.value;
    }

    router.patch(`${update.url()}${pageSuffix.value}`, data, {
        forceFormData: avatar.value !== null,
        preserveScroll: true,
        onStart: () => {
            processing.value = true;
            saved.value = false;
        },
        onFinish: () => {
            processing.value = false;
        },
        onError: (formErrors) => {
            errors.value = formErrors;
        },
        onSuccess: () => {
            errors.value = {};
            saved.value = true;
            avatar.value = null;
            removeAvatarRequested.value = false;
            formState.page_password = '';
            formState.remove_page_password = false;
        },
    });
}
</script>

<template>
    <Head title="My page" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="My page"
                description="Manage the bio page visitors see when they open your link"
            />
            <div class="flex flex-wrap items-center gap-2">
                <select
                    v-if="props.pages.length > 1"
                    :value="props.activePage"
                    class="h-9 max-w-56 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring"
                    aria-label="Switch page"
                    @change="switchPage"
                >
                    <option
                        v-for="page in props.pages"
                        :key="page.username"
                        :value="page.username"
                    >
                        {{ page.name }} ({{ page.role }})
                    </option>
                </select>
                <Button variant="outline" as-child>
                    <a :href="`/bio/blocks${pageSuffix}`">
                        <LayoutTemplate class="size-4" />
                        Blocks
                    </a>
                </Button>
                <Button variant="outline" as-child>
                    <a :href="props.profile.public_url" target="_blank">
                        <ExternalLink class="size-4" />
                        View page
                    </a>
                </Button>
            </div>
        </div>

        <p
            v-if="!props.canEdit"
            class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm"
        >
            You have view-only access to this page.
        </p>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <form class="space-y-6" @submit.prevent="submit">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Page details</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <div class="grid gap-2">
                            <Label for="username">Username</Label>
                            <Input
                                id="username"
                                :model-value="formState.username"
                                name="username"
                                autocomplete="off"
                                placeholder="your-name"
                                @update:model-value="
                                    formState.username = String($event)
                                "
                            />
                            <p class="text-sm text-muted-foreground">
                                Your page lives at
                                <a
                                    :href="props.profile.public_url"
                                    target="_blank"
                                    class="inline-flex items-center gap-1 font-medium text-foreground underline underline-offset-4"
                                >
                                    {{ props.profile.public_url }}
                                    <ExternalLink class="size-3" />
                                </a>
                            </p>
                            <InputError :message="errors.username" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="custom_domain">Custom domain</Label>
                            <Input
                                id="custom_domain"
                                :model-value="formState.custom_domain"
                                name="custom_domain"
                                autocomplete="off"
                                placeholder="example.com"
                                :disabled="!props.canUseCustomDomain"
                                @update:model-value="
                                    formState.custom_domain = String($event)
                                "
                            />
                            <p
                                v-if="!props.customDomainsEnabled"
                                class="text-sm text-muted-foreground"
                            >
                                Custom domains are temporarily disabled by the
                                site owner.
                            </p>
                            <div
                                v-else-if="props.canUseCustomDomain"
                                class="space-y-3 rounded-lg border bg-muted/30 p-3"
                            >
                                <p class="text-sm text-muted-foreground">
                                    Point the domain's DNS at this server, then
                                    add this TXT record to prove you own it.
                                </p>
                                <p
                                    v-if="props.profile.custom_domain_record"
                                    class="break-all rounded-md border bg-background px-3 py-2 font-mono text-xs"
                                >
                                    {{ props.profile.custom_domain_record }}
                                </p>
                                <div class="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        :disabled="
                                            verifyingDomain ||
                                            !props.profile.custom_domain
                                        "
                                        @click="verifyDomain"
                                    >
                                        {{ verifyingDomain ? 'Checking…' : 'Verify domain' }}
                                    </Button>
                                    <span
                                        v-if="props.profile.custom_domain_verified"
                                        class="text-xs font-medium text-emerald-600"
                                    >
                                        Verified
                                    </span>
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                    >
                                        Not verified yet
                                    </span>
                                </div>
                                <p
                                    v-if="!props.profile.custom_domain"
                                    class="text-xs text-muted-foreground"
                                >
                                    Save a domain first to generate its
                                    verification record.
                                </p>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                Custom domains are available on Pro and Premium
                                plans.
                                <a
                                    href="/pricing"
                                    class="font-medium text-foreground underline underline-offset-4"
                                >
                                    Compare plans
                                </a>
                            </p>
                            <InputError :message="errors.custom_domain" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="display_name">Display name</Label>
                            <Input
                                id="display_name"
                                :model-value="formState.display_name"
                                name="display_name"
                                placeholder="Your name"
                                @update:model-value="
                                    formState.display_name = String($event)
                                "
                            />
                            <InputError :message="errors.display_name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="bio">Bio</Label>
                            <textarea
                                id="bio"
                                v-model="formState.bio"
                                name="bio"
                                rows="3"
                                maxlength="280"
                                placeholder="Tell visitors what you do"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <p class="text-right text-xs text-muted-foreground">
                                {{ formState.bio.length }}/280
                            </p>
                            <InputError :message="errors.bio" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="avatar">Avatar</Label>
                            <div class="flex items-center gap-3">
                                <input
                                    ref="fileInput"
                                    id="avatar"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    class="hidden"
                                    @change="onAvatarSelected"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="fileInput?.click()"
                                >
                                    <Upload class="size-4" />
                                    Choose image
                                </Button>
                                <Button
                                    v-if="previewUrl"
                                    type="button"
                                    variant="ghost"
                                    class="text-destructive"
                                    @click="removeAvatar"
                                >
                                    <Trash2 class="size-4" />
                                    Remove
                                </Button>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                PNG, JPG or WebP up to 2 MB.
                            </p>
                            <InputError :message="errors.avatar" />
                        </div>

                        <div class="grid gap-2">
                            <Label>Accent color</Label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="theme in props.themes"
                                    :key="theme"
                                    type="button"
                                    :aria-label="theme"
                                    :class="[
                                        themeSwatches[theme],
                                        formState.theme === theme
                                            ? 'ring-2 ring-foreground ring-offset-2 ring-offset-background'
                                            : '',
                                    ]"
                                    class="size-8 rounded-full transition"
                                    @click="formState.theme = theme"
                                />
                            </div>
                            <InputError :message="errors.theme" />
                        </div>

                        <div class="grid gap-2">
                            <Label>Page template</Label>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <button
                                    v-for="template in props.templates"
                                    :key="template"
                                    type="button"
                                    :class="[
                                        templatePreviews[template],
                                        formState.template === template
                                            ? 'ring-2 ring-foreground ring-offset-2 ring-offset-background'
                                            : '',
                                    ]"
                                    class="flex h-16 items-end justify-center rounded-lg border px-2 py-2 text-xs font-medium"
                                    @click="formState.template = template"
                                >
                                    {{ templateLabels[template] ?? template }}
                                </button>
                            </div>
                            <InputError :message="errors.template" />
                        </div>

                        <div
                            class="flex items-start gap-3 rounded-lg border p-4"
                        >
                            <input
                                id="is_published"
                                v-model="formState.is_published"
                                type="checkbox"
                                class="mt-0.5 size-4 rounded border-input"
                            />
                            <div class="grid gap-1">
                                <Label for="is_published">Page is public</Label>
                                <p class="text-sm text-muted-foreground">
                                    Turn this off to hide your page from
                                    visitors.
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex items-start gap-3 rounded-lg border p-4"
                        >
                            <input
                                id="show_contact_form"
                                v-model="formState.show_contact_form"
                                type="checkbox"
                                class="mt-0.5 size-4 rounded border-input"
                            />
                            <div class="grid gap-1">
                                <Label for="show_contact_form">
                                    Contact form
                                </Label>
                                <p class="text-sm text-muted-foreground">
                                    Let visitors send you a message straight
                                    from your page. Messages are emailed to
                                    your account address.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Business profile</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <p class="text-sm text-muted-foreground">
                            These details identify your business and power the
                            downloadable contact card on your page.
                        </p>

                        <div class="grid gap-2">
                            <Label for="business_type">Business type</Label>
                            <select
                                id="business_type"
                                v-model="formState.business_type"
                                :class="inputClass"
                            >
                                <option
                                    v-for="(label, typeKey) in props.businessTypes"
                                    :key="typeKey"
                                    :value="typeKey"
                                >
                                    {{ label }}
                                </option>
                            </select>
                            <InputError :message="errors.business_type" />
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="company">Company</Label>
                                <Input
                                    id="company"
                                    v-model="formState.company"
                                    placeholder="Studio name"
                                />
                                <InputError :message="errors.company" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="job_title">Job title</Label>
                                <Input
                                    id="job_title"
                                    v-model="formState.job_title"
                                    placeholder="Founder & designer"
                                />
                                <InputError :message="errors.job_title" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="contact_email">Contact email</Label>
                                <Input
                                    id="contact_email"
                                    v-model="formState.contact_email"
                                    type="email"
                                    placeholder="hello@example.com"
                                />
                                <InputError :message="errors.contact_email" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="phone">Phone</Label>
                                <Input
                                    id="phone"
                                    v-model="formState.phone"
                                    placeholder="+1 555 123 4567"
                                />
                                <InputError :message="errors.phone" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="contact_website">Website</Label>
                                <Input
                                    id="contact_website"
                                    v-model="formState.contact_website"
                                    placeholder="your-site.com"
                                />
                                <InputError :message="errors.contact_website" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="address">Address</Label>
                                <Input
                                    id="address"
                                    v-model="formState.address"
                                    placeholder="123 Main Street, City"
                                />
                                <InputError :message="errors.address" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">
                            Search &amp; sharing
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <p class="text-sm text-muted-foreground">
                            Control how your page appears in search results and
                            when someone shares it.
                        </p>

                        <div class="grid gap-2">
                            <Label for="meta_title">SEO title</Label>
                            <Input
                                id="meta_title"
                                v-model="formState.meta_title"
                                maxlength="70"
                                placeholder="Your name — what you do"
                            />
                            <div
                                class="flex items-center justify-between gap-3 text-xs text-muted-foreground"
                            >
                                <span>Defaults to your display name.</span>
                                <span>{{ formState.meta_title.length }}/70</span>
                            </div>
                            <InputError :message="errors.meta_title" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="meta_description">
                                Meta description
                            </Label>
                            <textarea
                                id="meta_description"
                                v-model="formState.meta_description"
                                rows="3"
                                maxlength="160"
                                placeholder="A short description of you and your links"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <div class="flex items-center justify-between gap-3 text-xs text-muted-foreground">
                                <span>Shown in search snippets.</span>
                                <span>
                                    {{ formState.meta_description.length }}/160
                                </span>
                            </div>
                            <InputError :message="errors.meta_description" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">
                            Page protection
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex items-start gap-3">
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-full border bg-muted/40"
                            >
                                <Lock class="size-4" />
                            </span>
                            <div class="grid gap-1">
                                <p class="text-sm font-medium">
                                    Password protection
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Require visitors to enter a password before
                                    they can see your page, links and contact
                                    form.
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="page_password">
                                {{
                                    props.profile.has_password
                                        ? 'New password'
                                        : 'Password'
                                }}
                            </Label>
                            <Input
                                id="page_password"
                                v-model="formState.page_password"
                                type="password"
                                autocomplete="new-password"
                                :disabled="formState.remove_page_password"
                                :placeholder="
                                    props.profile.has_password
                                        ? 'Leave blank to keep the current password'
                                        : 'At least 6 characters'
                                "
                            />
                            <p class="text-xs text-muted-foreground">
                                Use at least 6 characters. Leave blank to keep
                                the current password.
                            </p>
                            <InputError :message="errors.page_password" />
                        </div>

                        <label
                            v-if="props.profile.has_password"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input
                                v-model="formState.remove_page_password"
                                type="checkbox"
                                class="size-4 rounded border-input"
                            />
                            Remove password protection
                        </label>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Social icons</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p class="text-sm text-muted-foreground">
                            These appear as a row of brand icons above your
                            links so visitors can follow you anywhere.
                        </p>

                        <ul
                            v-if="props.socials.length"
                            class="divide-y rounded-lg border"
                        >
                            <li
                                v-for="social in props.socials"
                                :key="social.id"
                                class="px-3 py-2"
                            >
                                <div
                                    v-if="editingSocialId === social.id"
                                    class="flex flex-wrap items-end gap-2"
                                >
                                    <div class="grid w-44 gap-2">
                                        <Label
                                            :for="`social-platform-${social.id}`"
                                        >
                                            Platform
                                        </Label>
                                        <select
                                            :id="`social-platform-${social.id}`"
                                            v-model="socialEdit.platform"
                                            :class="inputClass"
                                        >
                                            <option
                                                v-for="(
                                                    label, value
                                                ) in props.platforms"
                                                :key="value"
                                                :value="value"
                                            >
                                                {{ label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="grid min-w-0 flex-1 gap-2">
                                        <Label :for="`social-url-${social.id}`">
                                            {{
                                                socialEdit.platform === 'email'
                                                    ? 'Email address'
                                                    : 'Profile link'
                                            }}
                                        </Label>
                                        <input
                                            :id="`social-url-${social.id}`"
                                            v-model="socialEdit.url"
                                            :class="inputClass"
                                            :placeholder="
                                                socialEdit.platform === 'email'
                                                    ? 'you@example.com'
                                                    : 'https://instagram.com/you'
                                            "
                                        />
                                        <InputError
                                            :message="socialEditErrors.url"
                                        />
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            size="sm"
                                            :disabled="savingSocial"
                                            @click="saveSocial(social)"
                                        >
                                            Save
                                        </Button>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            @click="cancelEditingSocial"
                                        >
                                            Cancel
                                        </Button>
                                    </div>
                                </div>

                                <div v-else class="flex items-center gap-3">
                                    <span
                                        class="flex size-8 shrink-0 items-center justify-center rounded-full border bg-muted/40"
                                    >
                                        <SocialIcon :name="social.platform" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium">
                                            {{ social.label }}
                                        </p>
                                        <p
                                            class="truncate text-xs text-muted-foreground"
                                        >
                                            {{ social.url }}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        @click="startEditingSocial(social)"
                                    >
                                        <Pencil class="size-4" />
                                        <span class="sr-only">
                                            Edit {{ social.label }}
                                        </span>
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                        @click="removeSocial(social)"
                                    >
                                        <Trash2 class="size-4" />
                                        <span class="sr-only">
                                            Remove {{ social.label }}
                                        </span>
                                    </Button>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">
                            No social icons yet.
                        </p>

                        <div
                            class="grid gap-3 sm:grid-cols-[190px_minmax(0,1fr)_auto] sm:items-end"
                        >
                            <div class="grid gap-2">
                                <Label for="social-platform">Platform</Label>
                                <select
                                    id="social-platform"
                                    v-model="socialDraft.platform"
                                    :class="inputClass"
                                >
                                    <option
                                        v-for="(
                                            label, value
                                        ) in props.platforms"
                                        :key="value"
                                        :value="value"
                                    >
                                        {{ label }}
                                    </option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <Label for="social-url">
                                    {{
                                        socialDraft.platform === 'email'
                                            ? 'Email address'
                                            : 'Profile link'
                                    }}
                                </Label>
                                <input
                                    id="social-url"
                                    v-model="socialDraft.url"
                                    :class="inputClass"
                                    :placeholder="
                                        socialDraft.platform === 'email'
                                            ? 'you@example.com'
                                            : 'https://instagram.com/you'
                                    "
                                />
                                <InputError :message="socialErrors.url" />
                            </div>
                            <Button
                                type="button"
                                :disabled="addingSocial"
                                @click="addSocial"
                            >
                                <Plus class="size-4" />
                                Add
                            </Button>
                        </div>
                        <InputError :message="socialErrors.platform" />
                    </CardContent>
                </Card>

                <div class="flex items-center gap-3">
                    <Button
                        type="submit"
                        :disabled="processing || !props.canEdit"
                        data-test="save-bio-page"
                    >
                        Save changes
                    </Button>
                    <span v-if="saved" class="text-sm text-muted-foreground">
                        Saved.
                    </span>
                </div>
            </form>

            <div class="lg:sticky lg:top-4 lg:self-start">
                <Card class="overflow-hidden">
                    <CardHeader>
                        <CardTitle class="text-base">Live preview</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            :class="
                                templatePreviews[formState.template] ??
                                templatePreviews.classic
                            "
                            class="flex flex-col items-center gap-3 rounded-xl border px-5 py-8 text-center"
                        >
                            <div
                                :class="[accent]"
                                class="flex size-20 items-center justify-center overflow-hidden rounded-full text-xl font-semibold text-white"
                            >
                                <img
                                    v-if="previewUrl"
                                    :src="previewUrl"
                                    alt=""
                                    class="size-full object-cover"
                                />
                                <span v-else>{{ initials }}</span>
                            </div>
                            <div class="space-y-1">
                                <p class="font-semibold">
                                    {{ formState.display_name || formState.username }}
                                </p>
                                <p class="text-sm opacity-70">
                                    @{{ formState.username }}
                                </p>
                            </div>
                            <p v-if="formState.bio" class="text-sm opacity-70">
                                {{ formState.bio }}
                            </p>
                            <div
                                v-if="props.socials.length"
                                class="mt-1 flex flex-wrap items-center justify-center gap-1.5"
                            >
                                <span
                                    v-for="social in props.socials"
                                    :key="social.id"
                                    class="flex size-7 items-center justify-center rounded-full border bg-background/70"
                                >
                                    <SocialIcon :name="social.platform" />
                                </span>
                            </div>
                            <div class="mt-2 w-full space-y-2">
                                <div
                                    class="rounded-lg border bg-background px-4 py-2.5 text-sm font-medium text-muted-foreground"
                                >
                                    Your links appear here
                                </div>
                                <div
                                    class="rounded-lg border bg-background px-4 py-2.5 text-sm font-medium text-muted-foreground"
                                >
                                    Your links appear here
                                </div>
                            </div>
                        </div>
                        <p
                            v-if="!formState.is_published"
                            class="mt-3 text-center text-xs text-muted-foreground"
                        >
                            Your page is currently hidden from visitors.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Card v-if="props.canManageCollaborators">
            <CardHeader>
                <CardTitle class="text-base">Collaborators</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <p class="text-sm text-muted-foreground">
                    Editors can change this page, its links and its blocks.
                    Viewers can open the management screens without changing
                    anything.
                </p>

                <ul class="divide-y rounded-lg border">
                    <li
                        v-for="member in props.collaborators"
                        :key="member.id"
                        class="flex flex-wrap items-center justify-between gap-3 px-3 py-2.5"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">
                                {{ member.name }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ member.email }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="rounded-full border px-2 py-0.5 text-[11px] font-medium capitalize"
                            >
                                {{ member.role }}
                            </span>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-destructive"
                                @click="removeCollaborator(member)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </li>
                    <li
                        v-if="!props.collaborators.length"
                        class="px-3 py-6 text-center text-sm text-muted-foreground"
                    >
                        No collaborators yet. Invite someone by email to help
                        manage this page.
                    </li>
                </ul>

                <form
                    class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_150px_auto] sm:items-end"
                    @submit.prevent="addCollaborator"
                >
                    <div class="grid gap-2">
                        <Label for="collaborator-email">Email address</Label>
                        <Input
                            id="collaborator-email"
                            v-model="collaboratorForm.email"
                            type="email"
                            placeholder="teammate@example.com"
                        />
                        <InputError :message="collaboratorErrors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="collaborator-role">Role</Label>
                        <select
                            id="collaborator-role"
                            v-model="collaboratorForm.role"
                            :class="inputClass"
                        >
                            <option value="editor">Editor</option>
                            <option value="viewer">Viewer</option>
                        </select>
                    </div>
                    <Button type="submit" :disabled="addingCollaborator">
                        <UserPlus class="size-4" />
                        Invite
                    </Button>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
