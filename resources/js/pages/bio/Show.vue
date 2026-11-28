<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Download, Mail } from '@lucide/vue';
import { computed, ref } from 'vue';
import SocialIcon from '@/components/SocialIcon.vue';
import { linkIcon } from '@/lib/link-icons';

type PublicProfile = {
    username: string;
    display_name: string;
    bio: string | null;
    avatar_url: string | null;
    theme: string;
    template: string;
    share_url: string;
    share_image: string | null;
    seo_title: string;
    seo_description: string;
    business_type: string | null;
    contact_enabled: boolean;
    has_vcard: boolean;
    vcard_url: string;
    show_branding: boolean;
};

type PublicLink = {
    id: number;
    title: string;
    url: string;
    icon: string;
};

type PublicBlock = {
    id: number;
    type: string;
    content: string | null;
    media_url: string | null;
    embed_url: string | null;
};

type PublicSocial = {
    id: number;
    platform: string;
    label: string;
    url: string;
    href: string;
};

type TemplateStyle = {
    page: string;
    muted: string;
    button: string;
    social: string;
    container: string;
    heading: string;
    avatar: string;
};

const props = defineProps<{
    profile: PublicProfile;
    links: PublicLink[];
    blocks: PublicBlock[];
    socials: PublicSocial[];
}>();

const subscriberForm = useForm({ email: '' });

function subscribe(): void {
    subscriberForm.post(`/subscribe/${encodeURIComponent(props.profile.username)}`, {
        preserveScroll: true,
        onSuccess: () => subscriberForm.reset(),
    });
}

const accents: Record<string, string> = {
    indigo: 'bg-indigo-500',
    emerald: 'bg-emerald-500',
    rose: 'bg-rose-500',
    amber: 'bg-amber-500',
    sky: 'bg-sky-500',
    slate: 'bg-slate-500',
};

const templates: Record<string, TemplateStyle> = {
    classic: {
        page: 'bg-muted/40 text-foreground',
        muted: 'text-muted-foreground',
        button: 'border bg-background hover:shadow-md',
        social: 'border bg-background hover:shadow-md',
        container: 'max-w-lg',
        heading: 'text-2xl font-semibold tracking-tight',
        avatar: 'rounded-full',
    },
    gradient: {
        page: 'bg-gradient-to-br from-indigo-100 via-fuchsia-50 to-sky-100 text-slate-900 dark:from-indigo-950 dark:via-slate-950 dark:to-sky-950 dark:text-slate-100',
        muted: 'text-slate-600 dark:text-slate-400',
        button: 'border border-white/60 bg-white/70 backdrop-blur hover:bg-white dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10',
        social: 'border border-white/60 bg-white/70 backdrop-blur hover:bg-white dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10',
        container: 'max-w-lg',
        heading: 'text-2xl font-semibold tracking-tight',
        avatar: 'rounded-full',
    },
    dark: {
        page: 'bg-slate-950 text-slate-100',
        muted: 'text-slate-400',
        button: 'border border-slate-800 bg-slate-900 hover:bg-slate-800',
        social: 'border border-slate-800 bg-slate-900 hover:bg-slate-800',
        container: 'max-w-lg',
        heading: 'text-2xl font-semibold tracking-tight',
        avatar: 'rounded-full',
    },
    soft: {
        page: 'bg-amber-50 text-stone-900 dark:bg-stone-950 dark:text-stone-100',
        muted: 'text-stone-600 dark:text-stone-400',
        button: 'border border-amber-200 bg-white/80 hover:bg-white dark:border-amber-500/20 dark:bg-stone-900',
        social: 'border border-amber-200 bg-white/80 hover:bg-white dark:border-amber-500/20 dark:bg-stone-900',
        container: 'max-w-lg',
        heading: 'text-2xl font-semibold tracking-tight',
        avatar: 'rounded-full',
    },
    minimal: {
        page: 'bg-white text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100',
        muted: 'text-zinc-500 dark:text-zinc-400',
        button: 'border border-zinc-200 bg-white shadow-none hover:border-zinc-400 hover:shadow-sm dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-600',
        social: 'border border-zinc-200 bg-white hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:bg-zinc-800',
        container: 'max-w-md',
        heading: 'text-3xl font-light tracking-tight',
        avatar: 'rounded-2xl',
    },
    aurora: {
        page: 'bg-gradient-to-b from-slate-950 via-indigo-950 to-slate-900 text-white',
        muted: 'text-slate-300',
        button: 'border border-white/15 bg-white/5 backdrop-blur hover:border-cyan-300/60 hover:bg-white/10',
        social: 'border border-white/15 bg-white/5 backdrop-blur hover:border-cyan-300/60 hover:bg-white/10',
        container: 'max-w-lg',
        heading: 'text-3xl font-semibold tracking-tight',
        avatar: 'rounded-full ring-2 ring-cyan-300/50',
    },
    paper: {
        page: 'bg-[#f5f0e6] text-stone-900 dark:bg-[#1c1917] dark:text-stone-100',
        muted: 'text-stone-600 dark:text-stone-400',
        button: 'rounded-none border-x-0 border-t-0 border-b border-stone-300 bg-transparent px-0 shadow-none hover:border-stone-900 dark:border-stone-700 dark:hover:border-stone-300',
        social: 'border border-stone-300 bg-transparent hover:bg-stone-900 hover:text-white dark:border-stone-700 dark:hover:bg-stone-100 dark:hover:text-stone-900',
        container: 'max-w-xl',
        heading: 'font-serif text-3xl font-medium tracking-tight',
        avatar: 'rounded-full',
    },
    glass: {
        page: 'bg-gradient-to-br from-cyan-500 via-blue-600 to-violet-700 text-white',
        muted: 'text-white/75',
        button: 'border border-white/25 bg-white/10 backdrop-blur-md hover:bg-white/20',
        social: 'border border-white/25 bg-white/10 backdrop-blur-md hover:bg-white/20',
        container: 'max-w-lg',
        heading: 'text-2xl font-semibold tracking-tight',
        avatar: 'rounded-full ring-2 ring-white/40',
    },
};

const accent = computed(() => accents[props.profile.theme] ?? accents.indigo);

const template = computed<TemplateStyle>(
    () => templates[props.profile.template] ?? templates.classic,
);

const initials = computed(() =>
    props.profile.display_name
        .split(' ')
        .map((part) => part.charAt(0))
        .join('')
        .slice(0, 2)
        .toUpperCase(),
);

const appName = computed(() => String(usePage().props.name ?? ''));

const contactForm = useForm({
    name: '',
    email: '',
    message: '',
    website: '',
});

const contactSent = ref(false);

function sendMessage(): void {
    contactForm.post(
        `/${encodeURIComponent(props.profile.username)}/contact`,
        {
            preserveScroll: true,
            onSuccess: () => {
                contactForm.reset('name', 'email', 'message', 'website');
                contactSent.value = true;
            },
        },
    );
}
</script>

<template>
    <Head :title="props.profile.seo_title">
        <meta name="description" :content="props.profile.seo_description" />
        <meta property="og:type" content="profile" />
        <meta property="og:title" :content="props.profile.seo_title" />
        <meta property="og:description" :content="props.profile.seo_description" />
        <meta property="og:url" :content="props.profile.share_url" />
        <meta
            v-if="props.profile.share_image"
            property="og:image"
            :content="props.profile.share_image"
        />
        <meta
            name="twitter:card"
            :content="
                props.profile.share_image ? 'summary_large_image' : 'summary'
            "
        />
        <meta name="twitter:title" :content="props.profile.seo_title" />
        <meta name="twitter:description" :content="props.profile.seo_description" />
    </Head>

    <div :class="[template.page]" class="min-h-screen">
        <div
            :class="[template.container]"
            class="mx-auto flex min-h-screen w-full flex-col px-5 py-14"
        >
            <header class="flex flex-col items-center gap-4 text-center">
                <div
                    :class="[accent, template.avatar]"
                    class="flex size-24 items-center justify-center overflow-hidden text-2xl font-semibold text-white shadow-lg"
                >
                    <img
                        v-if="props.profile.avatar_url"
                        :src="props.profile.avatar_url"
                        :alt="props.profile.display_name"
                        class="size-full object-cover"
                    />
                    <span v-else>{{ initials }}</span>
                </div>

                <div class="space-y-1">
                    <h1 :class="[template.heading]">
                        {{ props.profile.display_name }}
                    </h1>
                    <p :class="[template.muted]" class="text-sm">
                        @{{ props.profile.username }}
                    </p>
                    <p
                        v-if="props.profile.business_type"
                        :class="[template.muted]"
                        class="text-[10px] font-semibold tracking-[0.2em] uppercase"
                    >
                        {{ props.profile.business_type }}
                    </p>
                </div>

                <p
                    v-if="props.profile.bio"
                    :class="[template.muted]"
                    class="max-w-md text-sm leading-relaxed"
                >
                    {{ props.profile.bio }}
                </p>

                <a
                    v-if="props.profile.has_vcard"
                    :href="props.profile.vcard_url"
                    :class="[template.button]"
                    class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-xs font-medium shadow-sm transition hover:-translate-y-0.5"
                >
                    <Download class="size-3.5" />
                    Save contact
                </a>
            </header>

            <main class="mt-10 flex flex-col gap-3">
                <template v-for="block in props.blocks" :key="block.id">
                    <h2
                        v-if="block.type === 'heading'"
                        :class="[template.heading]"
                        class="mt-2 text-center text-xl"
                    >
                        {{ block.content }}
                    </h2>
                    <p
                        v-else-if="block.type === 'text'"
                        :class="[template.muted]"
                        class="text-center text-sm leading-relaxed whitespace-pre-line"
                    >
                        {{ block.content }}
                    </p>
                    <hr
                        v-else-if="block.type === 'divider'"
                        :class="[template.muted]"
                        class="my-2 border-current opacity-30"
                    />
                    <img
                        v-else-if="block.type === 'image' && block.media_url"
                        :src="block.media_url"
                        alt=""
                        class="w-full rounded-xl border object-cover"
                        loading="lazy"
                    />
                    <div
                        v-else-if="block.type === 'video' && block.embed_url"
                        class="aspect-video w-full overflow-hidden rounded-xl border"
                    >
                        <iframe
                            :src="block.embed_url"
                            :title="`Video from ${props.profile.display_name}`"
                            class="size-full"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        />
                    </div>
                </template>

                <div
                    v-if="props.socials.length"
                    class="mb-3 flex flex-wrap items-center justify-center gap-2"
                >
                    <a
                        v-for="social in props.socials"
                        :key="social.id"
                        :href="social.href"
                        target="_blank"
                        rel="noopener noreferrer"
                        :aria-label="social.label"
                        :title="social.label"
                        :class="[template.social]"
                        class="flex size-10 items-center justify-center rounded-full shadow-sm transition hover:-translate-y-0.5"
                    >
                        <SocialIcon :name="social.platform" />
                    </a>
                </div>

                <a
                    v-for="link in props.links"
                    :key="link.id"
                    :href="link.url"
                    :class="[template.button]"
                    class="group flex w-full items-center gap-3 rounded-xl px-5 py-4 text-sm font-medium shadow-sm transition hover:-translate-y-0.5"
                >
                    <component
                        :is="linkIcon(link.icon)"
                        class="size-4 shrink-0 opacity-70"
                    />
                    <span class="flex-1 truncate text-left">{{
                        link.title
                    }}</span>
                    <span
                        :class="[template.muted]"
                        class="transition group-hover:translate-x-0.5"
                    >
                        &rarr;
                    </span>
                </a>

                <p
                    v-if="props.links.length === 0"
                    :class="[template.muted]"
                    class="rounded-xl border border-dashed px-5 py-10 text-center text-sm"
                >
                    No links yet.
                </p>

                <section
                    v-if="props.profile.contact_enabled"
                    :class="[template.button]"
                    class="mt-4 rounded-xl p-5 text-left shadow-sm"
                >
                    <div v-if="!contactSent">
                        <h2 class="flex items-center gap-2 text-sm font-semibold">
                            <Mail class="size-4" />
                            Send a message
                        </h2>
                        <p :class="[template.muted]" class="mt-1 text-xs">
                            Your message goes straight to
                            {{ props.profile.display_name }}'s inbox.
                        </p>

                        <form
                            class="mt-4 space-y-3"
                            @submit.prevent="sendMessage"
                        >
                            <div class="grid gap-1">
                                <input
                                    v-model="contactForm.name"
                                    type="text"
                                    placeholder="Your name"
                                    autocomplete="name"
                                    class="w-full rounded-lg border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground"
                                />
                                <p
                                    v-if="contactForm.errors.name"
                                    class="text-xs text-red-500"
                                >
                                    {{ contactForm.errors.name }}
                                </p>
                            </div>
                            <div class="grid gap-1">
                                <input
                                    v-model="contactForm.email"
                                    type="email"
                                    placeholder="Your email"
                                    autocomplete="email"
                                    class="w-full rounded-lg border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground"
                                />
                                <p
                                    v-if="contactForm.errors.email"
                                    class="text-xs text-red-500"
                                >
                                    {{ contactForm.errors.email }}
                                </p>
                            </div>
                            <div class="grid gap-1">
                                <textarea
                                    v-model="contactForm.message"
                                    rows="4"
                                    placeholder="Your message"
                                    class="w-full rounded-lg border bg-background px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground"
                                />
                                <p
                                    v-if="contactForm.errors.message"
                                    class="text-xs text-red-500"
                                >
                                    {{ contactForm.errors.message }}
                                </p>
                            </div>

                            <div class="hidden" aria-hidden="true">
                                <label for="contact-website">Website</label>
                                <input
                                    id="contact-website"
                                    v-model="contactForm.website"
                                    type="text"
                                    tabindex="-1"
                                    autocomplete="off"
                                />
                            </div>

                            <button
                                type="submit"
                                :class="[accent]"
                                class="w-full rounded-lg px-4 py-2 text-sm font-medium text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="contactForm.processing"
                            >
                                {{
                                    contactForm.processing
                                        ? 'Sending…'
                                        : 'Send message'
                                }}
                            </button>
                        </form>
                    </div>

                    <div v-else class="py-4 text-center">
                        <p class="text-sm font-semibold">Message sent</p>
                        <p :class="[template.muted]" class="mt-1 text-xs">
                            Thanks! Your message was delivered.
                        </p>
                    </div>
                </section>

                <section class="mt-6 rounded-2xl border p-5">
                    <div class="flex items-center gap-2">
                        <Mail class="size-4 opacity-70" />
                        <p class="text-sm font-semibold">Get updates</p>
                    </div>
                    <p :class="[template.muted]" class="mt-1 text-xs">
                        Join the email list for news and offers.
                    </p>
                    <form class="mt-3 flex gap-2" @submit.prevent="subscribe">
                        <input
                            v-model="subscriberForm.email"
                            type="email"
                            required
                            maxlength="120"
                            placeholder="you@example.com"
                            class="min-w-0 flex-1 rounded-lg border bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring"
                        />
                        <button
                            type="submit"
                            :disabled="subscriberForm.processing"
                            class="rounded-lg bg-foreground px-3 py-2 text-sm font-semibold text-background disabled:opacity-50"
                        >
                            Join
                        </button>
                    </form>
                    <p v-if="subscriberForm.errors.email" class="mt-2 text-xs text-red-500">
                        {{ subscriberForm.errors.email }}
                    </p>
                </section>
            </main>

            <footer
                v-if="props.profile.show_branding"
                :class="[template.muted]"
                class="mt-auto pt-12 text-center text-xs"
            >
                Powered by {{ appName }}
            </footer>
        </div>
    </div>
</template>
