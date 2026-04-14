<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class GenerateDummyPasswordResetLinkCommand extends Command
{
    protected $signature = 'auth:dummy-reset-link
        {--email=reset.dummy@example.com : Dummy user email}
        {--name=Reset Dummy User : Dummy user name}
        {--password=DummyPass123! : Initial password if user is created}
        {--send : Also send reset email via broker}';

    protected $description = 'Create a dummy user (if needed) and generate a ready-to-use reset password link.';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->option('email')));
        $name = trim((string) $this->option('name'));
        $password = (string) $this->option('password');
        $send = (bool) $this->option('send');

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid --email value.');

            return self::FAILURE;
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name !== '' ? $name : 'Reset Dummy User',
                'password' => Hash::make($password !== '' ? $password : 'DummyPass123!'),
            ]
        );

        if ($send) {
            $status = Password::sendResetLink(['email' => $user->email]);
            $this->line('Email send status: '.$status);

            if ($status === Password::RESET_LINK_SENT) {
                $this->info('Reset email sent. Check your Mailtrap inbox.');
                $this->line('User ID: '.$user->id);
                $this->line('Email: '.$user->email);

                if (! $user->wasRecentlyCreated) {
                    $this->comment('User already existed, password was not changed.');
                }

                return self::SUCCESS;
            }
        }

        $token = Password::broker()->createToken($user);
        $url = url('/reset-password/'.$token).'?email='.urlencode($user->email);

        $this->info('Dummy reset password link is ready.');
        $this->line('User ID: '.$user->id);
        $this->line('Email: '.$user->email);
        $this->line('Reset URL: '.$url);

        if (! $user->wasRecentlyCreated) {
            $this->comment('User already existed, password was not changed.');
        }

        return self::SUCCESS;
    }
}
