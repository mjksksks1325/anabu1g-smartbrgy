<?php

namespace App\Console\Commands;

use App\Models\CabinetDevice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('cabinet:provision {identifier : Cabinet identifier, e.g. CAB-01} {--name= : Display name} {--rotate : Replace an existing token}')]
#[Description('Register a local cabinet device and issue its API token')]
class ProvisionCabinetDevice extends Command
{
    public function handle(): int
    {
        $identifier = trim((string) $this->argument('identifier'));
        $name = trim((string) $this->option('name'));

        if ($identifier === '' || mb_strlen($identifier) > 255 || ($name !== '' && mb_strlen($name) > 255)) {
            $this->error('Identifier and name must be between 1 and 255 characters.');

            return self::FAILURE;
        }

        $cabinet = CabinetDevice::query()->firstOrNew(['identifier' => $identifier]);

        if ($cabinet->api_token_hash !== null && ! $this->option('rotate')) {
            $this->error('This cabinet already has a token. Use --rotate to replace it.');

            return self::FAILURE;
        }

        $token = Str::random(64);
        $cabinet->name = $name !== '' ? $name : ($cabinet->name ?? $identifier);
        $cabinet->api_token_hash = hash('sha256', $token);
        $cabinet->save();

        $this->info('Cabinet '.$identifier.' is ready. Store this token on the Raspberry Pi; it will only be shown once:');
        $this->line($token);

        return self::SUCCESS;
    }
}
