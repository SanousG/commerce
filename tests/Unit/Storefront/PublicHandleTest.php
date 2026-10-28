<?php

namespace Tests\Unit\Storefront;

use App\Modules\Storefront\Application\PublicHandle;
use InvalidArgumentException;
use Tests\TestCase;

class PublicHandleTest extends TestCase
{
    public function test_it_normalizes_a_public_handle_consistently(): void
    {
        $this->assertSame('tienda-lima', app(PublicHandle::class)->normalize('  Tienda Lima  '));
    }

    public function test_it_rejects_empty_reserved_and_overlong_handles(): void
    {
        foreach (['---', 'admin', str_repeat('a', 64)] as $handle) {
            try {
                app(PublicHandle::class)->normalize($handle);
                $this->fail("Handle '{$handle}' should have been rejected.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
