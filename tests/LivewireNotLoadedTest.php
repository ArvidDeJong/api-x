<?php

namespace Darvis\ApiX\Tests;

use Darvis\ApiX\XServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * Livewire is installed in this repository, but a host app may not have it or may exclude its
 * provider from discovery. The package must then boot without the page.
 */
class LivewireNotLoadedTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [XServiceProvider::class];
    }

    public function test_it_boots_without_the_page_when_livewire_is_not_loaded(): void
    {
        $this->assertFalse(Route::has('api-x.page'));
    }
}
