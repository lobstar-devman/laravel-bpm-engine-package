<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:list-api-tokens')]
#[Description('List Sanctum API tokens by user and role, for MCP Inspector testing')]
class ListApiTokens extends Command
{
    /**
     * Execute the console command.
     *
     * Sanctum only stores a hash of each token's plaintext value, so an
     * existing token's bearer secret can never be displayed here — only
     * newly created tokens (php artisan tinker or
     * scripts/generate-sanctum-token.sh) print the plaintext, once, at
     * creation time.
     */
    public function handle(): int
    {
        $rows = User::query()
            ->with('tokens')
            ->get()
            ->flatMap(fn (User $user) => $user->tokens->map(fn ($token) => [
                $user->role->value,
                $user->email,
                $token->name,
                $token->created_at?->toDateTimeString(),
                $token->last_used_at?->toDateTimeString() ?? 'never',
            ]));

        if ($rows->isEmpty()) {
            $this->info('No API tokens found.');

            return self::SUCCESS;
        }

        $this->table(['Role', 'Email', 'Token Name', 'Created At', 'Last Used'], $rows);

        return self::SUCCESS;
    }
}
