<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocaleSwitcherTest extends TestCase
{
    public function test_guarani_locale_uses_the_paraguay_flag(): void
    {
        $this->withSession(['locale' => 'gn'])
            ->get(route('login'))
            ->assertOk()
            ->assertSee('🇵🇾', false)
            ->assertSee('GN', false)
            ->assertDontSee('🪶', false);
    }
}
