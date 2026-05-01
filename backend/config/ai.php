<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI / LLM Provider settings
    |--------------------------------------------------------------------------
    | Provider-agnostic via .env. Ollama, OpenAI, Azure, GitHub Models — all
    | use the same OpenAI-compatible chat completions API shape.
    |
    | To switch provider, only .env changes are required; no code changes.
    */
    'provider_url'    => env('AI_PROVIDER_URL', 'http://localhost:11434/v1'),
    'model'           => env('AI_MODEL', 'rizkiagungid/rasxlite:latest'),
    'api_key'         => env('AI_API_KEY', 'ollama'),
    'timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', 30),
    'max_tokens'      => (int) env('AI_MAX_TOKENS', 512),
];
