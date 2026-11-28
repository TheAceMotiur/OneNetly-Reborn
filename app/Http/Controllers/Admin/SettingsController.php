<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Show every application setting, grouped for the admin screen.
     */
    public function edit(SettingsService $settings): Response
    {
        $values = $settings->all();
        $mailPasswordSet = filled($values['mail_password'] ?? null);
        $values['mail_password'] = '';
        $textbeeKeySet = filled($values['textbee_api_key'] ?? null);
        $values['textbee_api_key'] = '';
        $textbeeWebhookSet = filled($values['textbee_webhook_secret'] ?? null);
        $values['textbee_webhook_secret'] = '';
        $whatsappVerifyTokenSet = filled($values['whatsapp_verify_token'] ?? null);
        $values['whatsapp_verify_token'] = '';
        $whatsappAppSecretSet = filled($values['whatsapp_app_secret'] ?? null);
        $values['whatsapp_app_secret'] = '';
        $facebookVerifyTokenSet = filled($values['facebook_verify_token'] ?? null);
        $values['facebook_verify_token'] = '';
        $facebookAppSecretSet = filled($values['facebook_app_secret'] ?? null);
        $values['facebook_app_secret'] = '';
        $instagramVerifyTokenSet = filled($values['instagram_verify_token'] ?? null);
        $values['instagram_verify_token'] = '';
        $instagramAppSecretSet = filled($values['instagram_app_secret'] ?? null);
        $values['instagram_app_secret'] = '';
        $twitterClientSecretSet = filled($values['twitter_client_secret'] ?? null);
        $values['twitter_client_secret'] = '';

        return Inertia::render('admin/settings/Index', [
            'groups' => config('settings.groups'),
            'settings' => $values,
            'mailPasswordSet' => $mailPasswordSet,
            'textbeeKeySet' => $textbeeKeySet,
            'textbeeWebhookSet' => $textbeeWebhookSet,
            'whatsappVerifyTokenSet' => $whatsappVerifyTokenSet,
            'whatsappAppSecretSet' => $whatsappAppSecretSet,
            'facebookVerifyTokenSet' => $facebookVerifyTokenSet,
            'facebookAppSecretSet' => $facebookAppSecretSet,
            'instagramVerifyTokenSet' => $instagramVerifyTokenSet,
            'instagramAppSecretSet' => $instagramAppSecretSet,
            'twitterClientSecretSet' => $twitterClientSecretSet,
        ]);
    }

    /**
     * Persist the submitted settings.
     */
    public function update(Request $request, SettingsService $settings): RedirectResponse
    {
        $definitions = (array) config('settings.settings', []);

        $rules = [];

        foreach ($definitions as $key => $definition) {
            $rules[$key] = $definition['rules'] ?? ['nullable', 'string'];
        }

        $data = $request->validate($rules);

        if (blank($data['mail_password'] ?? null)) {
            unset($data['mail_password']);
        }

        if (blank($data['textbee_api_key'] ?? null)) {
            unset($data['textbee_api_key']);
        }

        if (blank($data['textbee_webhook_secret'] ?? null)) {
            unset($data['textbee_webhook_secret']);
        }

        if (blank($data['whatsapp_verify_token'] ?? null)) {
            unset($data['whatsapp_verify_token']);
        }

        if (blank($data['whatsapp_app_secret'] ?? null)) {
            unset($data['whatsapp_app_secret']);
        }

        if (blank($data['facebook_verify_token'] ?? null)) {
            unset($data['facebook_verify_token']);
        }

        if (blank($data['facebook_app_secret'] ?? null)) {
            unset($data['facebook_app_secret']);
        }

        if (blank($data['instagram_verify_token'] ?? null)) {
            unset($data['instagram_verify_token']);
        }

        if (blank($data['instagram_app_secret'] ?? null)) {
            unset($data['instagram_app_secret']);
        }

        if (blank($data['twitter_client_secret'] ?? null)) {
            unset($data['twitter_client_secret']);
        }

        foreach ($definitions as $key => $definition) {
            if (($definition['type'] ?? 'string') === 'boolean') {
                $data[$key] = $request->boolean($key);
            }
        }

        $settings->setMany($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Settings updated.'),
        ]);

        return back();
    }
}
