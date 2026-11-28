<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type {
    ResumeCertification,
    ResumeContent,
    ResumeEducation,
    ResumeExperience,
    ResumeLanguage,
    ResumeProject,
    ResumeSkillGroup,
} from '@/types/resume';

const props = withDefaults(
    defineProps<{
        content: ResumeContent;
        template?: string;
        accent?: string;
        font?: string;
        watermark?: boolean;
    }>(),
    {
        template: 'classic',
        accent: '#4f46e5',
        font: 'sans',
        watermark: false,
    },
);

const isFilled = (value: string | null | undefined): boolean =>
    typeof value === 'string' && value.trim().length > 0;

const fontFamily = computed(() =>
    props.font === 'serif'
        ? "Georgia, 'Times New Roman', serif"
        : "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
);

const basics = computed(() => props.content.basics);

const appName = computed(() => String(usePage().props.name ?? 'OneNetly'));

const experience = computed<ResumeExperience[]>(() =>
    props.content.experience.filter(
        (entry) =>
            isFilled(entry.title) ||
            isFilled(entry.company) ||
            isFilled(entry.bullets),
    ),
);

const education = computed<ResumeEducation[]>(() =>
    props.content.education.filter(
        (entry) => isFilled(entry.degree) || isFilled(entry.school),
    ),
);

const skills = computed<ResumeSkillGroup[]>(() =>
    props.content.skills.filter(
        (entry) => isFilled(entry.group) || isFilled(entry.items),
    ),
);

const projects = computed<ResumeProject[]>(() =>
    props.content.projects.filter(
        (entry) => isFilled(entry.name) || isFilled(entry.description),
    ),
);

const certifications = computed<ResumeCertification[]>(() =>
    props.content.certifications.filter((entry) => isFilled(entry.name)),
);

const languages = computed<ResumeLanguage[]>(() =>
    props.content.languages.filter((entry) => isFilled(entry.name)),
);

type ContactItem = {
    label: string;
    href: string | null;
};

const withProtocol = (value: string): string => {
    const trimmed = value.trim();

    return /^https?:\/\//i.test(trimmed)
        ? trimmed
        : `https://${trimmed.replace(/^\/+/, '')}`;
};

const withoutProtocol = (value: string): string =>
    value
        .trim()
        .replace(/^https?:\/\//i, '')
        .replace(/\/+$/, '');

const contactItems = computed<ContactItem[]>(() => {
    const items: ContactItem[] = [];
    const email = basics.value.email.trim();
    const phone = basics.value.phone.trim();
    const location = basics.value.location.trim();
    const website = basics.value.website.trim();
    const linkedin = basics.value.linkedin.trim();

    if (isFilled(email)) {
        items.push({ label: email, href: `mailto:${email}` });
    }

    if (isFilled(phone)) {
        items.push({ label: phone, href: `tel:${phone.replace(/[^\d+]/g, '')}` });
    }

    if (isFilled(location)) {
        items.push({ label: location, href: null });
    }

    if (isFilled(website)) {
        items.push({
            label: withoutProtocol(website),
            href: withProtocol(website),
        });
    }

    if (isFilled(linkedin)) {
        items.push({
            label: withoutProtocol(linkedin),
            href: withProtocol(linkedin),
        });
    }

    return items;
});

const hasSummary = computed(() => isFilled(basics.value.summary));

const isTwoColumn = computed(() => props.template === 'sidebar');
const isBand = computed(() => props.template === 'modern');
const isCompact = computed(() => props.template === 'technical');
const isCentered = computed(() =>
    ['classic', 'executive'].includes(props.template),
);

const headerNameClass = computed(() => {
    switch (props.template) {
        case 'modern':
            return 'text-[30px] leading-tight font-semibold tracking-tight text-white';
        case 'executive':
            return 'text-[29px] leading-tight font-semibold tracking-[0.08em] uppercase';
        case 'technical':
            return 'text-[26px] leading-tight font-bold tracking-tight';
        case 'minimal':
            return 'text-[32px] leading-tight font-medium tracking-tight';
        case 'classic':
            return 'text-[27px] leading-tight font-bold tracking-[0.14em] uppercase';
        default:
            return 'text-[27px] leading-tight font-semibold tracking-tight';
    }
});

const sectionTitleClass = computed(() => {
    switch (props.template) {
        case 'modern':
            return 'text-[10.5px] font-bold tracking-[0.2em] uppercase';
        case 'executive':
            return 'border-b border-slate-800 pb-1 text-center text-[10.5px] font-semibold tracking-[0.24em] uppercase';
        case 'minimal':
            return 'text-[9.5px] font-semibold tracking-[0.26em] uppercase text-slate-500';
        case 'technical':
            return 'border-l-2 pl-2 text-[10.5px] font-bold tracking-[0.14em] uppercase';
        default:
            return 'border-b border-slate-300 pb-1 text-[10.5px] font-bold tracking-[0.2em] uppercase text-slate-700';
    }
});

const sectionTitleStyle = computed(() => {
    if (props.template === 'modern') {
        return { color: props.accent };
    }

    if (props.template === 'technical') {
        return { borderColor: props.accent };
    }

    return {};
});

const sidebarTitleClass =
    'text-[9px] font-bold tracking-[0.22em] uppercase text-white/65';

const sectionGap = computed(() =>
    isCompact.value ? 'mb-3.5' : isTwoColumn.value ? 'mb-5' : 'mb-6',
);

const period = (start: string, end: string, current: boolean): string => {
    const from = start.trim();
    const to = current ? 'Present' : end.trim();

    if (!from && !to) {
        return '';
    }

    if (!from) {
        return to;
    }

    if (!to) {
        return from;
    }

    return `${from} — ${to}`;
};

const bulletLines = (value: string): string[] =>
    value
        .split('\n')
        .map((line) => line.replace(/^\s*[-•*]\s*/, '').trim())
        .filter((line) => line.length > 0);
</script>

<template>
    <div
        class="resume-sheet flex flex-col bg-white text-slate-900"
        :style="{
            width: '794px',
            minHeight: '1123px',
            fontFamily,
        }"
    >
        <div class="flex min-h-[1123px] flex-1">
            <aside
                v-if="isTwoColumn"
                class="w-[250px] shrink-0 px-7 py-9"
                :style="{ backgroundColor: accent }"
            >
                <p class="text-[22px] leading-tight font-semibold text-white">
                    {{ basics.name || 'Your name' }}
                </p>
                <p
                    v-if="isFilled(basics.headline)"
                    class="mt-1 text-[11.5px] leading-snug text-white/85"
                >
                    {{ basics.headline }}
                </p>

                <div
                    v-if="contactItems.length"
                    class="mt-6 space-y-1.5 text-[10.5px] leading-relaxed break-words text-white/90"
                >
                    <p
                        v-for="(item, index) in contactItems"
                        :key="index"
                    >
                        <a
                            v-if="item.href"
                            :href="item.href"
                            class="underline underline-offset-2"
                            >{{ item.label }}</a
                        >
                        <template v-else>{{ item.label }}</template>
                    </p>
                </div>

                <div v-if="skills.length" class="mt-7">
                    <h2 :class="sidebarTitleClass">Skills</h2>
                    <div
                        v-for="(group, index) in skills"
                        :key="index"
                        class="mt-2"
                    >
                        <p
                            v-if="isFilled(group.group)"
                            class="text-[10.5px] font-semibold text-white"
                        >
                            {{ group.group }}
                        </p>
                        <p
                            class="text-[10.5px] leading-relaxed text-white/85"
                        >
                            {{ group.items }}
                        </p>
                    </div>
                </div>

                <div v-if="languages.length" class="mt-7">
                    <h2 :class="sidebarTitleClass">Languages</h2>
                    <p
                        v-for="(language, index) in languages"
                        :key="index"
                        class="mt-1.5 text-[10.5px] text-white/90"
                    >
                        {{ language.name
                        }}<span v-if="isFilled(language.level)" class="text-white/60">
                            · {{ language.level }}</span
                        >
                    </p>
                </div>

                <div v-if="certifications.length" class="mt-7">
                    <h2 :class="sidebarTitleClass">Certifications</h2>
                    <div
                        v-for="(item, index) in certifications"
                        :key="index"
                        class="mt-2 text-[10.5px] leading-snug text-white/90"
                    >
                        <p class="font-medium text-white">{{ item.name }}</p>
                        <p v-if="isFilled(item.issuer) || isFilled(item.year)">
                            {{ [item.issuer, item.year].filter(isFilled).join(' · ') }}
                        </p>
                    </div>
                </div>
            </aside>

            <div
                class="min-w-0 flex-1"
                :class="isTwoColumn ? 'px-9 py-9' : 'px-12 py-10'"
            >
                <header
                    v-if="!isTwoColumn"
                    :class="[
                        isBand ? '-mx-12 -mt-10 mb-6 px-12 py-8' : '',
                        isCentered ? 'text-center' : '',
                    ]"
                    :style="isBand ? { backgroundColor: accent } : {}"
                >
                    <p :class="headerNameClass">
                        {{ basics.name || 'Your name' }}
                    </p>
                    <p
                        v-if="isFilled(basics.headline)"
                        class="mt-1 text-[12.5px]"
                        :class="
                            isBand
                                ? 'text-white/85'
                                : 'tracking-[0.02em] text-slate-600'
                        "
                    >
                        {{ basics.headline }}
                    </p>

                    <div
                        v-if="contactItems.length"
                        class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-[10.5px]"
                        :class="[
                            isCentered ? 'justify-center' : '',
                            isBand ? 'text-white/85' : 'text-slate-600',
                        ]"
                    >
                        <span
                            v-for="(item, index) in contactItems"
                            :key="index"
                            class="flex items-center gap-3"
                        >
                            <span
                                v-if="index > 0"
                                aria-hidden="true"
                                :class="isBand ? 'text-white/40' : 'text-slate-300'"
                                >•</span
                            >
                            <a
                                v-if="item.href"
                                :href="item.href"
                                class="underline underline-offset-2"
                                >{{ item.label }}</a
                            >
                            <template v-else>{{ item.label }}</template>
                        </span>
                    </div>

                    <div
                        class="mt-4"
                        :class="
                            isBand
                                ? 'border-t border-white/25'
                                : isCompact
                                  ? 'border-t-2'
                                  : 'border-t border-slate-300'
                        "
                        :style="isCompact ? { borderColor: accent } : {}"
                    />
                </header>

                <section v-if="hasSummary" :class="sectionGap">
                    <h2 :class="sectionTitleClass" :style="sectionTitleStyle">
                        Profile
                    </h2>
                    <p
                        class="mt-2 text-[11px] leading-relaxed whitespace-pre-line text-slate-700"
                    >
                        {{ basics.summary }}
                    </p>
                </section>

                <section v-if="experience.length" :class="sectionGap">
                    <h2 :class="sectionTitleClass" :style="sectionTitleStyle">
                        Experience
                    </h2>
                    <div
                        v-for="(entry, index) in experience"
                        :key="index"
                        class="mt-2.5"
                    >
                        <div class="flex items-baseline justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[12px] font-semibold">
                                    {{ entry.title }}
                                </p>
                                <p
                                    v-if="isFilled(entry.company) || isFilled(entry.location)"
                                    class="text-[11px] text-slate-600"
                                >
                                    {{
                                        [entry.company, entry.location]
                                            .filter(isFilled)
                                            .join(' · ')
                                    }}
                                </p>
                            </div>
                            <p
                                class="shrink-0 text-[10px] tracking-tight text-slate-500 tabular-nums"
                            >
                                {{ period(entry.start, entry.end, entry.current) }}
                            </p>
                        </div>
                        <ul
                            v-if="bulletLines(entry.bullets).length"
                            class="mt-1 list-disc space-y-0.5 pl-4 text-[10.5px] leading-relaxed text-slate-700"
                        >
                            <li
                                v-for="(line, lineIndex) in bulletLines(
                                    entry.bullets,
                                )"
                                :key="lineIndex"
                            >
                                {{ line }}
                            </li>
                        </ul>
                    </div>
                </section>

                <section v-if="education.length" :class="sectionGap">
                    <h2 :class="sectionTitleClass" :style="sectionTitleStyle">
                        Education
                    </h2>
                    <div
                        v-for="(entry, index) in education"
                        :key="index"
                        class="mt-2.5 flex items-baseline justify-between gap-3"
                    >
                        <div class="min-w-0">
                            <p class="text-[12px] font-semibold">
                                {{ entry.degree }}
                            </p>
                            <p
                                v-if="isFilled(entry.school) || isFilled(entry.location)"
                                class="text-[11px] text-slate-600"
                            >
                                {{
                                    [entry.school, entry.location]
                                        .filter(isFilled)
                                        .join(' · ')
                                }}
                            </p>
                            <p
                                v-if="isFilled(entry.details)"
                                class="mt-0.5 text-[10.5px] leading-relaxed text-slate-600"
                            >
                                {{ entry.details }}
                            </p>
                        </div>
                        <p
                            class="shrink-0 text-[10px] text-slate-500 tabular-nums"
                        >
                            {{ period(entry.start, entry.end, false) }}
                        </p>
                    </div>
                </section>

                <section v-if="projects.length" :class="sectionGap">
                    <h2 :class="sectionTitleClass" :style="sectionTitleStyle">
                        Projects
                    </h2>
                    <div
                        v-for="(entry, index) in projects"
                        :key="index"
                        class="mt-2.5"
                    >
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="text-[12px] font-semibold">
                                {{ entry.name }}
                            </p>
                            <a
                                v-if="isFilled(entry.link)"
                                :href="withProtocol(entry.link)"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="shrink-0 text-[10px] text-slate-500 underline underline-offset-2"
                            >
                                {{ withoutProtocol(entry.link) }}
                            </a>
                        </div>
                        <p
                            v-if="isFilled(entry.description)"
                            class="mt-0.5 text-[10.5px] leading-relaxed text-slate-700"
                        >
                            {{ entry.description }}
                        </p>
                    </div>
                </section>

                <section v-if="!isTwoColumn && skills.length" :class="sectionGap">
                    <h2 :class="sectionTitleClass" :style="sectionTitleStyle">
                        Skills
                    </h2>
                    <div class="mt-2 space-y-1.5">
                        <p
                            v-for="(group, index) in skills"
                            :key="index"
                            class="text-[10.5px] leading-relaxed text-slate-700"
                        >
                            <span
                                v-if="isFilled(group.group)"
                                class="font-semibold text-slate-900"
                                >{{ group.group }}:</span
                            >
                            {{ group.items }}
                        </p>
                    </div>
                </section>

                <section
                    v-if="!isTwoColumn && (certifications.length || languages.length)"
                    :class="sectionGap"
                >
                    <div
                        v-if="certifications.length"
                        class="mb-3"
                    >
                        <h2 :class="sectionTitleClass" :style="sectionTitleStyle">
                            Certifications
                        </h2>
                        <p
                            v-for="(item, index) in certifications"
                            :key="index"
                            class="mt-1.5 text-[10.5px] text-slate-700"
                        >
                            <span class="font-medium">{{ item.name }}</span>
                            <span
                                v-if="isFilled(item.issuer) || isFilled(item.year)"
                                class="text-slate-500"
                            >
                                — {{ [item.issuer, item.year].filter(isFilled).join(', ') }}
                            </span>
                        </p>
                    </div>

                    <div v-if="languages.length">
                        <h2 :class="sectionTitleClass" :style="sectionTitleStyle">
                            Languages
                        </h2>
                        <p class="mt-1.5 text-[10.5px] text-slate-700">
                            <template
                                v-for="(language, index) in languages"
                                :key="index"
                            >
                                <span
                                    v-if="index > 0"
                                    class="text-slate-300"
                                    > · </span
                                >{{ language.name
                                }}<span v-if="isFilled(language.level)" class="text-slate-500">
                                    ({{ language.level }})</span
                                >
                            </template>
                        </p>
                    </div>
                </section>

                <p
                    v-if="watermark"
                    class="mt-6 border-t border-slate-200 pt-1.5 text-center text-[8px] tracking-[0.18em] text-slate-400 uppercase"
                >
                    Made with {{ appName }}
                </p>
            </div>
        </div>
    </div>
</template>
