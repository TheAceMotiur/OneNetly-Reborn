export type WidgetFieldType =
    | 'text'
    | 'textarea'
    | 'select'
    | 'checkbox'
    | 'number'
    | 'color';

export type WidgetField = {
    key: string;
    label: string;
    type: WidgetFieldType;
    default: string | boolean | number;
    help?: string;
    placeholder?: string;
    options?: Record<string, string>;
    min?: number;
    max?: number;
    group?: string;
};

export type WidgetMeta = {
    key: string;
    name: string;
    description: string;
    category: string;
    category_label: string;
    icon: string;
    placement: string;
};

export type WidgetDefinition = WidgetMeta & {
    fields: WidgetField[];
};

export type WidgetPayload = {
    id: number;
    name: string;
    type: string;
    config: Record<string, string | number | boolean>;
    snippet: string;
    preview: string;
    watermark: boolean;
    updated_at: string | null;
    definition: WidgetMeta;
};
