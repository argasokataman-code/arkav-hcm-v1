<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds default AI runtime configuration keys into the settings table.
 *
 * Only inserts if the key does NOT already exist — safe to run multiple times.
 *
 * Keys:
 *   ai_provider_url  — OpenAI-compatible API base URL (Ollama default)
 *   ai_model         — Model identifier
 *   ai_api_key       — API key ('ollama' for local Ollama; real key for OpenAI)
 *   ai_timeout_seconds — HTTP timeout in seconds
 *   ai_max_tokens    — Max tokens per response
 *
 * To change at runtime: update these keys via the admin settings panel.
 * To change at deploy time: set AI_* env vars (AiLlmService falls back to env/config).
 */
class AiSettingsSeeder extends Seeder
{
    private array $defaults = [
        'ai_provider_url'      => ['value' => 'http://localhost:11434/v1', 'group' => 'ai'],
        'ai_model'             => ['value' => 'llama3.2',                   'group' => 'ai'],
        'ai_api_key'           => ['value' => 'ollama',                     'group' => 'ai'],
        'ai_timeout_seconds'   => ['value' => '30',                         'group' => 'ai'],
        'ai_max_tokens'        => ['value' => '512',                        'group' => 'ai'],
    ];

    public function run(): void
    {
        foreach ($this->defaults as $key => $meta) {
            $exists = \DB::table('settings')->where('key', $key)->exists();
            if (! $exists) {
                Setting::set($key, $meta['value'], $meta['group']);
                $this->command->info("AI setting seeded: {$key}");
            } else {
                $this->command->line("AI setting already exists, skipping: {$key}");
            }
        }
    }
}
