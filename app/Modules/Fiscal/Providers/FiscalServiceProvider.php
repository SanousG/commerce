<?php

namespace App\Modules\Fiscal\Providers;

use App\Modules\Fiscal\Application\Contracts\DigitalCertificateProvider;
use App\Modules\Fiscal\Application\Contracts\FiscalArtifactStorage;
use App\Modules\Fiscal\Application\Contracts\FiscalDocumentSerializer;
use App\Modules\Fiscal\Application\Contracts\SunatCredentialsProvider;
use App\Modules\Fiscal\Application\Contracts\SunatTransport;
use App\Modules\Fiscal\Infrastructure\Sunat\ConfigSunatCredentialsProvider;
use App\Modules\Fiscal\Infrastructure\Sunat\PrivateFiscalArtifactStorage;
use App\Modules\Fiscal\Infrastructure\Sunat\StorageDigitalCertificateProvider;
use App\Modules\Fiscal\Infrastructure\Sunat\SunatBetaTransport;
use App\Modules\Fiscal\Infrastructure\Sunat\Ubl21FiscalDocumentSerializer;
use Illuminate\Support\ServiceProvider;

final class FiscalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SunatCredentialsProvider::class, ConfigSunatCredentialsProvider::class);
        $this->app->bind(DigitalCertificateProvider::class, StorageDigitalCertificateProvider::class);
        $this->app->bind(FiscalArtifactStorage::class, PrivateFiscalArtifactStorage::class);
        $this->app->bind(FiscalDocumentSerializer::class, Ubl21FiscalDocumentSerializer::class);
        $this->app->bind(SunatTransport::class, SunatBetaTransport::class);
    }
}
