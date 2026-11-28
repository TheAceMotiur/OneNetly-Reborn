<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TwitterNotifier;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class SyncTwitterMentions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'twitter:sync-mentions {--profile= : Only sync the workspace with this username}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll X mentions for connected accounts and send automated replies';

    /**
     * Execute the console command.
     */
    public function handle(TwitterNotifier $notifier): int
    {
        $query = User::query()
            ->where('twitter_enabled', true)
            ->whereNotNull('twitter_user_id')
            ->whereNotNull('twitter_access_token');

        if (is_string($this->option('profile')) && $this->option('profile') !== '') {
            $query->whereHas('profiles', fn (Builder $profiles): Builder => $profiles
                ->where('username', $this->option('profile')));
        }

        $accounts = $query->get();
        $replies = 0;

        foreach ($accounts as $account) {
            try {
                $replies += $notifier->sync($account->workspaceProfile());
            } catch (Throwable $exception) {
                report($exception);
                $this->error("Could not sync @{$account->twitter_username}: {$exception->getMessage()}");
            }
        }

        $this->info("Synced {$accounts->count()} account(s) and sent {$replies} replie(s).");

        return self::SUCCESS;
    }
}
