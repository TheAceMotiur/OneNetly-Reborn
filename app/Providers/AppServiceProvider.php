<?php

namespace App\Providers;

use App\Mail\PhpMailerTransport;
use App\Models\ProfileInvitation;
use App\Models\User;
use App\Services\MailConfiguration;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthMail();
        $this->registerMailTransport();
        $this->registerInvitationAcceptance();
        $this->applySettings();
    }

    /**
     * Accept page invitations whenever an invited person signs in.
     */
    protected function registerInvitationAcceptance(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            try {
                ProfileInvitation::acceptPendingFor($event->user);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Use the branded HTML templates for account emails.
     */
    protected function configureAuthMail(): void
    {
        VerifyEmail::toMailUsing(function (mixed $notifiable, string $url): MailMessage {
            return (new MailMessage)
                ->subject(__('Verify your email address'))
                ->view('emails.verify-email', [
                    'userName' => $notifiable instanceof User ? $notifiable->name : '',
                    'verificationUrl' => $url,
                    'expiresInMinutes' => (int) config('auth.verification.expire', 60),
                ]);
        });

        ResetPassword::toMailUsing(function (mixed $notifiable, string $token): MailMessage {
            $email = $notifiable instanceof User ? $notifiable->email : '';

            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $email,
            ], false));

            return (new MailMessage)
                ->subject(__('Reset your password'))
                ->view('emails.reset-password', [
                    'userName' => $notifiable instanceof User ? $notifiable->name : '',
                    'resetUrl' => $url,
                    'expiresInMinutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
                ]);
        });
    }

    /**
     * Apply admin-managed settings to the framework configuration.
     */
    protected function applySettings(): void
    {
        try {
            $settings = app(SettingsService::class);

            config([
                'app.name' => $settings->get('site_name') ?: config('app.name'),
                'mail.from.name' => $settings->get('mail_from_name') ?: config('mail.from.name'),
                'mail.from.address' => $settings->get('mail_from_address') ?: config('mail.from.address'),
                'services.paypal.me_username' => $settings->get('paypal_me_username') ?: config('services.paypal.me_username'),
                'services.textbee.api_key' => $settings->get('textbee_api_key') ?: config('services.textbee.api_key'),
                'services.textbee.device_id' => $settings->get('textbee_device_id') ?: config('services.textbee.device_id'),
            ]);

            $this->app->make(MailConfiguration::class)->apply($settings);
        } catch (Throwable) {
            // The settings table is not available yet (fresh install or migration).
        }
    }

    /**
     * Register the PHPMailer SMTP transport with Laravel's mail manager.
     */
    protected function registerMailTransport(): void
    {
        Mail::extend('phpmailer', function (array $config): PhpMailerTransport {
            return new PhpMailerTransport(
                host: (string) ($config['host'] ?? '127.0.0.1'),
                port: (int) ($config['port'] ?? 587),
                username: $config['username'] ?? null,
                password: $config['password'] ?? null,
                encryption: (string) ($config['encryption'] ?? 'tls'),
                timeout: (int) ($config['timeout'] ?? 15),
                localDomain: (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost'),
            );
        });
    }
}
