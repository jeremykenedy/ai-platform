<?php

declare(strict_types=1);

return [

    'default_local_model' => env('DEFAULT_LOCAL_MODEL', 'llama3.2:latest'),

    'default_embedding_model' => env('DEFAULT_EMBEDDING_MODEL', 'nomic-embed-text:latest'),

    'default_vision_model' => env('DEFAULT_VISION_MODEL', 'llama3.2-vision:11b'),

    'default_code_model' => env('DEFAULT_CODE_MODEL', 'qwen2.5-coder:32b'),

    'default_image_model' => env('DEFAULT_IMAGE_MODEL', 'comfyui:flux-schnell'),

    'default_reasoning_model' => env('DEFAULT_REASONING_MODEL', 'deepseek-r1:32b'),

    'default_transcription_model' => env('DEFAULT_TRANSCRIPTION_MODEL', 'openai:whisper-1'),

    'default_tts_model' => env('DEFAULT_TTS_MODEL', 'elevenlabs:eleven_turbo_v2_5'),

    'auto_routing' => env('MODEL_AUTO_ROUTING', true),

    'prefer_local' => env('PREFER_LOCAL_MODELS', true),

];
