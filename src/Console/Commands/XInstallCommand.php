<?php

declare(strict_types=1);

namespace Darvis\ApiX\Console\Commands;

use Darvis\ApiX\Exceptions\XException;
use Darvis\ApiX\Support\EnvironmentFile;
use Darvis\ApiX\Support\PostText;
use Darvis\ApiX\Support\Subscription;
use Darvis\ApiX\Support\XConfig;
use Darvis\ApiX\XClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Laravel\Mcp\Server\Registrar;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class XInstallCommand extends Command
{
    /**
     * The .env variable per credential key, in the order the wizard asks for them.
     */
    public const KEYS = [
        'consumer_key' => 'X_CONSUMER_KEY',
        'consumer_secret' => 'X_CONSUMER_SECRET',
        'access_token' => 'X_ACCESS_TOKEN',
        'access_token_secret' => 'X_ACCESS_TOKEN_SECRET',
    ];

    /**
     * How the X developer console names each key, and where to find it.
     */
    private const LABELS = [
        'consumer_key' => 'Consumer Key',
        'consumer_secret' => 'Consumer Secret',
        'access_token' => 'Access Token',
        'access_token_secret' => 'Access Token Secret',
    ];

    private const HINTS = [
        'consumer_key' => 'Keys & Tokens, OAuth 1.0 Keys. Regenerate shows the key together with its secret.',
        'consumer_secret' => 'Shown next to the Consumer Key, only right after you (re)generate it.',
        'access_token' => 'Keys & Tokens, OAuth 1.0 Keys, the row "For @you, Read and write". Not the OAuth 2.0 token.',
        'access_token_secret' => 'Shown next to the Access Token, only right after you generate it.',
    ];

    public const REPOSITORY = 'https://github.com/ArvidDeJong/api-x';

    /**
     * @var string
     */
    protected $signature = 'x:install
        {--consumer-key= : Consumer Key of the X app (OAuth 1.0 Keys)}
        {--consumer-secret= : Consumer Secret of the X app (OAuth 1.0 Keys)}
        {--access-token= : Access token of your account}
        {--access-token-secret= : Access token secret of your account}
        {--subscription= : X subscription of the account: none, basic, premium or premium_plus}
        {--daily-limit= : Most posts per day, 0 switches the limit off}
        {--live : Post for real (X_DRY_RUN=false)}
        {--verify : Check the keys with X (one billed read request)}
        {--mcp : Add the MCP server to .mcp.json}
        {--config : Publish config/api_x.php}';

    /**
     * @var string
     */
    protected $description = 'Set up darvis/api-x step by step: X app, keys, subscription, safety limits, MCP server and a dry run';

    /**
     * Outcome per wizard step, shown in the summary.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private array $results = [];

    public function handle(XClient $client): int
    {
        if (! is_file($this->laravel->environmentFilePath())) {
            $this->components->error('There is no .env file. Run "cp .env.example .env" and "php artisan key:generate" first, then start this command again.');

            return self::FAILURE;
        }

        return $this->input->isInteractive()
            ? $this->runWizard($client)
            : $this->installWithoutQuestions($client);
    }

    /**
     * Walk a first-time user through every setting. Each answer is written to .env as soon as it
     * is given, so stopping halfway keeps the finished steps.
     */
    private function runWizard(XClient $client): int
    {
        intro(' darvis/api-x setup ');

        note(implode("\n", [
            'This wizard sets up the package in 7 short steps and explains each one.',
            'Every answer is saved to .env right away. Run it again at any time with',
            'php artisan x:install to check or change a setting.',
        ]));

        $steps = [
            'The X app' => fn (): bool => $this->stepApp(),
            'Keys' => fn (): bool => $this->stepKeys(),
            'Check the keys' => fn (): bool => $this->stepVerify($client),
            'Subscription' => fn (): bool => $this->stepSubscription(),
            'Safety' => fn (): bool => $this->stepSafety(),
            'MCP server' => fn (): bool => $this->stepMcp(),
            'Dry run' => fn (): bool => $this->stepDryRun($client),
        ];

        $number = 0;

        foreach ($steps as $title => $step) {
            $number++;
            $this->newLine();
            $this->line("<fg=cyan;options=bold>Step {$number} of ".count($steps)." · {$title}</>");

            if (! $step()) {
                $this->renderSummary();

                return self::FAILURE;
            }
        }

        $this->publishConfig();
        $this->renderSummary();
        $this->askForStar();

        return self::SUCCESS;
    }

    /**
     * The flag-driven install for scripts. It never asks anything and only touches what the
     * options name.
     */
    private function installWithoutQuestions(XClient $client): int
    {
        $variables = [];

        foreach (self::KEYS as $key => $variable) {
            $value = $this->option(str_replace('_', '-', $key));

            if (is_string($value) && trim($value) !== '') {
                $variables[$variable] = trim($value);
            }
        }

        $given = [];
        foreach (self::KEYS as $key => $variable) {
            if (isset($variables[$variable])) {
                $problem = $this->keyProblem($key, $variables[$variable], $given);

                if ($problem !== null) {
                    $this->components->error('--'.str_replace('_', '-', $key).': '.$problem);

                    return self::FAILURE;
                }

                $given[$key] = $variables[$variable];
            }
        }

        $subscription = $this->option('subscription');
        if (is_string($subscription) && $subscription !== '') {
            if (Subscription::tryFrom($subscription) === null) {
                $this->components->error('--subscription must be one of: '.implode(', ', array_column(Subscription::cases(), 'value')).'.');

                return self::FAILURE;
            }

            $variables['X_SUBSCRIPTION'] = $subscription;
        }

        $limit = $this->option('daily-limit');
        if (is_string($limit) && $limit !== '') {
            if (! ctype_digit($limit)) {
                $this->components->error('--daily-limit must be a whole number of 0 or more.');

                return self::FAILURE;
            }

            $variables['X_DAILY_LIMIT'] = $limit;
        }

        if ($this->option('live')) {
            $variables['X_DRY_RUN'] = false;
        }

        if ($variables !== []) {
            $this->saveEnvironment($variables);
            $this->result('.env', 'ok', implode(', ', array_keys($variables)));
        }

        if ($this->option('verify') && ! $this->verifyKeys($client)) {
            $this->renderSummary();

            return self::FAILURE;
        }

        if ($this->option('mcp')) {
            $this->addToMcpJson();
        }

        $this->publishConfig();
        $this->renderSummary();

        return self::SUCCESS;
    }

    private function stepApp(): bool
    {
        note(implode("\n", [
            'The package posts as your own X account with four OAuth 1.0 keys. To get them:',
            '',
            '1. Open https://console.x.com, go to Apps and open (or create) your app.',
            '2. Settings: set App permissions to "Read and write" and Type of App to',
            '   "Web App, Automated App or Bot". Callback URL and Website URL are required',
            '   but not used by this package: any URL of your own site will do. Save.',
            '3. Keys & Tokens, section OAuth 1.0 Keys: copy the Consumer Key and Consumer',
            '   Secret, then generate the Access Token and Access Token Secret.',
            '   You do not need the Bearer Token or anything under OAuth 2.0 Keys.',
            '',
            'Generate the access token after step 2. A token made while the app could only',
            'read keeps that permission, and every post is then refused with HTTP 403.',
            'X bills API use per request, so add some credit under Billing, Credits.',
        ]));

        if (XConfig::credentials() !== null) {
            $this->result('X app', 'ok', 'keys already in .env');

            return true;
        }

        if (! confirm('Do you have the four keys at hand?', true)) {
            $this->result('X app', 'todo', 'create the app and run php artisan x:install again');

            return false;
        }

        $this->result('X app', 'ok', 'keys at hand');

        return true;
    }

    private function stepKeys(): bool
    {
        $variables = [];
        $known = [];

        foreach (self::KEYS as $key => $variable) {
            $current = $this->currentValue($variable);

            $value = trim(password(
                'Paste the '.self::LABELS[$key],
                required: $current === null,
                validate: fn (string $value): ?string => trim($value) === '' ? null : $this->keyProblem($key, trim($value), $known),
                hint: self::HINTS[$key].($current !== null ? ' Leave empty to keep the current value (…'.substr($current, -4).').' : ''),
            ));

            if ($value !== '') {
                $variables[$variable] = $value;
            }

            $known[$key] = $value !== '' ? $value : (string) $current;
        }

        if ($variables !== []) {
            $this->saveEnvironment($variables);
        }

        $this->result('Keys', 'ok', $variables === [] ? 'kept the current keys' : count($variables).' saved to .env');

        return true;
    }

    /**
     * Catch the mix-ups a beginner makes in the console before X answers with a bare 401:
     * the same value pasted twice, or a Consumer Key pasted as the Access Token.
     *
     * @param  array<string, string>  $known  Values of the keys asked before this one.
     */
    public function keyProblem(string $key, string $value, array $known): ?string
    {
        foreach ($known as $otherKey => $other) {
            if ($other !== '' && hash_equals($other, $value)) {
                return 'This is the same value as the '.self::LABELS[$otherKey].'. Each of the four keys is different; copy the '.self::LABELS[$key].' from its own row.';
            }
        }

        if ($key === 'access_token' && preg_match('/^\d+-\S+$/', $value) !== 1) {
            return 'An Access Token starts with your account id and a dash, for example 1234567890-AbCd…. Click Generate on the Access Token row under OAuth 1.0 Keys (not Regenerate on the Consumer Key).';
        }

        return null;
    }

    private function stepVerify(XClient $client): bool
    {
        if (! confirm('Check the keys with X now? X bills this as one read request.', true)) {
            $this->result('Check', 'skip', 'not checked');

            return true;
        }

        return $this->verifyKeys($client);
    }

    private function verifyKeys(XClient $client): bool
    {
        try {
            $account = spin(fn (): array => $client->account(), 'Asking X who these keys belong to…');
        } catch (XException $exception) {
            $this->components->error($exception->getMessage());
            $this->result('Check', 'fail', 'X refused the keys; see the message above');

            return false;
        }

        $this->components->info("The keys belong to @{$account['username']} ({$account['name']}).");
        $this->result('Check', 'ok', '@'.$account['username']);

        return true;
    }

    private function stepSubscription(): bool
    {
        note(implode("\n", [
            'With a paid X subscription (Basic, Premium or Premium+) X accepts posts up to',
            PostText::LONG_MAX_LENGTH.' characters instead of '.PostText::MAX_LENGTH.'. Pick the one of the account that posts;',
            'with the wrong one, X refuses long posts the package lets through.',
        ]));

        $subscription = Subscription::from(select(
            'Which X subscription does the account have?',
            collect(Subscription::cases())->mapWithKeys(fn (Subscription $case): array => [
                $case->value => $case->label().' ('.number_format($case->maxLength()).' characters)',
            ])->all(),
            XConfig::subscription()->value,
        ));

        $this->saveEnvironment(['X_SUBSCRIPTION' => $subscription->value]);
        $this->result('Subscription', 'ok', $subscription->label().', posts up to '.number_format($subscription->maxLength()).' characters');

        return true;
    }

    private function stepSafety(): bool
    {
        note(implode("\n", [
            'Three settings keep an agent from running up a bill:',
            '',
            'Daily limit: the most posts per day. Dry runs do not count.',
            'Links: X bills a post with a link at a much higher rate, so they are refused by default.',
            'Dry run: every check runs, nothing is sent. Start here and switch when you trust it.',
        ]));

        $limit = text(
            'Most posts per day',
            default: (string) XConfig::dailyLimit(),
            required: true,
            validate: fn (string $value): ?string => ctype_digit($value) ? null : 'Enter a whole number of 0 or more.',
            hint: '0 switches the limit off.',
        );

        $links = confirm('Allow posts with a link?', XConfig::allowLinks(), hint: 'X bills them at a much higher rate.');

        $mode = select('How should posts go out?', [
            'dry' => 'Dry run: check posts but send nothing (recommended to start)',
            'live' => 'Post for real',
        ], XConfig::dryRun() || XConfig::credentials() === null ? 'dry' : 'live');

        $this->saveEnvironment([
            'X_ALLOW_LINKS' => $links,
            'X_DAILY_LIMIT' => $limit,
            'X_DRY_RUN' => $mode === 'dry',
        ]);

        $this->result('Safety', 'ok', sprintf(
            '%s, links %s, %s',
            $limit === '0' ? 'no daily limit' : "{$limit} posts a day",
            $links ? 'allowed' : 'refused',
            $mode === 'dry' ? 'dry run' : 'live',
        ));

        return true;
    }

    private function stepMcp(): bool
    {
        if (! class_exists(Registrar::class)) {
            note('The MCP server needs laravel/mcp (Laravel Boost installs it). Run "composer require laravel/mcp" and this wizard again to set it up.');
            $this->result('MCP server', 'skip', 'laravel/mcp is not installed');

            return true;
        }

        if (! XConfig::mcpEnabled()) {
            $this->result('MCP server', 'skip', 'switched off with X_MCP_ENABLED=false');

            return true;
        }

        note(implode("\n", [
            'The MCP server lets Claude or another agent post through the tool post-update.',
            'For Claude Code in this project it goes in .mcp.json. For every other project',
            'and for the Claude desktop app, add it once with:',
            '',
            '  '.$this->userScopeCommand(),
        ]));

        if (confirm('Add the server to .mcp.json of this project?', true)) {
            $this->addToMcpJson();
        } else {
            $this->result('MCP server', 'skip', 'not added to .mcp.json');
        }

        return true;
    }

    private function stepDryRun(XClient $client): bool
    {
        if (! confirm('Run a dry run post now to check the setup? Nothing is sent.', true)) {
            $this->result('Dry run', 'skip', 'not run');

            return true;
        }

        try {
            $result = $client->post('Testing darvis/api-x', dryRun: true);
        } catch (XException $exception) {
            $this->components->error($exception->getMessage());
            $this->result('Dry run', 'fail', 'see the message above');

            return false;
        }

        $this->result('Dry run', 'ok', $result->remainingToday === null
            ? 'passed'
            : "passed, {$result->remainingToday} posts left today");

        return true;
    }

    /**
     * Add the server to .mcp.json in the project root, keeping every other server.
     */
    private function addToMcpJson(): void
    {
        $path = base_path('.mcp.json');
        $handle = XConfig::mcpHandle();
        $contents = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];

        if (! is_array($contents)) {
            $this->components->warn('.mcp.json is not valid JSON; left it alone.');
            $this->result('MCP server', 'todo', 'fix .mcp.json and add the server by hand');

            return;
        }

        if (isset($contents['mcpServers'][$handle])) {
            $this->result('MCP server', 'ok', "\"{$handle}\" already in .mcp.json");

            return;
        }

        $contents['mcpServers'][$handle] = [
            'command' => 'php',
            'args' => ['artisan', 'mcp:start', $handle],
        ];

        file_put_contents($path, json_encode($contents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->result('MCP server', 'ok', "\"{$handle}\" added to .mcp.json");
    }

    private function userScopeCommand(): string
    {
        $handle = XConfig::mcpHandle();

        return "claude mcp add --scope user {$handle} -- php ".base_path('artisan')." mcp:start {$handle}";
    }

    private function renderSummary(): void
    {
        $this->newLine();
        $this->line('<options=bold>Summary</>');

        foreach ($this->results as [$step, $status, $detail]) {
            $this->components->twoColumnDetail($step, match ($status) {
                'ok' => "<fg=green>✔</> {$detail}",
                'skip' => "<fg=gray>–</> {$detail}",
                'todo' => "<fg=yellow>!</> {$detail}",
                default => "<fg=red>✘</> {$detail}",
            });
        }

        $this->newLine();
        $this->components->bulletList([
            '.env is not in git: run this command on every machine that posts.',
            'Post from the terminal: php artisan x:post "Hello" --image=path/to/image.png',
            'Post from the browser: '.rtrim((string) config('app.url'), '/').XConfig::uiPath().' (needs Livewire and Flux; run php artisan migrate for the history)',
        ]);

        outro(collect($this->results)->contains(fn (array $result): bool => in_array($result[1], ['todo', 'fail'], true))
            ? 'Almost done: see the items marked above.'
            : 'darvis/api-x is ready.');
    }

    /**
     * One question at the end of the interactive wizard, never in the flag-driven install.
     */
    private function askForStar(): void
    {
        if (! confirm('Star darvis/api-x on GitHub? A star helps other developers find the package.', true, hint: self::REPOSITORY)) {
            return;
        }

        Process::run(match (PHP_OS_FAMILY) {
            'Darwin' => ['open', self::REPOSITORY],
            'Windows' => ['cmd', '/c', 'start', '', self::REPOSITORY],
            default => ['xdg-open', self::REPOSITORY],
        });

        $this->components->info('Thank you! '.self::REPOSITORY);
    }

    private function publishConfig(): void
    {
        if ($this->option('config') && ! file_exists(config_path('api_x.php'))) {
            $this->callSilently('vendor:publish', ['--tag' => 'api-x-config']);
            $this->result('Config', 'ok', 'published to config/api_x.php');
        }
    }

    /**
     * Write to .env, rebuild a cached config that would hide the new values and apply them to
     * the running config, so later steps already use them.
     *
     * @param  array<string, string|bool>  $variables
     */
    private function saveEnvironment(array $variables): void
    {
        (new EnvironmentFile($this->laravel->environmentFilePath()))->set($variables);

        if ($this->laravel->configurationIsCached()) {
            $this->callSilently('config:cache');
        }

        $config = [
            'X_ALLOW_LINKS' => 'api_x.allow_links',
            'X_DAILY_LIMIT' => 'api_x.daily_limit',
            'X_DRY_RUN' => 'api_x.dry_run',
            'X_SUBSCRIPTION' => 'api_x.subscription',
        ];
        foreach (self::KEYS as $key => $variable) {
            $config[$variable] = 'api_x.credentials.'.$key;
        }

        foreach ($variables as $variable => $value) {
            if (isset($config[$variable])) {
                config()->set($config[$variable], $variable === 'X_DAILY_LIMIT' ? (int) $value : $value);
            }
        }
    }

    private function result(string $step, string $status, string $detail): void
    {
        $this->results[] = [$step, $status, $detail];
    }

    private function currentValue(string $variable): ?string
    {
        return (new EnvironmentFile($this->laravel->environmentFilePath()))->get($variable);
    }
}
