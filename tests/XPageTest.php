<?php

declare(strict_types=1);

use Darvis\ApiX\Livewire\XPage;
use Darvis\ApiX\Models\XPost;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->useXCredentials();
});

function allowPage(bool $allow = true): void
{
    Gate::define('postToX', fn (?User $user = null): bool => $allow);
}

it('answers 403 outside the local environment when the host defines no gate', function () {
    $this->get('/x')->assertForbidden();

    Livewire::test(XPage::class)->assertForbidden();
});

it('opens in the local environment without a gate', function () {
    $this->app['env'] = 'local';

    $this->get('/x')->assertOk()->assertSee('Post to X');
});

it('follows the host gate', function () {
    allowPage(false);
    $this->get('/x')->assertForbidden();

    allowPage();
    $this->get('/x')->assertOk();
});

it('posts, shows the link and lists the post in the history', function () {
    allowPage();
    Http::fake(['api.x.com/2/tweets' => Http::response(['data' => ['id' => '42']], 201)]);

    Livewire::test(XPage::class)
        ->set('text', 'Hello from the page')
        ->call('post')
        ->assertSet('text', '')
        ->assertSee('https://x.com/i/web/status/42')
        ->assertSee('Hello from the page');

    expect(XPost::sole()->x_id)->toBe('42');
});

it('posts an uploaded image', function () {
    allowPage();
    Http::fake([
        'api.x.com/2/media/upload' => Http::response(['data' => ['id' => '9']]),
        'api.x.com/2/tweets' => Http::response(['data' => ['id' => '10']], 201),
    ]);

    Livewire::test(XPage::class)
        ->set('text', 'With an image')
        ->set('upload', UploadedFile::fake()->createWithContent('card.png', (string) file_get_contents($this->fixture('pixel.png'))))
        ->call('post')
        ->assertHasNoErrors();

    expect(XPost::sole()->media_id)->toBe('9');
});

it('does a dry run and shows why a post is refused', function () {
    allowPage();
    Http::fake();

    Livewire::test(XPage::class)
        ->set('text', 'Checking')
        ->set('dryRun', true)
        ->call('post')
        ->assertSee('Dry run: the post passed every check')
        ->set('text', 'See https://arvid.nl')
        ->call('post')
        ->assertSee('X_ALLOW_LINKS');

    Http::assertNothingSent();
});

it('counts the text the way X does', function () {
    allowPage();

    Livewire::test(XPage::class)
        ->set('text', 'Hi 🚀')
        ->assertSee('5 / 280');
});

it('counts up to the limit of the X subscription', function () {
    allowPage();
    config(['api_x.subscription' => 'premium']);

    Livewire::test(XPage::class)
        ->set('text', 'Hi 🚀')
        ->assertSee('5 / 25000');
});

it('starts in dry run when the config says so', function () {
    allowPage();
    config(['api_x.dry_run' => true]);

    Livewire::test(XPage::class)->assertSet('dryRun', true);
});

it('checks the gate on every update, not only on the page load', function () {
    $state = new stdClass;
    $state->allow = true;
    Gate::define('postToX', fn (?User $user = null): bool => $state->allow);
    Http::fake();

    $component = Livewire::test(XPage::class)->assertOk()->set('text', 'Hello');
    $state->allow = false;

    $component->call('post')->assertForbidden();
    Http::assertNothingSent();
});
