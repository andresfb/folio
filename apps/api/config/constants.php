<?php

declare(strict_types=1);

return [

    'admin_name' => env('ADMIN_NAME'),

    'admin_email' => env('ADMIN_EMAIL'),

    'providers' => [
        [
            'code' => 'anthropic',
            'name' => 'Anthropic',
            'models' => explode(',', env('ANTHROPIC_MODELS')),
            'enabled' => (bool) env('ANTHROPIC_ENABLED'),
        ],
        [
            'code' => 'gemini',
            'name' => 'Gemini',
            'models' => explode(',', env('GEMINI_MODELS')),
            'enabled' => (bool) env('GEMINI_ENABLED'),
        ],
        [
            'code' => 'openai',
            'name' => 'OpenAI',
            'models' => explode(',', env('OPENAI_MODELS')),
            'enabled' => (bool) env('OPENAI_ENABLED'),
        ],
        [
            'code' => 'openrouter',
            'name' => 'OpenRouter',
            'models' => explode(',', env('OPENROUTER_MODELS')),
            'enabled' => (bool) env('OPENROUTER_ENABLED'),
        ],
        [
            'code' => 'ollama',
            'name' => 'Ollama',
            'models' => explode(',', env('OLLAMA_MODELS')),
            'enabled' => (bool) env('OLLAMA_ENABLED'),
        ],
    ],

];
