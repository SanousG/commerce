<?php

namespace App\Modules\Listings\Application;

readonly class EligibilityResult
{
    /** @param list<string> $reasons */
    public function __construct(public bool $eligible, public array $reasons) {}

    public function toArray(): array
    {
        return ['eligible' => $this->eligible, 'reasons' => $this->reasons];
    }
}
