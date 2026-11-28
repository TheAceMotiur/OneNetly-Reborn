<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    Check,
    Copy,
    Eye,
    Loader2,
    Plus,
    Printer,
    Share2,
    Trash2,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ResumePreview from '@/components/resume/ResumePreview.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type {
    ResumeContent,
    ResumePayload,
    ResumeTemplate,
} from '@/types/resume';

const props = defineProps<{
    resume: ResumePayload;
    templates: ResumeTemplate[];
    accents: Record<string, string>;
    fonts: Record<string, string>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Resumes', href: '/resumes' },
            { title: 'Builder', href: '/resumes' },
        ],
    },
});

/**
 * Page props arrive as reactive proxies, which structuredClone refuses to
 * copy. The content is plain JSON, so a JSON round trip gives the editor its
 * own editable copy.
 */
const copyContent = (content: ResumeContent): ResumeContent =>
    JSON.parse(JSON.stringify(content)) as ResumeContent;

const form = useForm({
    title: props.resume.title,
    template: props.resume.template,
    accent: props.resume.accent,
    font: props.resume.font,
    content: copyContent(props.resume.content),
});

type EntrySection =
    | 'experience'
    | 'education'
    | 'skills'
    | 'projects'
    | 'certifications'
    | 'languages';

const inputClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring';
const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:border-ring';

const activeTemplate = computed(() =>
    props.templates.find((template) => template.key === form.template),
);

const accentHex = computed(
    () => props.accents[form.accent] ?? props.resume.accent_hex,
);

const copied = ref(false);
const publishing = ref(false);

const errorMessages = computed(() => Object.values(form.errors));

const accentLabel = (key: string): string =>
    key.charAt(0).toUpperCase() + key.slice(1);

function save(): void {
    form.patch(`/resumes/${props.resume.id}`, { preserveScroll: true });
}

/**
 * Save quietly in the background so a closed tab or an accidental
 * navigation never costs the user their work.
 */
const AUTOSAVE_DELAY = 1200;

const autosavedAt = ref<string | null>(null);

let autosaveTimer: ReturnType<typeof setTimeout> | undefined;
let autosaveInFlight = false;

function autosave(): void {
    if (
        autosaveInFlight ||
        form.processing ||
        !form.isDirty ||
        form.title.trim() === ''
    ) {
        return;
    }

    autosaveInFlight = true;

    form.patch(`/resumes/${props.resume.id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            autosavedAt.value = new Date().toLocaleTimeString(undefined, {
                hour: '2-digit',
                minute: '2-digit',
            });
        },
        onFinish: () => {
            autosaveInFlight = false;
        },
    });
}

watch(
    () => form.data(),
    () => {
        if (autosaveTimer) {
            window.clearTimeout(autosaveTimer);
        }

        autosaveTimer = window.setTimeout(autosave, AUTOSAVE_DELAY);
    },
    { deep: true },
);

function warnBeforeLeaving(event: BeforeUnloadEvent): void {
    if (!form.isDirty && !form.processing) {
        return;
    }

    event.preventDefault();
    event.returnValue = '';
}

onMounted(() => window.addEventListener('beforeunload', warnBeforeLeaving));

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', warnBeforeLeaving);

    if (autosaveTimer) {
        window.clearTimeout(autosaveTimer);
    }
});

function togglePublic(): void {
    router.post(`/resumes/${props.resume.id}/publish`, {}, {
        preserveScroll: true,
        onStart: () => {
            publishing.value = true;
        },
        onFinish: () => {
            publishing.value = false;
        },
    });
}

async function copyLink(): Promise<void> {
    if (!props.resume.public_url) {
        return;
    }

    try {
        await navigator.clipboard.writeText(props.resume.public_url);
        copied.value = true;
        window.setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        copied.value = false;
    }
}

function addExperience(): void {
    form.content.experience.push({
        title: '',
        company: '',
        location: '',
        start: '',
        end: '',
        current: false,
        bullets: '',
    });
}

function addEducation(): void {
    form.content.education.push({
        degree: '',
        school: '',
        location: '',
        start: '',
        end: '',
        details: '',
    });
}

function addSkillGroup(): void {
    form.content.skills.push({ group: '', items: '' });
}

function addProject(): void {
    form.content.projects.push({ name: '', link: '', description: '' });
}

function addCertification(): void {
    form.content.certifications.push({ name: '', issuer: '', year: '' });
}

function addLanguage(): void {
    form.content.languages.push({ name: '', level: '' });
}

function removeEntry(section: EntrySection, index: number): void {
    (form.content[section] as unknown[]).splice(index, 1);
}

function moveEntry(
    section: EntrySection,
    index: number,
    direction: -1 | 1,
): void {
    const entries = form.content[section] as unknown[];
    const target = index + direction;

    if (target < 0 || target >= entries.length) {
        return;
    }

    const [entry] = entries.splice(index, 1);
    entries.splice(target, 0, entry);
}
</script>

<template>
    <Head :title="`${props.resume.title} — Builder`" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div
            class="flex flex-wrap items-center gap-2 rounded-xl border bg-card p-3"
        >
            <Button variant="ghost" size="sm" as-child>
                <Link href="/resumes">
                    <ArrowLeft class="size-4" />
                    All resumes
                </Link>
            </Button>

            <input
                v-model="form.title"
                :class="[inputClass, 'min-w-40 flex-1 font-medium']"
                maxlength="120"
                aria-label="Resume name"
                placeholder="Resume name"
            />

            <span
                v-if="form.processing"
                class="flex items-center gap-1 text-xs text-muted-foreground"
            >
                <Loader2 class="size-3.5 animate-spin" />
                Saving…
            </span>
            <span
                v-else-if="form.isDirty"
                class="text-xs font-medium text-amber-600 dark:text-amber-400"
            >
                Unsaved changes
            </span>
            <span
                v-else-if="autosavedAt"
                class="flex items-center gap-1 text-xs font-medium text-emerald-600 dark:text-emerald-400"
            >
                <Check class="size-3.5" />
                Saved at {{ autosavedAt }}
            </span>

            <Button :disabled="form.processing" @click="save">
                <Check class="size-4" />
                {{ form.processing ? 'Saving…' : 'Save' }}
            </Button>
            <Button variant="outline" as-child>
                <a :href="props.resume.print_url" target="_blank">
                    <Printer class="size-4" />
                    Print / PDF
                </a>
            </Button>
        </div>

        <div
            v-if="errorMessages.length"
            class="rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm"
        >
            <p class="font-medium">Please check these fields before saving:</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5 text-xs">
                <li v-for="message in errorMessages" :key="message">
                    {{ message }}
                </li>
            </ul>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_420px]">
            <div class="space-y-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Template & style</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <label
                                for="resume-template"
                                class="text-sm font-medium"
                                >Template</label
                            >
                            <select
                                id="resume-template"
                                v-model="form.template"
                                :class="inputClass"
                            >
                                <option
                                    v-for="template in props.templates"
                                    :key="template.key"
                                    :value="template.key"
                                >
                                    {{ template.name }} — {{ template.style }}
                                </option>
                            </select>
                            <p class="text-xs text-muted-foreground">
                                {{ activeTemplate?.description }}
                                <span v-if="activeTemplate?.ats">
                                    Reads cleanly in applicant tracking
                                    systems.</span
                                >
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <label for="resume-font" class="text-sm font-medium"
                                >Typeface</label
                            >
                            <select
                                id="resume-font"
                                v-model="form.font"
                                :class="inputClass"
                            >
                                <option
                                    v-for="(label, value) in props.fonts"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </div>

                        <div class="grid gap-2">
                            <span class="text-sm font-medium">Accent colour</span>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="(hex, key) in props.accents"
                                    :key="key"
                                    type="button"
                                    class="size-7 rounded-full ring-offset-2 ring-offset-background transition"
                                    :class="
                                        form.accent === key
                                            ? 'ring-2 ring-foreground'
                                            : 'hover:ring-2 hover:ring-muted-foreground/40'
                                    "
                                    :style="{ backgroundColor: hex }"
                                    :aria-label="`${accentLabel(key)} accent`"
                                    :title="accentLabel(key)"
                                    @click="form.accent = key"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Personal details</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2 sm:col-span-2">
                            <label for="resume-name" class="text-sm font-medium"
                                >Full name</label
                            >
                            <input
                                id="resume-name"
                                v-model="form.content.basics.name"
                                :class="inputClass"
                                maxlength="120"
                                placeholder="Ada Lovelace"
                            />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <label
                                for="resume-headline"
                                class="text-sm font-medium"
                                >Professional title</label
                            >
                            <input
                                id="resume-headline"
                                v-model="form.content.basics.headline"
                                :class="inputClass"
                                maxlength="160"
                                placeholder="Senior Backend Engineer"
                            />
                        </div>
                        <div class="grid gap-2">
                            <label for="resume-email" class="text-sm font-medium"
                                >Email</label
                            >
                            <input
                                id="resume-email"
                                v-model="form.content.basics.email"
                                :class="inputClass"
                                maxlength="160"
                                placeholder="you@example.com"
                            />
                        </div>
                        <div class="grid gap-2">
                            <label for="resume-phone" class="text-sm font-medium"
                                >Phone</label
                            >
                            <input
                                id="resume-phone"
                                v-model="form.content.basics.phone"
                                :class="inputClass"
                                maxlength="40"
                                placeholder="+1 (415) 555-0134"
                            />
                        </div>
                        <div class="grid gap-2">
                            <label
                                for="resume-location"
                                class="text-sm font-medium"
                                >Location</label
                            >
                            <input
                                id="resume-location"
                                v-model="form.content.basics.location"
                                :class="inputClass"
                                maxlength="120"
                                placeholder="Lisbon, Portugal"
                            />
                        </div>
                        <div class="grid gap-2">
                            <label
                                for="resume-website"
                                class="text-sm font-medium"
                                >Website</label
                            >
                            <input
                                id="resume-website"
                                v-model="form.content.basics.website"
                                :class="inputClass"
                                maxlength="200"
                                placeholder="yourname.com"
                            />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <label
                                for="resume-linkedin"
                                class="text-sm font-medium"
                                >LinkedIn</label
                            >
                            <input
                                id="resume-linkedin"
                                v-model="form.content.basics.linkedin"
                                :class="inputClass"
                                maxlength="200"
                                placeholder="linkedin.com/in/you"
                            />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <label
                                for="resume-summary"
                                class="text-sm font-medium"
                                >Professional summary</label
                            >
                            <textarea
                                id="resume-summary"
                                v-model="form.content.basics.summary"
                                :class="textareaClass"
                                rows="4"
                                maxlength="2000"
                                placeholder="Two or three sentences on what you do, your strongest results and what you are looking for."
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ form.content.basics.summary.length }}/2000
                                characters
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle class="text-base">Experience</CardTitle>
                        <Button
                            size="sm"
                            variant="outline"
                            type="button"
                            @click="addExperience"
                        >
                            <Plus class="size-4" />
                            Add role
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p
                            v-if="!form.content.experience.length"
                            class="text-sm text-muted-foreground"
                        >
                            Add your most recent role first. Recruiters read the
                            top third of the page first.
                        </p>

                        <div
                            v-for="(entry, index) in form.content.experience"
                            :key="`experience-${index}`"
                            class="space-y-3 rounded-lg border p-3"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-medium">
                                    Role {{ index + 1 }}
                                </p>
                                <div class="flex gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-7"
                                        type="button"
                                        :disabled="index === 0"
                                        aria-label="Move role up"
                                        @click="moveEntry('experience', index, -1)"
                                    >
                                        <ArrowUp class="size-3.5" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-7"
                                        type="button"
                                        :disabled="
                                            index ===
                                            form.content.experience.length - 1
                                        "
                                        aria-label="Move role down"
                                        @click="moveEntry('experience', index, 1)"
                                    >
                                        <ArrowDown class="size-3.5" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-7 text-destructive"
                                        type="button"
                                        aria-label="Remove role"
                                        @click="
                                            removeEntry('experience', index)
                                        "
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <label class="text-xs font-medium"
                                        >Job title</label
                                    >
                                    <input
                                        v-model="entry.title"
                                        :class="inputClass"
                                        maxlength="120"
                                        placeholder="Senior Engineer"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <label class="text-xs font-medium"
                                        >Company</label
                                    >
                                    <input
                                        v-model="entry.company"
                                        :class="inputClass"
                                        maxlength="120"
                                        placeholder="Acme"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <label class="text-xs font-medium"
                                        >Location</label
                                    >
                                    <input
                                        v-model="entry.location"
                                        :class="inputClass"
                                        maxlength="120"
                                        placeholder="Remote"
                                    />
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="grid gap-2">
                                        <label class="text-xs font-medium"
                                            >Start</label
                                        >
                                        <input
                                            v-model="entry.start"
                                            :class="inputClass"
                                            maxlength="40"
                                            placeholder="2022"
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <label class="text-xs font-medium"
                                            >End</label
                                        >
                                        <input
                                            v-model="entry.end"
                                            :class="inputClass"
                                            maxlength="40"
                                            :disabled="entry.current"
                                            placeholder="2024"
                                        />
                                    </div>
                                </div>
                            </div>

                            <label
                                class="flex items-center gap-2 text-sm text-muted-foreground"
                            >
                                <input
                                    v-model="entry.current"
                                    type="checkbox"
                                    class="size-4 rounded border-input"
                                />
                                I currently work here
                            </label>

                            <div class="grid gap-2">
                                <label class="text-xs font-medium"
                                    >Highlights</label
                                >
                                <textarea
                                    v-model="entry.bullets"
                                    :class="textareaClass"
                                    rows="4"
                                    maxlength="4000"
                                    placeholder="One achievement per line, starting with a verb.&#10;Cut checkout latency by 40%."
                                />
                                <p class="text-xs text-muted-foreground">
                                    One achievement per line. Numbers make them
                                    land.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle class="text-base">Education</CardTitle>
                        <Button
                            size="sm"
                            variant="outline"
                            type="button"
                            @click="addEducation"
                        >
                            <Plus class="size-4" />
                            Add education
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p
                            v-if="!form.content.education.length"
                            class="text-sm text-muted-foreground"
                        >
                            Add your highest qualification. Courses and
                            bootcamps count too.
                        </p>

                        <div
                            v-for="(entry, index) in form.content.education"
                            :key="`education-${index}`"
                            class="space-y-3 rounded-lg border p-3"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-medium">
                                    Education {{ index + 1 }}
                                </p>
                                <div class="flex gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-7"
                                        type="button"
                                        :disabled="index === 0"
                                        aria-label="Move education up"
                                        @click="moveEntry('education', index, -1)"
                                    >
                                        <ArrowUp class="size-3.5" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-7"
                                        type="button"
                                        :disabled="
                                            index ===
                                            form.content.education.length - 1
                                        "
                                        aria-label="Move education down"
                                        @click="moveEntry('education', index, 1)"
                                    >
                                        <ArrowDown class="size-3.5" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-7 text-destructive"
                                        type="button"
                                        aria-label="Remove education"
                                        @click="
                                            removeEntry('education', index)
                                        "
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <label class="text-xs font-medium"
                                        >Degree</label
                                    >
                                    <input
                                        v-model="entry.degree"
                                        :class="inputClass"
                                        maxlength="160"
                                        placeholder="BSc Computer Science"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <label class="text-xs font-medium"
                                        >School</label
                                    >
                                    <input
                                        v-model="entry.school"
                                        :class="inputClass"
                                        maxlength="160"
                                        placeholder="University of Lagos"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <label class="text-xs font-medium"
                                        >Location</label
                                    >
                                    <input
                                        v-model="entry.location"
                                        :class="inputClass"
                                        maxlength="120"
                                        placeholder="Lagos, Nigeria"
                                    />
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="grid gap-2">
                                        <label class="text-xs font-medium"
                                            >Start</label
                                        >
                                        <input
                                            v-model="entry.start"
                                            :class="inputClass"
                                            maxlength="40"
                                            placeholder="2015"
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <label class="text-xs font-medium"
                                            >End</label
                                        >
                                        <input
                                            v-model="entry.end"
                                            :class="inputClass"
                                            maxlength="40"
                                            placeholder="2019"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <label class="text-xs font-medium"
                                    >Details</label
                                >
                                <textarea
                                    v-model="entry.details"
                                    :class="textareaClass"
                                    rows="2"
                                    maxlength="1000"
                                    placeholder="First class honours · Coursework in distributed systems"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle class="text-base">Skills</CardTitle>
                        <Button
                            size="sm"
                            variant="outline"
                            type="button"
                            @click="addSkillGroup"
                        >
                            <Plus class="size-4" />
                            Add group
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p
                            v-if="!form.content.skills.length"
                            class="text-sm text-muted-foreground"
                        >
                            Group skills so they are easy to scan — for example
                            “Languages” and “Tools”.
                        </p>

                        <div
                            v-for="(entry, index) in form.content.skills"
                            :key="`skills-${index}`"
                            class="flex flex-wrap items-start gap-2 rounded-lg border p-3"
                        >
                            <div class="grid min-w-32 flex-1 gap-2">
                                <label class="text-xs font-medium"
                                    >Group</label
                                >
                                <input
                                    v-model="entry.group"
                                    :class="inputClass"
                                    maxlength="60"
                                    placeholder="Languages"
                                />
                            </div>
                            <div class="grid min-w-48 flex-[2] gap-2">
                                <label class="text-xs font-medium"
                                    >Skills (comma separated)</label
                                >
                                <input
                                    v-model="entry.items"
                                    :class="inputClass"
                                    maxlength="500"
                                    placeholder="PHP, Laravel, MySQL, Redis"
                                />
                            </div>
                            <div class="flex gap-1 pt-5">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7"
                                    type="button"
                                    :disabled="index === 0"
                                    aria-label="Move skill group up"
                                    @click="moveEntry('skills', index, -1)"
                                >
                                    <ArrowUp class="size-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7"
                                    type="button"
                                    :disabled="
                                        index === form.content.skills.length - 1
                                    "
                                    aria-label="Move skill group down"
                                    @click="moveEntry('skills', index, 1)"
                                >
                                    <ArrowDown class="size-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7 text-destructive"
                                    type="button"
                                    aria-label="Remove skill group"
                                    @click="removeEntry('skills', index)"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle class="text-base">Projects</CardTitle>
                        <Button
                            size="sm"
                            variant="outline"
                            type="button"
                            @click="addProject"
                        >
                            <Plus class="size-4" />
                            Add project
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <p
                            v-if="!form.content.projects.length"
                            class="text-sm text-muted-foreground"
                        >
                            Side projects show initiative, especially early in a
                            career.
                        </p>

                        <div
                            v-for="(entry, index) in form.content.projects"
                            :key="`project-${index}`"
                            class="space-y-3 rounded-lg border p-3"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-medium">
                                    Project {{ index + 1 }}
                                </p>
                                <div class="flex gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-7"
                                        type="button"
                                        :disabled="index === 0"
                                        aria-label="Move project up"
                                        @click="moveEntry('projects', index, -1)"
                                    >
                                        <ArrowUp class="size-3.5" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-7"
                                        type="button"
                                        :disabled="
                                            index ===
                                            form.content.projects.length - 1
                                        "
                                        aria-label="Move project down"
                                        @click="moveEntry('projects', index, 1)"
                                    >
                                        <ArrowDown class="size-3.5" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-7 text-destructive"
                                        type="button"
                                        aria-label="Remove project"
                                        @click="removeEntry('projects', index)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <label class="text-xs font-medium"
                                        >Name</label
                                    >
                                    <input
                                        v-model="entry.name"
                                        :class="inputClass"
                                        maxlength="160"
                                        placeholder="Open source scheduler"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <label class="text-xs font-medium"
                                        >Link</label
                                    >
                                    <input
                                        v-model="entry.link"
                                        :class="inputClass"
                                        maxlength="200"
                                        placeholder="github.com/you/project"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <label class="text-xs font-medium"
                                    >Description</label
                                >
                                <textarea
                                    v-model="entry.description"
                                    :class="textareaClass"
                                    rows="2"
                                    maxlength="1000"
                                    placeholder="What it does, the stack and the result."
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle class="text-base">Certifications</CardTitle>
                        <Button
                            size="sm"
                            variant="outline"
                            type="button"
                            @click="addCertification"
                        >
                            <Plus class="size-4" />
                            Add certification
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p
                            v-if="!form.content.certifications.length"
                            class="text-sm text-muted-foreground"
                        >
                            Certifications are optional but useful for regulated
                            and technical fields.
                        </p>

                        <div
                            v-for="(entry, index) in form.content.certifications"
                            :key="`certification-${index}`"
                            class="flex flex-wrap items-start gap-2 rounded-lg border p-3"
                        >
                            <div class="grid min-w-40 flex-[2] gap-2">
                                <label class="text-xs font-medium"
                                    >Certification</label
                                >
                                <input
                                    v-model="entry.name"
                                    :class="inputClass"
                                    maxlength="160"
                                    placeholder="AWS Solutions Architect"
                                />
                            </div>
                            <div class="grid min-w-36 flex-[2] gap-2">
                                <label class="text-xs font-medium"
                                    >Issuer</label
                                >
                                <input
                                    v-model="entry.issuer"
                                    :class="inputClass"
                                    maxlength="160"
                                    placeholder="Amazon Web Services"
                                />
                            </div>
                            <div class="grid w-24 gap-2">
                                <label class="text-xs font-medium">Year</label>
                                <input
                                    v-model="entry.year"
                                    :class="inputClass"
                                    maxlength="20"
                                    placeholder="2024"
                                />
                            </div>
                            <div class="flex gap-1 pt-5">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7"
                                    type="button"
                                    :disabled="index === 0"
                                    aria-label="Move certification up"
                                    @click="
                                        moveEntry('certifications', index, -1)
                                    "
                                >
                                    <ArrowUp class="size-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7"
                                    type="button"
                                    :disabled="
                                        index ===
                                        form.content.certifications.length - 1
                                    "
                                    aria-label="Move certification down"
                                    @click="
                                        moveEntry('certifications', index, 1)
                                    "
                                >
                                    <ArrowDown class="size-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7 text-destructive"
                                    type="button"
                                    aria-label="Remove certification"
                                    @click="
                                        removeEntry('certifications', index)
                                    "
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle class="text-base">Languages</CardTitle>
                        <Button
                            size="sm"
                            variant="outline"
                            type="button"
                            @click="addLanguage"
                        >
                            <Plus class="size-4" />
                            Add language
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p
                            v-if="!form.content.languages.length"
                            class="text-sm text-muted-foreground"
                        >
                            List languages you can work in and how well.
                        </p>

                        <div
                            v-for="(entry, index) in form.content.languages"
                            :key="`language-${index}`"
                            class="flex flex-wrap items-start gap-2 rounded-lg border p-3"
                        >
                            <div class="grid min-w-40 flex-1 gap-2">
                                <label class="text-xs font-medium"
                                    >Language</label
                                >
                                <input
                                    v-model="entry.name"
                                    :class="inputClass"
                                    maxlength="80"
                                    placeholder="English"
                                />
                            </div>
                            <div class="grid min-w-36 flex-1 gap-2">
                                <label class="text-xs font-medium"
                                    >Level</label
                                >
                                <input
                                    v-model="entry.level"
                                    :class="inputClass"
                                    maxlength="60"
                                    placeholder="Fluent"
                                />
                            </div>
                            <div class="flex gap-1 pt-5">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7"
                                    type="button"
                                    :disabled="index === 0"
                                    aria-label="Move language up"
                                    @click="moveEntry('languages', index, -1)"
                                >
                                    <ArrowUp class="size-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7"
                                    type="button"
                                    :disabled="
                                        index ===
                                        form.content.languages.length - 1
                                    "
                                    aria-label="Move language down"
                                    @click="moveEntry('languages', index, 1)"
                                >
                                    <ArrowDown class="size-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7 text-destructive"
                                    type="button"
                                    aria-label="Remove language"
                                    @click="removeEntry('languages', index)"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Share</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p class="text-sm text-muted-foreground">
                            Publish to get a private link you can send to
                            recruiters. Unpublish any time and the link stops
                            working.
                        </p>

                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                variant="outline"
                                type="button"
                                :disabled="publishing"
                                @click="togglePublic"
                            >
                                <Share2 class="size-4" />
                                {{
                                    props.resume.is_public
                                        ? 'Stop sharing'
                                        : 'Publish & get link'
                                }}
                            </Button>
                            <template v-if="props.resume.public_url">
                                <Button variant="outline" as-child>
                                    <a
                                        :href="props.resume.public_url"
                                        target="_blank"
                                    >
                                        <Eye class="size-4" />
                                        View
                                    </a>
                                </Button>
                                <Button
                                    variant="ghost"
                                    type="button"
                                    @click="copyLink"
                                >
                                    <Copy class="size-4" />
                                    {{ copied ? 'Copied' : 'Copy link' }}
                                </Button>
                            </template>
                        </div>

                        <p
                            v-if="props.resume.public_url"
                            class="truncate rounded-md bg-muted px-3 py-2 text-xs text-muted-foreground"
                        >
                            {{ props.resume.public_url }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="lg:sticky lg:top-4 lg:self-start">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <p class="text-sm font-medium">Live preview</p>
                    <div class="flex items-center gap-2">
                        <Badge variant="secondary">
                            {{ activeTemplate?.name }}
                        </Badge>
                        <span class="text-xs text-muted-foreground">
                            A4 · {{ props.resume.completeness }}% complete
                        </span>
                    </div>
                </div>

                <div
                    class="overflow-x-auto rounded-xl border bg-neutral-200 p-3 dark:bg-neutral-900"
                >
                    <div class="mx-auto w-fit shadow-lg">
                        <ResumePreview
                            :content="form.content"
                            :template="form.template"
                            :accent="accentHex"
                            :font="form.font"
                            :width="372"
                            :watermark="props.resume.watermark"
                        />
                    </div>
                </div>

                <p
                    v-if="props.resume.watermark"
                    class="mt-2 text-center text-xs text-muted-foreground"
                >
                    The “Made with” line at the bottom disappears on a paid
                    plan.
                </p>

                <div class="mt-3 flex flex-wrap gap-2">
                    <Button class="flex-1" :disabled="form.processing" @click="save">
                        <Check class="size-4" />
                        {{ form.processing ? 'Saving…' : 'Save resume' }}
                    </Button>
                    <Button variant="outline" as-child>
                        <a :href="props.resume.print_url" target="_blank">
                            <Printer class="size-4" />
                            Print / PDF
                        </a>
                    </Button>
                </div>

                <p class="mt-2 text-center text-xs text-muted-foreground">
                    Changes save automatically a moment after you stop typing.
                    “Save resume” saves immediately.
                </p>
            </div>
        </div>
    </div>
</template>
