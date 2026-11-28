<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The connection columns grouped by the tool that owns them.
     *
     * @var array<string, array{identifier: string, columns: list<string>}>
     */
    private const CREDENTIAL_GROUPS = [
        'whatsapp' => [
            'identifier' => 'whatsapp_phone_number_id',
            'columns' => [
                'whatsapp_enabled',
                'whatsapp_display_number',
                'whatsapp_phone_number_id',
                'whatsapp_business_account_id',
                'whatsapp_access_token',
                'whatsapp_order_updates',
                'whatsapp_reservation_updates',
            ],
        ],
        'facebook' => [
            'identifier' => 'facebook_page_id',
            'columns' => [
                'facebook_enabled',
                'facebook_page_name',
                'facebook_page_id',
                'facebook_page_access_token',
            ],
        ],
        'email' => [
            'identifier' => 'email_reply_to',
            'columns' => [
                'email_marketing_enabled',
                'email_reply_to',
                'email_footer',
            ],
        ],
        'instagram' => [
            'identifier' => 'instagram_account_id',
            'columns' => [
                'instagram_enabled',
                'instagram_username',
                'instagram_account_id',
                'instagram_access_token',
            ],
        ],
        'twitter' => [
            'identifier' => 'twitter_user_id',
            'columns' => [
                'twitter_enabled',
                'twitter_username',
                'twitter_user_id',
                'twitter_access_token',
                'twitter_refresh_token',
                'twitter_token_expires_at',
            ],
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('whatsapp_enabled')->default(false);
            $table->string('whatsapp_display_number', 40)->nullable();
            $table->string('whatsapp_phone_number_id', 64)->nullable();
            $table->string('whatsapp_business_account_id', 64)->nullable();
            $table->text('whatsapp_access_token')->nullable();
            $table->boolean('whatsapp_order_updates')->default(true);
            $table->boolean('whatsapp_reservation_updates')->default(true);
            $table->boolean('facebook_enabled')->default(false);
            $table->string('facebook_page_name', 120)->nullable();
            $table->string('facebook_page_id', 64)->nullable();
            $table->text('facebook_page_access_token')->nullable();
            $table->boolean('email_marketing_enabled')->default(true);
            $table->string('email_reply_to', 120)->nullable();
            $table->text('email_footer')->nullable();
            $table->boolean('instagram_enabled')->default(false);
            $table->string('instagram_username', 120)->nullable();
            $table->string('instagram_account_id', 64)->nullable();
            $table->text('instagram_access_token')->nullable();
            $table->boolean('twitter_enabled')->default(false);
            $table->string('twitter_username', 50)->nullable();
            $table->string('twitter_user_id', 32)->nullable();
            $table->text('twitter_access_token')->nullable();
            $table->text('twitter_refresh_token')->nullable();
            $table->timestamp('twitter_token_expires_at')->nullable();

            $table->index('whatsapp_phone_number_id');
            $table->index('facebook_page_id');
            $table->index('instagram_account_id');
            $table->index('twitter_user_id');
        });

        $this->copyProfileCredentialsToUsers();

        $columns = $this->credentialColumns();

        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropIndex(['whatsapp_phone_number_id']);
            $table->dropIndex(['facebook_page_id']);
            $table->dropIndex(['instagram_account_id']);
            $table->dropIndex(['twitter_user_id']);
        });

        Schema::table('profiles', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->boolean('whatsapp_enabled')->default(false);
            $table->string('whatsapp_display_number', 40)->nullable();
            $table->string('whatsapp_phone_number_id', 64)->nullable();
            $table->string('whatsapp_business_account_id', 64)->nullable();
            $table->text('whatsapp_access_token')->nullable();
            $table->boolean('whatsapp_order_updates')->default(true);
            $table->boolean('whatsapp_reservation_updates')->default(true);
            $table->boolean('facebook_enabled')->default(false);
            $table->string('facebook_page_name', 120)->nullable();
            $table->string('facebook_page_id', 64)->nullable();
            $table->text('facebook_page_access_token')->nullable();
            $table->boolean('email_marketing_enabled')->default(true);
            $table->string('email_reply_to', 120)->nullable();
            $table->text('email_footer')->nullable();
            $table->boolean('instagram_enabled')->default(false);
            $table->string('instagram_username', 120)->nullable();
            $table->string('instagram_account_id', 64)->nullable();
            $table->text('instagram_access_token')->nullable();
            $table->boolean('twitter_enabled')->default(false);
            $table->string('twitter_username', 50)->nullable();
            $table->string('twitter_user_id', 32)->nullable();
            $table->text('twitter_access_token')->nullable();
            $table->text('twitter_refresh_token')->nullable();
            $table->timestamp('twitter_token_expires_at')->nullable();

            $table->index('whatsapp_phone_number_id');
            $table->index('facebook_page_id');
            $table->index('instagram_account_id');
            $table->index('twitter_user_id');
        });

        $this->copyUserCredentialsToProfiles();

        $columns = $this->credentialColumns();

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['whatsapp_phone_number_id']);
            $table->dropIndex(['facebook_page_id']);
            $table->dropIndex(['instagram_account_id']);
            $table->dropIndex(['twitter_user_id']);
        });

        Schema::table('users', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }

    /**
     * Copy each tool's connection from the profile that holds it to the account.
     */
    private function copyProfileCredentialsToUsers(): void
    {
        DB::table('users')->orderBy('id')->pluck('id')->each(function (int|string $userId): void {
            $profiles = DB::table('profiles')->where('user_id', $userId)->orderBy('id')->get();

            if ($profiles->isEmpty()) {
                return;
            }

            $values = [];

            foreach (self::CREDENTIAL_GROUPS as $group) {
                $profile = $profiles->first(fn (object $profile): bool => filled(data_get($profile, $group['identifier'])))
                    ?? $profiles->first();

                foreach ($group['columns'] as $column) {
                    $values[$column] = data_get($profile, $column);
                }
            }

            DB::table('users')->where('id', $userId)->update($values);
        });
    }

    /**
     * Copy each account's connections back to its first profile.
     */
    private function copyUserCredentialsToProfiles(): void
    {
        DB::table('users')->orderBy('id')->pluck('id')->each(function (int|string $userId): void {
            $user = DB::table('users')->where('id', $userId)->first();
            $profile = DB::table('profiles')->where('user_id', $userId)->orderBy('id')->first();

            if ($user === null || $profile === null) {
                return;
            }

            $values = [];

            foreach ($this->credentialColumns() as $column) {
                $values[$column] = data_get($user, $column);
            }

            DB::table('profiles')->where('id', $profile->id)->update($values);
        });
    }

    /**
     * Every connection column, in tool order.
     *
     * @return list<string>
     */
    private function credentialColumns(): array
    {
        return array_merge(...array_map(
            fn (array $group): array => $group['columns'],
            array_values(self::CREDENTIAL_GROUPS),
        ));
    }
};
