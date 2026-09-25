<?php

declare(strict_types=1);

use Darvis\ApiX\Console\Commands\XInstallCommand;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

const STAR_QUESTION = 'Star darvis/api-x on GitHub? A star helps other developers find the package.';

const SUBSCRIPTION_CHOICES = [
    'none' => 'No subscription (280 characters)',
    'basic' => 'Basic (25,000 characters)',
    'premium' => 'Premium (25,000 characters)',
    'premium_plus' => 'Premium+ (25,000 characters)',
];

const MODE_CHOICES = [
    'dry' => 'Dry run: check posts but send nothing (recommended to start)',
    'live' => 'Post for real',
];

beforeEach(function (): void {
    $this->envDirectory = sys_get_temp_dir().'/api-x-wizard-'.uniqid();
    mkdir($this->envDirectory);
    file_put_contents($this->envDirectory.'/.env', "APP_NAME=Test\n");

    $this->app->useEnvironmentPath($this->envDirectory);

    Process::fake();
    @unlink(base_path('.mcp.json'));
});

afterEach(function (): void {
    @unlink($this->envDirectory.'/.env');
    @rmdir($this->envDirectory);
    @unlink(base_path('.mcp.json'));
});

function wizardEnv(): string
{
    return (string) file_get_contents(test()->envDirectory.'/.env');
}

it('walks a new user through every step and writes .env', function () {
    Http::fake([
        'api.x.com/2/users/me' => Http::response(['data' => ['id' => '1', 'name' => 'Arvid', 'username' => 'arviddejong']]),
    ]);

    $this->artisan('x:install')
        ->expectsConfirmation('Do you have the four keys at hand?', 'yes')
        ->expectsQuestion('Paste the Consumer Key', 'ck')
        ->expectsQuestion('Paste the Consumer Secret', 'cs')
        ->expectsQuestion('Paste the Access Token', '123-at')
        ->expectsQuestion('Paste the Access Token Secret', 'ats')
        ->expectsConfirmation('Check the keys with X now? X bills this as one read request.', 'yes')
        ->expectsOutputToContain('The keys belong to @arviddejong (Arvid).')
        ->expectsChoice('Which X subscription does the account have?', 'premium', SUBSCRIPTION_CHOICES)
        ->expectsQuestion('Most posts per day', '5')
        ->expectsConfirmation('Allow posts with a link?', 'no')
        ->expectsChoice('How should posts go out?', 'dry', MODE_CHOICES)
        ->expectsConfirmation('Add the server to .mcp.json of this project?', 'yes')
        ->expectsConfirmation('Run a dry run post now to check the setup? Nothing is sent.', 'yes')
        ->expectsConfirmation(STAR_QUESTION, 'no')
        ->expectsOutputToContain('darvis/api-x is ready.')
        ->assertSuccessful();

    expect(wizardEnv())
        ->toContain('X_CONSUMER_KEY=ck')
        ->toContain('X_CONSUMER_SECRET=cs')
        ->toContain('X_ACCESS_TOKEN=123-at')
        ->toContain('X_ACCESS_TOKEN_SECRET=ats')
        ->toContain('X_SUBSCRIPTION=premium')
        ->toContain('X_DAILY_LIMIT=5')
        ->toContain('X_ALLOW_LINKS=false')
        ->toContain('X_DRY_RUN=true');

    expect(json_decode((string) file_get_contents(base_path('.mcp.json')), true))
        ->toBe(['mcpServers' => ['x' => ['command' => 'php', 'args' => ['artisan', 'mcp:start', 'x']]]]);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.x.com/2/users/me'
        && str_contains($request->header('Authorization')[0], 'oauth_consumer_key="ck"'));
    Http::assertSentCount(1);
});

it('stops when the user has no keys yet and says what to do', function () {
    $this->artisan('x:install')
        ->expectsConfirmation('Do you have the four keys at hand?', 'no')
        ->expectsOutputToContain('create the app and run php artisan x:install again')
        ->assertFailed();

    expect(wizardEnv())->toBe("APP_NAME=Test\n");
});

it('keeps the current keys when the answer is empty and stops on keys X refuses', function () {
    file_put_contents($this->envDirectory.'/.env', "X_CONSUMER_KEY=old-ck\nX_CONSUMER_SECRET=old-cs\nX_ACCESS_TOKEN=old-at\nX_ACCESS_TOKEN_SECRET=old-ats\n");
    $this->useXCredentials();

    Http::fake([
        'api.x.com/2/users/me' => Http::response(['title' => 'Unauthorized', 'detail' => 'Unauthorized'], 401),
    ]);

    $this->artisan('x:install')
        ->expectsQuestion('Paste the Consumer Key', '')
        ->expectsQuestion('Paste the Consumer Secret', 'new-cs')
        ->expectsQuestion('Paste the Access Token', '')
        ->expectsQuestion('Paste the Access Token Secret', '')
        ->expectsConfirmation('Check the keys with X now? X bills this as one read request.', 'yes')
        ->expectsOutputToContain('X refused to check the keys (HTTP 401): Unauthorized')
        ->assertFailed();

    expect(wizardEnv())
        ->toContain('X_CONSUMER_KEY=old-ck')
        ->toContain('X_CONSUMER_SECRET=new-cs');
});

it('keeps an existing .mcp.json and its other servers', function () {
    file_put_contents(base_path('.mcp.json'), json_encode(['mcpServers' => ['laravel-boost' => ['command' => 'php', 'args' => ['artisan', 'boost:mcp']]]]));

    $this->artisan('x:install', ['--mcp' => true, '--no-interaction' => true])->assertSuccessful();

    expect(array_keys(json_decode((string) file_get_contents(base_path('.mcp.json')), true)['mcpServers']))
        ->toBe(['laravel-boost', 'x']);
});

it('installs without questions from options', function () {
    Http::fake();

    $this->artisan('x:install', [
        '--consumer-key' => 'ck',
        '--consumer-secret' => 'cs',
        '--access-token' => '123-at',
        '--access-token-secret' => 'ats',
        '--subscription' => 'premium_plus',
        '--daily-limit' => '3',
        '--live' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(wizardEnv())
        ->toContain('X_CONSUMER_KEY=ck')
        ->toContain('X_SUBSCRIPTION=premium_plus')
        ->toContain('X_DAILY_LIMIT=3')
        ->toContain('X_DRY_RUN=false');

    Http::assertNothingSent();
    Process::assertNothingRan();
});

it('refuses a subscription X does not have', function () {
    $this->artisan('x:install', ['--subscription' => 'gold', '--no-interaction' => true])
        ->expectsOutputToContain('--subscription must be one of: none, basic, premium, premium_plus.')
        ->assertFailed();

    expect(wizardEnv())->toBe("APP_NAME=Test\n");
});

it('refuses a daily limit that is not a whole number', function () {
    $this->artisan('x:install', ['--daily-limit' => 'many', '--no-interaction' => true])
        ->expectsOutputToContain('--daily-limit must be a whole number')
        ->assertFailed();
});

it('needs a .env file', function () {
    unlink($this->envDirectory.'/.env');

    $this->artisan('x:install', ['--no-interaction' => true])
        ->expectsOutputToContain('There is no .env file.')
        ->assertFailed();
});

it('refuses a Consumer Key pasted as the Access Token', function () {
    $this->artisan('x:install', [
        '--consumer-key' => 'AbCdEfGhIjKlMnOpQrStUvWxY',
        '--access-token' => 'AbCdEfGhIjKlMnOpQrStUvWxY',
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('This is the same value as the Consumer Key.')
        ->assertFailed();

    expect(wizardEnv())->toBe("APP_NAME=Test\n");
});

it('explains what an Access Token looks like', function () {
    $command = new XInstallCommand;

    expect($command->keyProblem('access_token', 'AbCdEfGhIjKlMnOpQrStUvWxY', []))->toContain('starts with your account id and a dash')
        ->and($command->keyProblem('access_token', '2103029279827128320-AbCdEfGhIjKlMnOpQrStUvWxY', []))->toBeNull()
        ->and($command->keyProblem('access_token_secret', 'same', ['consumer_secret' => 'same']))->toContain('same value as the Consumer Secret')
        ->and($command->keyProblem('consumer_key', 'AbCdEfGhIjKlMnOpQrStUvWxY', []))->toBeNull();
});
