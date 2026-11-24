<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RmmAdmin extends Command
{
    protected $signature = 'rmm:admin {email} {--name=Admin} {--password-file= : read password from this file; if missing, a random one is generated and written there (chmod 600)}';
    protected $description = 'Create or reset the dashboard admin account (password never printed)';

    public function handle(): int
    {
        $file = $this->option('password-file');
        if (! $file) {
            $this->error('--password-file is required');
            return self::FAILURE;
        }
        if (is_file($file) && trim((string) file_get_contents($file)) !== '') {
            $password = trim((string) file_get_contents($file));
        } else {
            $password = Str::password(20, symbols: false);
            $old = umask(0077);
            file_put_contents($file, $password."\n");
            umask($old);
            @chmod($file, 0600);
        }
        $user = User::updateOrCreate(['email' => $this->argument('email')], [
            'name' => $this->option('name'),
            'password' => $password,
        ]);
        $this->info("admin user #{$user->id} {$user->email} ready (password in {$file})");
        return self::SUCCESS;
    }
}
