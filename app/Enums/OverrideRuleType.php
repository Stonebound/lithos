<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OverrideRuleType: string implements HasColor, HasLabel
{
    case TextReplace = 'text_replace';
    case JsonPatch = 'json_patch';
    case YamlPatch = 'yaml_patch';
    case FileAdd = 'file_add';
    case FileRemove = 'file_remove';
    case FileSkip = 'file_skip';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::TextReplace => 'Text Replace',
            self::JsonPatch => 'JSON Patch',
            self::YamlPatch => 'YAML Patch',
            self::FileAdd => 'Add File',
            self::FileRemove => 'Remove Files',
            self::FileSkip => 'Skip Files',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::TextReplace => 'primary',
            self::JsonPatch => 'info',
            self::YamlPatch => 'warning',
            self::FileAdd => 'success',
            self::FileRemove => 'danger',
            self::FileSkip => 'gray',
        };
    }
}
