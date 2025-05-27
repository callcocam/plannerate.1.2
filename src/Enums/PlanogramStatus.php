<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\Plannerate\Enums;

enum PlanogramStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Published => 'Publicado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'green'
        };
    }


    // Adiciona o método estático options
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn($case) => ['value' => $case->value, 'label' => $case->label()])
            ->values()
            ->toArray();
    }

    // Adiciona o método estático toArray
    public static function getOptions(): array
    {
        return [
            'draft' => self::Draft->label(),
            'published' => self::Published->label(),
        ];
    }
}
