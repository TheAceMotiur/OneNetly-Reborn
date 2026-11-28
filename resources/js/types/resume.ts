export type ResumeBasics = {
    name: string;
    headline: string;
    email: string;
    phone: string;
    location: string;
    website: string;
    linkedin: string;
    summary: string;
};

export type ResumeExperience = {
    title: string;
    company: string;
    location: string;
    start: string;
    end: string;
    current: boolean;
    bullets: string;
};

export type ResumeEducation = {
    degree: string;
    school: string;
    location: string;
    start: string;
    end: string;
    details: string;
};

export type ResumeSkillGroup = {
    group: string;
    items: string;
};

export type ResumeProject = {
    name: string;
    link: string;
    description: string;
};

export type ResumeCertification = {
    name: string;
    issuer: string;
    year: string;
};

export type ResumeLanguage = {
    name: string;
    level: string;
};

export type ResumeContent = {
    basics: ResumeBasics;
    experience: ResumeExperience[];
    education: ResumeEducation[];
    skills: ResumeSkillGroup[];
    projects: ResumeProject[];
    certifications: ResumeCertification[];
    languages: ResumeLanguage[];
};

export type ResumePayload = {
    id: number;
    title: string;
    template: string;
    accent: string;
    accent_hex: string;
    font: string;
    is_public: boolean;
    public_url: string | null;
    print_url: string;
    updated_at: string | null;
    completeness: number;
    watermark: boolean;
    content: ResumeContent;
};

export type ResumeTemplate = {
    key: string;
    name: string;
    description: string;
    style: string;
    ats: boolean;
    tags: string[];
};
