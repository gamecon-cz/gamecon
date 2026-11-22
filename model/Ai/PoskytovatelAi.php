<?php

declare(strict_types=1);

namespace Gamecon\Ai;

/**
 * Whose AI the admin can reach through an API. The value is stored in `ai_pripojeni` and with each batch
 * in `akce_korektura`, so a case cannot be renamed without a migration. Another provider is a case here
 * and a class implementing {@see DavkyAi}.
 */
enum PoskytovatelAi: string
{
    case ANTHROPIC = 'anthropic';
    case OPENAI = 'openai';

    public function nazev(): string
    {
        return match ($this) {
            self::ANTHROPIC => 'Claude (Anthropic)',
            self::OPENAI    => 'ChatGPT (OpenAI)',
        };
    }

    public function vychoziModel(): string
    {
        return match ($this) {
            self::ANTHROPIC => AnthropicDavkyAi::VYCHOZI_MODEL,
            self::OPENAI    => OpenAiDavkyAi::VYCHOZI_MODEL,
        };
    }

    /**
     * Where an admin of the provider's account creates the token.
     */
    public function urlTokenu(): string
    {
        return match ($this) {
            self::ANTHROPIC => 'https://platform.claude.com/settings/keys',
            self::OPENAI    => 'https://platform.openai.com/api-keys',
        };
    }

    public function davky(string $token, ?string $model = null): DavkyAi
    {
        $model = $model !== null && trim($model) !== ''
            ? trim($model)
            : $this->vychoziModel();

        return match ($this) {
            self::ANTHROPIC => new AnthropicDavkyAi($token, $model),
            self::OPENAI    => new OpenAiDavkyAi($token, $model),
        };
    }
}
