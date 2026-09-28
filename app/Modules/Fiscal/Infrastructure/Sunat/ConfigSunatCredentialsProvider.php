<?php

namespace App\Modules\Fiscal\Infrastructure\Sunat;

use App\Modules\Fiscal\Application\Contracts\SunatCredentialsProvider;
use App\Modules\Fiscal\Application\Data\SunatCredentials;
use App\Modules\Fiscal\Domain\Enums\SunatEnvironment;
use Illuminate\Contracts\Config\Repository;
use RuntimeException;

final class ConfigSunatCredentialsProvider implements SunatCredentialsProvider
{
    public function __construct(private Repository $config) {}

    /**
     * The Beta configuration still exposes a single SOL credential pair; the
     * tenant id is part of the contract so tenant-scoped credentials can be
     * introduced without touching callers. No request payload can influence it.
     */
    public function credentialsFor(string $tenantId): SunatCredentials
    {
        $environment = SunatEnvironment::tryFrom((string) $this->config->get('sunat.environment'));
        $username = trim((string) $this->config->get('sunat.credentials.username'));
        $password = (string) $this->config->get('sunat.credentials.password');

        if ($environment === null || $username === '' || $password === '') {
            throw new RuntimeException('SUNAT_CREDENTIALS_NOT_CONFIGURED');
        }

        if ($environment === SunatEnvironment::Production && ! $this->config->get('sunat.production_enabled', false)) {
            throw new RuntimeException('SUNAT_PRODUCTION_DISABLED');
        }

        return new SunatCredentials($username, $password, $environment);
    }
}
