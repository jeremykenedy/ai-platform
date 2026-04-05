<?php

declare(strict_types=1);

namespace App\Enums;

enum Capability: string
{
    case Chat = 'chat';
    case Streaming = 'streaming';
    case Vision = 'vision';
    case Code = 'code';
    case Reasoning = 'reasoning';
    case FunctionCalling = 'function_calling';
    case Embeddings = 'embeddings';
    case ImageGeneration = 'image_generation';
    case AudioTranscription = 'audio_transcription';
    case AudioGeneration = 'audio_generation';
    case FileAnalysis = 'file_analysis';
    case LongContext = 'long_context';
    case WebSearch = 'web_search';
    case StructuredOutput = 'structured_output';
}
