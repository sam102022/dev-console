<?php

namespace App\model;

enum EnumDomain: string
{
    case PDV = 'pdv';

    public function getCode(): string
    {
        return $this->value;
    }

    public function getName(): string
    {
        return match ($this) {
            self::PDV => 'Point de Vente',
        };
    }

    /**
     * @return string[]
     */
    public function getSfs(): array
    {
        return match ($this) {
            self::PDV => ['buyers', 'merchandising', 'receipt', 'stores', 'stores-stock', 'stores-result'],
        };
    }
}