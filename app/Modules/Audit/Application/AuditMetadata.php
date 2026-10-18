<?php

namespace App\Modules\Audit\Application;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use JsonException;

final class AuditMetadata
{
    public const REDACTED = '[REDACTED]';

    /** @var list<string> */
    private const DANGEROUS_KEYS = [
        'password', 'passwd', 'token', 'secret', 'privatekey', 'certificatepassword', 'sunatpassword',
        'authorization', 'proxyauthorization', 'authheader', 'apikey', 'credential', 'xml', 'cdr',
    ];

    /** @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function sanitize(array $metadata): array
    {
        $sanitized = $this->walk($metadata, 0);

        try {
            $json = json_encode($sanitized, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Audit metadata must be valid JSON data.', previous: $exception);
        }

        if (strlen($json) > 65_536) {
            throw new InvalidArgumentException('Audit metadata may not exceed 64 KiB.');
        }

        return $sanitized;
    }

    private function walk(mixed $value, int $depth): mixed
    {
        if ($depth > 12) {
            throw new InvalidArgumentException('Audit metadata nesting may not exceed 12 levels.');
        }

        if ($value instanceof Model) {
            throw new InvalidArgumentException('Eloquent models may not be stored in audit metadata.');
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (is_object($value) || is_resource($value)) {
            throw new InvalidArgumentException('Audit metadata contains an unsupported value.');
        }

        if (is_float($value) && ! is_finite($value)) {
            throw new InvalidArgumentException('Audit metadata contains a non-finite number.');
        }

        if (! is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && $this->isDangerousKey($key)) {
                $result[$key] = self::REDACTED;
            } else {
                $result[$key] = $this->walk($item, $depth + 1);
            }
        }

        return $result;
    }

    private function isDangerousKey(string $key): bool
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $key));

        foreach (self::DANGEROUS_KEYS as $dangerous) {
            if (str_contains($normalized, $dangerous)) {
                return true;
            }
        }

        return false;
    }
}
