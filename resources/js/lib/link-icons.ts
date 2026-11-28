import {
    BookOpen,
    Briefcase,
    Calendar,
    Camera,
    Code,
    Download,
    Gamepad2,
    Globe,
    Heart,
    Link2,
    Mail,
    Mic,
    Music,
    Podcast,
    ShoppingBag,
    Star,
    Video,
} from '@lucide/vue';
import type { Component } from 'vue';

export const linkIconComponents: Record<string, Component> = {
    link: Link2,
    globe: Globe,
    mail: Mail,
    calendar: Calendar,
    video: Video,
    music: Music,
    camera: Camera,
    store: ShoppingBag,
    briefcase: Briefcase,
    book: BookOpen,
    mic: Mic,
    podcast: Podcast,
    gamepad: Gamepad2,
    heart: Heart,
    star: Star,
    download: Download,
    code: Code,
};

export const linkIconLabels: Record<string, string> = {
    link: 'Link',
    globe: 'Website',
    mail: 'Email',
    calendar: 'Calendar',
    video: 'Video',
    music: 'Music',
    camera: 'Photos',
    store: 'Store',
    briefcase: 'Work',
    book: 'Writing',
    mic: 'Speaking',
    podcast: 'Podcast',
    gamepad: 'Gaming',
    heart: 'Support',
    star: 'Favourite',
    download: 'Download',
    code: 'Code',
};

export function linkIcon(icon: string): Component {
    return linkIconComponents[icon] ?? Link2;
}
