<?php

namespace App\Modules\Fiscal\Application\Data;

final readonly class XmlValidationResult
{
    /** @param list<array{line: int, column: int, code: int, message: string}> $errors */
    public function __construct(public bool $valid, public array $errors = []) {}
}
