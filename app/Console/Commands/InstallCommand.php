<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ResponseSigner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * One-time setup of a new server: creates the RSA signing keys and the first admin account.
 * Safe to run again: existing keys and admins are never touched.
 */
#[Signature('licensebox:install {--name= : Admin name} {--email= : Admin email} {--password= : Admin password (prompted when omitted)}')]
#[Description('Create the signing keys and the first admin account on a new server')]
class InstallCommand extends Command
{
    public function handle(ResponseSigner $signer): int
    {
        if ($signer->hasKeys()) {
            $this->components->info('Signing keys already exist (key id '.$signer->fingerprint().'); kept as they are.');
        } else {
            $signer->regenerate();
            $this->components->info('Created signing keys in '.config('licensebox.signing.path').' (key id '.$signer->fingerprint().').');
            $this->components->warn('Back up this directory now. Losing it breaks every SDK you ship with this key.');
        }

        if (User::exists()) {
            $this->components->info('An admin account already exists; skipped.');

            return self::SUCCESS;
        }

        if (! $this->input->isInteractive() && ! ($this->option('email') && $this->option('password'))) {
            $this->components->error('No admin exists yet. Pass --email and --password, or run the command interactively.');

            return self::FAILURE;
        }

        $data = [
            'name' => $this->option('name') ?? text('Admin name', default: 'Admin', required: true),
            'email' => $this->option('email') ?? text('Admin email', required: true),
            'password' => $this->option('password') ?? password('Admin password (min. 8 characters)', required: true),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create($data);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->components->info("Admin {$user->email} created. Log in at ".url('/login').'.');

        return self::SUCCESS;
    }
}
