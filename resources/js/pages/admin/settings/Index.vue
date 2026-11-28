<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Save } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type SettingsValues = {
    site_name: string;
    support_email: string | null;
    footer_text: string | null;
    meta_description: string | null;
    payments_enabled: boolean;
    paypal_me_username: string | null;
    payment_note: string | null;
    custom_domains_enabled: boolean;
    analytics_enabled: boolean;
    mail_host: string | null;
    mail_port: number | null;
    mail_encryption: string;
    mail_username: string | null;
    mail_password: string | null;
    mail_from_name: string;
    mail_from_address: string;
    sms_enabled: boolean;
    textbee_api_key: string | null;
    textbee_device_id: string | null;
    textbee_webhook_secret: string | null;
    whatsapp_enabled: boolean;
    whatsapp_api_version: string;
    whatsapp_verify_token: string | null;
    whatsapp_app_secret: string | null;
    facebook_enabled: boolean;
    facebook_graph_version: string;
    facebook_verify_token: string | null;
    facebook_app_secret: string | null;
    instagram_enabled: boolean;
    instagram_graph_version: string;
    instagram_verify_token: string | null;
    instagram_app_secret: string | null;
    twitter_enabled: boolean;
    twitter_client_id: string | null;
    twitter_client_secret: string | null;
};

const props = defineProps<{
    groups: Record<string, { label: string; description: string }>;
    settings: SettingsValues;
    mailPasswordSet: boolean;
    textbeeKeySet: boolean;
    textbeeWebhookSet: boolean;
    whatsappVerifyTokenSet: boolean;
    whatsappAppSecretSet: boolean;
    facebookVerifyTokenSet: boolean;
    facebookAppSecretSet: boolean;
    instagramVerifyTokenSet: boolean;
    instagramAppSecretSet: boolean;
    twitterClientSecretSet: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Settings', href: '/admin/settings' },
        ],
    },
});

const form = useForm({
    site_name: props.settings.site_name,
    support_email: props.settings.support_email ?? '',
    footer_text: props.settings.footer_text ?? '',
    meta_description: props.settings.meta_description ?? '',
    payments_enabled: Boolean(props.settings.payments_enabled),
    paypal_me_username: props.settings.paypal_me_username ?? '',
    payment_note: props.settings.payment_note ?? '',
    custom_domains_enabled: Boolean(props.settings.custom_domains_enabled),
    analytics_enabled: Boolean(props.settings.analytics_enabled),
    mail_host: props.settings.mail_host ?? '',
    mail_port: props.settings.mail_port ?? 587,
    mail_encryption: props.settings.mail_encryption,
    mail_username: props.settings.mail_username ?? '',
    mail_password: '',
    mail_from_name: props.settings.mail_from_name,
    mail_from_address: props.settings.mail_from_address,
    sms_enabled: Boolean(props.settings.sms_enabled),
    textbee_api_key: '',
    textbee_device_id: props.settings.textbee_device_id ?? '',
    textbee_webhook_secret: '',
    whatsapp_enabled: Boolean(props.settings.whatsapp_enabled),
    whatsapp_api_version: props.settings.whatsapp_api_version ?? 'v21.0',
    whatsapp_verify_token: '',
    whatsapp_app_secret: '',
    facebook_enabled: Boolean(props.settings.facebook_enabled),
    facebook_graph_version: props.settings.facebook_graph_version ?? 'v21.0',
    facebook_verify_token: '',
    facebook_app_secret: '',
    instagram_enabled: Boolean(props.settings.instagram_enabled),
    instagram_graph_version: props.settings.instagram_graph_version ?? 'v21.0',
    instagram_verify_token: '',
    instagram_app_secret: '',
    twitter_enabled: Boolean(props.settings.twitter_enabled),
    twitter_client_id: props.settings.twitter_client_id ?? '',
    twitter_client_secret: '',
});

function save(): void {
    form.patch('/admin/settings', { preserveScroll: true });
}
</script>

<template>
    <Head title="Settings" />

    <form
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
        @submit.prevent="save"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                title="Settings"
                description="Control branding, payments, features, email and more from one place"
            />
            <Button type="submit" :disabled="form.processing">
                <Save class="size-4" />
                {{ form.processing ? 'Saving…' : 'Save changes' }}
            </Button>
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ props.groups.general.label }}
                    </CardTitle>
                    <p class="text-sm text-muted-foreground">
                        {{ props.groups.general.description }}
                    </p>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="grid gap-2">
                        <Label for="site_name">Site name</Label>
                        <Input id="site_name" v-model="form.site_name" required />
                        <p class="text-sm text-muted-foreground">
                            Shown in the sidebar, page titles and marketing pages.
                        </p>
                        <InputError :message="form.errors.site_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="support_email">Support email</Label>
                        <Input
                            id="support_email"
                            v-model="form.support_email"
                            type="email"
                            placeholder="support@example.com"
                        />
                        <p class="text-sm text-muted-foreground">
                            Public contact address shown to customers.
                        </p>
                        <InputError :message="form.errors.support_email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="footer_text">Footer tagline</Label>
                        <Input id="footer_text" v-model="form.footer_text" />
                        <p class="text-sm text-muted-foreground">
                            Short line displayed in marketing page footers.
                        </p>
                        <InputError :message="form.errors.footer_text" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="meta_description">Meta description</Label>
                        <textarea
                            id="meta_description"
                            v-model="form.meta_description"
                            rows="3"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                        />
                        <p class="text-sm text-muted-foreground">
                            Default description used by search engines.
                        </p>
                        <InputError :message="form.errors.meta_description" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ props.groups.payments.label }}
                    </CardTitle>
                    <p class="text-sm text-muted-foreground">
                        {{ props.groups.payments.description }}
                    </p>
                </CardHeader>
                <CardContent class="space-y-5">
                    <label class="flex items-start gap-3 rounded-lg border p-4">
                        <input
                            v-model="form.payments_enabled"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-input"
                        />
                        <span class="space-y-1">
                            <span class="block text-sm font-medium">
                                Accept payments
                            </span>
                            <span class="block text-sm text-muted-foreground">
                                When disabled, checkout is blocked but existing
                                plans stay active.
                            </span>
                        </span>
                    </label>
                    <InputError :message="form.errors.payments_enabled" />

                    <div class="grid gap-2">
                        <Label for="paypal_me_username">PayPal.me username</Label>
                        <Input
                            id="paypal_me_username"
                            v-model="form.paypal_me_username"
                            placeholder="your-paypal-username"
                            autocomplete="off"
                        />
                        <p class="text-sm text-muted-foreground">
                            Personal PayPal username used for PayPal.me checkout
                            links. Leave blank to use the environment value.
                        </p>
                        <InputError :message="form.errors.paypal_me_username" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="payment_note">Payment instructions</Label>
                        <textarea
                            id="payment_note"
                            v-model="form.payment_note"
                            rows="3"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                            placeholder="e.g. Include your account email in the PayPal note."
                        />
                        <p class="text-sm text-muted-foreground">
                            Shown on the payment return page after checkout.
                        </p>
                        <InputError :message="form.errors.payment_note" />
                    </div>

                    <p class="text-sm text-muted-foreground">
                        Prices and currencies are managed per plan on the
                        <a
                            href="/admin/plans"
                            class="font-medium text-foreground underline underline-offset-4"
                        >
                            Plans
                        </a>
                        page.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ props.groups.features.label }}
                    </CardTitle>
                    <p class="text-sm text-muted-foreground">
                        {{ props.groups.features.description }}
                    </p>
                </CardHeader>
                <CardContent class="space-y-4">
                    <label class="flex items-start gap-3 rounded-lg border p-4">
                        <input
                            v-model="form.custom_domains_enabled"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-input"
                        />
                        <span class="space-y-1">
                            <span class="block text-sm font-medium">
                                Custom domains
                            </span>
                            <span class="block text-sm text-muted-foreground">
                                When disabled, bio pages are not served on
                                custom domains.
                            </span>
                        </span>
                    </label>
                    <InputError :message="form.errors.custom_domains_enabled" />

                    <label class="flex items-start gap-3 rounded-lg border p-4">
                        <input
                            v-model="form.analytics_enabled"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-input"
                        />
                        <span class="space-y-1">
                            <span class="block text-sm font-medium">
                                Click analytics
                            </span>
                            <span class="block text-sm text-muted-foreground">
                                When disabled, users cannot view the analytics
                                dashboard.
                            </span>
                        </span>
                    </label>
                    <InputError :message="form.errors.analytics_enabled" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ props.groups.sms.label }}
                    </CardTitle>
                    <p class="text-sm text-muted-foreground">
                        {{ props.groups.sms.description }}
                    </p>
                </CardHeader>
                <CardContent class="space-y-5">
                    <label class="flex items-start gap-3 rounded-lg border p-4">
                        <input
                            v-model="form.sms_enabled"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-input"
                        />
                        <span class="space-y-1">
                            <span class="block text-sm font-medium">
                                Send SMS
                            </span>
                            <span class="block text-sm text-muted-foreground">
                                Allow restaurants to text guests about order
                                and reservation updates through TextBee.
                            </span>
                        </span>
                    </label>
                    <InputError :message="form.errors.sms_enabled" />

                    <div class="grid gap-2">
                        <Label for="textbee_api_key">TextBee API key</Label>
                        <Input
                            id="textbee_api_key"
                            v-model="form.textbee_api_key"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="
                                props.textbeeKeySet
                                    ? 'A key is saved — leave blank to keep it'
                                    : 'Paste your TextBee API key'
                            "
                        />
                        <p class="text-sm text-muted-foreground">
                            Stored encrypted. Create it in the TextBee dashboard
                            under API Keys.
                        </p>
                        <InputError :message="form.errors.textbee_api_key" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="textbee_webhook_secret">
                            Webhook signing secret
                        </Label>
                        <Input
                            id="textbee_webhook_secret"
                            v-model="form.textbee_webhook_secret"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="
                                props.textbeeWebhookSet
                                    ? 'A secret is saved — leave blank to keep it'
                                    : 'TextBee webhook secret'
                            "
                        />
                        <p class="text-sm text-muted-foreground">
                            Used to verify delivery events at
                            <code class="text-xs">/webhooks/textbee</code>.
                        </p>
                        <InputError
                            :message="form.errors.textbee_webhook_secret"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="textbee_device_id">
                            Android device ID
                        </Label>
                        <Input
                            id="textbee_device_id"
                            v-model="form.textbee_device_id"
                            placeholder="Use the default TextBee device"
                        />
                        <p class="text-sm text-muted-foreground">
                            Optional. Leave blank to send from the default
                            online device.
                        </p>
                        <InputError :message="form.errors.textbee_device_id" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ props.groups.whatsapp.label }}
                    </CardTitle>
                    <p class="text-sm text-muted-foreground">
                        {{ props.groups.whatsapp.description }}
                    </p>
                </CardHeader>
                <CardContent class="space-y-5">
                    <label class="flex items-start gap-3 rounded-lg border p-4">
                        <input
                            v-model="form.whatsapp_enabled"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-input"
                        />
                        <span class="space-y-1">
                            <span class="block text-sm font-medium">
                                Enable WhatsApp
                            </span>
                            <span class="block text-sm text-muted-foreground">
                                When disabled, no page can send WhatsApp messages.
                            </span>
                        </span>
                    </label>
                    <InputError :message="form.errors.whatsapp_enabled" />

                    <div class="grid gap-2">
                        <Label for="whatsapp_api_version">
                            Graph API version
                        </Label>
                        <Input
                            id="whatsapp_api_version"
                            v-model="form.whatsapp_api_version"
                            placeholder="v21.0"
                            required
                        />
                        <p class="text-sm text-muted-foreground">
                            Used for every WhatsApp Cloud API request.
                        </p>
                        <InputError :message="form.errors.whatsapp_api_version" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="whatsapp_verify_token">
                            Webhook verify token
                        </Label>
                        <Input
                            id="whatsapp_verify_token"
                            v-model="form.whatsapp_verify_token"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="
                                props.whatsappVerifyTokenSet
                                    ? 'A token is saved — leave blank to keep it'
                                    : 'Choose a verify token'
                            "
                        />
                        <p class="text-sm text-muted-foreground">
                            Paste the same value in Meta when subscribing to
                            <code class="text-xs">/webhooks/whatsapp</code>.
                        </p>
                        <InputError
                            :message="form.errors.whatsapp_verify_token"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="whatsapp_app_secret">Meta app secret</Label>
                        <Input
                            id="whatsapp_app_secret"
                            v-model="form.whatsapp_app_secret"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="
                                props.whatsappAppSecretSet
                                    ? 'A secret is saved — leave blank to keep it'
                                    : 'Meta app secret'
                            "
                        />
                        <p class="text-sm text-muted-foreground">
                            Verifies the X-Hub-Signature-256 header on inbound
                            webhooks.
                        </p>
                        <InputError :message="form.errors.whatsapp_app_secret" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ props.groups.facebook.label }}
                    </CardTitle>
                    <p class="text-sm text-muted-foreground">
                        {{ props.groups.facebook.description }}
                    </p>
                </CardHeader>
                <CardContent class="space-y-5">
                    <label class="flex items-start gap-3 rounded-lg border p-4">
                        <input
                            v-model="form.facebook_enabled"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-input"
                        />
                        <span class="space-y-1">
                            <span class="block text-sm font-medium">
                                Enable Facebook
                            </span>
                            <span class="block text-sm text-muted-foreground">
                                When disabled, no page can send Facebook
                                automations or Messenger replies.
                            </span>
                        </span>
                    </label>
                    <InputError :message="form.errors.facebook_enabled" />

                    <div class="grid gap-2">
                        <Label for="facebook_graph_version">
                            Graph API version
                        </Label>
                        <Input
                            id="facebook_graph_version"
                            v-model="form.facebook_graph_version"
                            placeholder="v21.0"
                        />
                        <p class="text-sm text-muted-foreground">
                            Used for Messenger sends, comment replies and Page
                            checks.
                        </p>
                        <InputError
                            :message="form.errors.facebook_graph_version"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="facebook_verify_token">
                            Webhook verify token
                        </Label>
                        <Input
                            id="facebook_verify_token"
                            v-model="form.facebook_verify_token"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="
                                props.facebookVerifyTokenSet
                                    ? 'A token is saved — leave blank to keep it'
                                    : 'Choose a verify token'
                            "
                        />
                        <p class="text-sm text-muted-foreground">
                            Paste the same value in Meta when subscribing to
                            <code class="text-xs">/webhooks/facebook</code>.
                        </p>
                        <InputError
                            :message="form.errors.facebook_verify_token"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="facebook_app_secret">Meta app secret</Label>
                        <Input
                            id="facebook_app_secret"
                            v-model="form.facebook_app_secret"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="
                                props.facebookAppSecretSet
                                    ? 'A secret is saved — leave blank to keep it'
                                    : 'Meta app secret'
                            "
                        />
                        <p class="text-sm text-muted-foreground">
                            Verifies the X-Hub-Signature-256 header on Facebook
                            webhooks.
                        </p>
                        <InputError :message="form.errors.facebook_app_secret" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ props.groups.instagram.label }}
                    </CardTitle>
                    <p class="text-sm text-muted-foreground">
                        {{ props.groups.instagram.description }}
                    </p>
                </CardHeader>
                <CardContent class="space-y-5">
                    <label class="flex items-start gap-3 rounded-lg border p-4">
                        <input
                            v-model="form.instagram_enabled"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-input"
                        />
                        <span class="space-y-1">
                            <span class="block text-sm font-medium">
                                Enable Instagram
                            </span>
                            <span class="block text-sm text-muted-foreground">
                                When disabled, no page can send Instagram
                                automations or direct message replies.
                            </span>
                        </span>
                    </label>
                    <InputError :message="form.errors.instagram_enabled" />

                    <div class="grid gap-2">
                        <Label for="instagram_graph_version">
                            Graph API version
                        </Label>
                        <Input
                            id="instagram_graph_version"
                            v-model="form.instagram_graph_version"
                            placeholder="v21.0"
                        />
                        <p class="text-sm text-muted-foreground">
                            Used for Instagram sends, comment replies and
                            account checks.
                        </p>
                        <InputError
                            :message="form.errors.instagram_graph_version"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="instagram_verify_token">
                            Webhook verify token
                        </Label>
                        <Input
                            id="instagram_verify_token"
                            v-model="form.instagram_verify_token"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="
                                props.instagramVerifyTokenSet
                                    ? 'A token is saved — leave blank to keep it'
                                    : 'Choose a verify token'
                            "
                        />
                        <p class="text-sm text-muted-foreground">
                            Paste the same value in Meta when subscribing to
                            <code class="text-xs">/webhooks/instagram</code>.
                        </p>
                        <InputError
                            :message="form.errors.instagram_verify_token"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="instagram_app_secret">Meta app secret</Label>
                        <Input
                            id="instagram_app_secret"
                            v-model="form.instagram_app_secret"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="
                                props.instagramAppSecretSet
                                    ? 'A secret is saved — leave blank to keep it'
                                    : 'Meta app secret'
                            "
                        />
                        <p class="text-sm text-muted-foreground">
                            Verifies the X-Hub-Signature-256 header on Instagram
                            webhooks.
                        </p>
                        <InputError :message="form.errors.instagram_app_secret" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ props.groups.twitter.label }}
                    </CardTitle>
                    <p class="text-sm text-muted-foreground">
                        {{ props.groups.twitter.description }}
                    </p>
                </CardHeader>
                <CardContent class="space-y-5">
                    <label class="flex items-start gap-3 rounded-lg border p-4">
                        <input
                            v-model="form.twitter_enabled"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-input"
                        />
                        <span class="space-y-1">
                            <span class="block text-sm font-medium">
                                Enable Twitter / X
                            </span>
                            <span class="block text-sm text-muted-foreground">
                                When disabled, no page can publish tweets, poll
                                mentions or send replies.
                            </span>
                        </span>
                    </label>
                    <InputError :message="form.errors.twitter_enabled" />

                    <div class="grid gap-2">
                        <Label for="twitter_client_id">
                            OAuth 2.0 client ID
                        </Label>
                        <Input
                            id="twitter_client_id"
                            v-model="form.twitter_client_id"
                            placeholder="X app client ID"
                        />
                        <p class="text-sm text-muted-foreground">
                            Used to refresh expired page access tokens. Pages
                            connect their own tokens under Twitter → Settings.
                        </p>
                        <InputError :message="form.errors.twitter_client_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="twitter_client_secret">
                            OAuth 2.0 client secret
                        </Label>
                        <Input
                            id="twitter_client_secret"
                            v-model="form.twitter_client_secret"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="
                                props.twitterClientSecretSet
                                    ? 'A secret is saved — leave blank to keep it'
                                    : 'Client secret for confidential clients'
                            "
                        />
                        <p class="text-sm text-muted-foreground">
                            Leave blank for public clients; the client ID is sent
                            with refresh requests instead.
                        </p>
                        <InputError :message="form.errors.twitter_client_secret" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ props.groups.email.label }}
                    </CardTitle>
                    <p class="text-sm text-muted-foreground">
                        {{ props.groups.email.description }}
                    </p>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="space-y-4 rounded-lg border p-4">
                        <div class="space-y-1">
                            <p class="text-sm font-medium">PHPMailer SMTP</p>
                            <p class="text-sm text-muted-foreground">
                                When a host is set, every email (verification,
                                password reset and contact messages) is sent
                                through this SMTP server. Leave it blank to keep
                                using the environment mailer.
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="mail_host">SMTP host</Label>
                            <Input
                                id="mail_host"
                                v-model="form.mail_host"
                                placeholder="smtp.example.com"
                                autocomplete="off"
                            />
                            <InputError :message="form.errors.mail_host" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-2">
                                <Label for="mail_port">Port</Label>
                                <Input
                                    id="mail_port"
                                    v-model.number="form.mail_port"
                                    type="number"
                                    min="1"
                                    max="65535"
                                />
                                <InputError :message="form.errors.mail_port" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="mail_encryption">Encryption</Label>
                                <select
                                    id="mail_encryption"
                                    v-model="form.mail_encryption"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                                >
                                    <option value="tls">TLS (STARTTLS)</option>
                                    <option value="ssl">SSL</option>
                                    <option value="none">None</option>
                                </select>
                                <InputError
                                    :message="form.errors.mail_encryption"
                                />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="mail_username">SMTP username</Label>
                            <Input
                                id="mail_username"
                                v-model="form.mail_username"
                                autocomplete="off"
                            />
                            <InputError :message="form.errors.mail_username" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="mail_password">SMTP password</Label>
                            <Input
                                id="mail_password"
                                v-model="form.mail_password"
                                type="password"
                                autocomplete="new-password"
                                :placeholder="
                                    props.mailPasswordSet
                                        ? 'A password is saved — leave blank to keep it'
                                        : 'SMTP password'
                                "
                            />
                            <p class="text-sm text-muted-foreground">
                                Stored encrypted. Leave blank to keep the
                                current password.
                            </p>
                            <InputError :message="form.errors.mail_password" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="mail_from_name">Sender name</Label>
                        <Input
                            id="mail_from_name"
                            v-model="form.mail_from_name"
                            required
                        />
                        <InputError :message="form.errors.mail_from_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="mail_from_address">Sender address</Label>
                        <Input
                            id="mail_from_address"
                            v-model="form.mail_from_address"
                            type="email"
                            required
                        />
                        <p class="text-sm text-muted-foreground">
                            Must be an address your mail provider allows you to
                            send from.
                        </p>
                        <InputError :message="form.errors.mail_from_address" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing">
                <Save class="size-4" />
                {{ form.processing ? 'Saving…' : 'Save changes' }}
            </Button>
        </div>
    </form>
</template>
