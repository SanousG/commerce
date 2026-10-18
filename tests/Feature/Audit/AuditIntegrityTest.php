<?php

namespace Tests\Feature\Audit;

use App\Modules\Audit\Application\AuditMetadata;
use App\Modules\Audit\Domain\Enums\AuditAction;
use App\Modules\Audit\Domain\Models\AuditEntry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AuditIntegrityTest extends AuditFeatureTestCase
{
    public function test_metadata_recursively_redacts_secrets_and_never_persists_raw_token(): void
    {
        $tenant = $this->createTenant();
        $rawToken = 'raw-token-must-never-be-stored';
        $entry = $this->audit($tenant, AuditAction::TenantUpdated, metadata: [
            'token' => $rawToken,
            'nested' => ['api_key' => $rawToken, 'safe' => 'visible'],
            'authorization_header' => 'Bearer '.$rawToken,
        ]);

        $this->assertSame([
            'token' => AuditMetadata::REDACTED,
            'nested' => ['api_key' => AuditMetadata::REDACTED, 'safe' => 'visible'],
            'authorization_header' => AuditMetadata::REDACTED,
        ], $entry->metadata);
        $this->assertStringNotContainsString($rawToken, DB::table('audit_entries')->where('id', $entry->getKey())->value('metadata'));
    }

    public function test_rolled_back_transaction_leaves_no_audit_entry(): void
    {
        $tenant = $this->createTenant();

        try {
            DB::transaction(function () use ($tenant): void {
                $this->audit($tenant, AuditAction::TenantUpdated);
                throw new RuntimeException('force rollback');
            });
            $this->fail('The transaction should have rolled back.');
        } catch (RuntimeException $exception) {
            $this->assertSame('force rollback', $exception->getMessage());
        }

        $this->assertDatabaseCount('audit_entries', 0);
    }

    public function test_database_rejects_update_of_append_only_entry(): void
    {
        $entry = $this->audit($this->createTenant(), AuditAction::TenantUpdated);

        try {
            DB::transaction(fn () => DB::table('audit_entries')->where('id', $entry->getKey())
                ->update(['subject_label' => 'tampered']));
            $this->fail('PostgreSQL accepted an audit UPDATE.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('audit entries are append-only', $exception->getMessage());
        }

        $this->assertDatabaseHas('audit_entries', ['id' => $entry->getKey(), 'subject_label' => null]);
    }

    public function test_database_rejects_delete_of_append_only_entry(): void
    {
        $entry = $this->audit($this->createTenant(), AuditAction::TenantUpdated);

        try {
            DB::transaction(fn () => DB::table('audit_entries')->where('id', $entry->getKey())->delete());
            $this->fail('PostgreSQL accepted an audit DELETE.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('audit entries are append-only', $exception->getMessage());
        }

        $this->assertTrue(AuditEntry::query()->whereKey($entry->getKey())->exists());
    }
}
