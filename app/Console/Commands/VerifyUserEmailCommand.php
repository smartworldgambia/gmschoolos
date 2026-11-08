<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyUserEmailCommand extends Command
{
    protected $signature = 'schoolos:verify-email {email : Email address of the user to mark as verified}';

    protected $description = 'Operator-only: mark a user email as verified (e.g. dev environments using the log mailer). Audited in the log.';

    public function handle(): int
    {
        $user = User::query()->where('email', strtolower(trim((string) $this->argument('email'))))->first();

        if (! $user) {
            $this->error('No user found with that email.');

            return self::FAILURE;
        }

        if ($user->hasVerifiedEmail()) {
            $this->info('Email was already verified.');

            return self::SUCCESS;
        }

        $user->markEmailAsVerified();
        Log::channel(config('logging.default'))->warning('security.email_verified_by_operator', [
            'user_id' => $user->id,
            'email' => $user->email,
            'school_id' => $user->school_id,
            'operator' => get_current_user(),
        ]);

        $this->info("Marked {$user->email} as verified.");

        return self::SUCCESS;
    }
}
