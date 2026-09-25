<?php

declare(strict_types=1);

use Darvis\ApiX\Support\XConfig;

/**
 * XConfig is the one place that reads the package config. These tests guard the two things
 * that go wrong once a default is written down twice: an accessor that disagrees with the config
 * file, and a caller that reaches past the accessor and keeps its own stale fallback.
 */
function packageRoot(string $path = ''): string
{
    return dirname(__DIR__).($path === '' ? '' : '/'.$path);
}

it('returns the values the config file ships', function () {
    $config = require packageRoot('config/api_x.php');

    expect(XConfig::allowLinks())->toBe($config['allow_links'])
        ->and(XConfig::apiUrl())->toBe($config['api_url'])
        ->and(XConfig::cacheStore())->toBe($config['cache_store'])
        ->and(XConfig::credentials())->toBeNull()
        ->and(XConfig::dailyLimit())->toBe($config['daily_limit'])
        ->and(XConfig::dryRun())->toBe($config['dry_run'])
        ->and(XConfig::mcpEnabled())->toBe($config['mcp']['enabled'])
        ->and(XConfig::mcpHandle())->toBe($config['mcp']['handle'])
        ->and(XConfig::subscription()->value)->toBe($config['subscription'])
        ->and(XConfig::timeout())->toBe($config['timeout']);
});

it('follows a changed setting', function () {
    config([
        'api_x.api_url' => 'https://proxy.test/',
        'api_x.daily_limit' => -5,
        'api_x.mcp.handle' => 'social',
    ]);

    expect(XConfig::apiUrl())->toBe('https://proxy.test')
        ->and(XConfig::dailyLimit())->toBe(0)
        ->and(XConfig::mcpHandle())->toBe('social');
});

it('reports credentials only when all four keys are set', function () {
    $this->useXCredentials();

    expect(XConfig::credentials())->toBe([
        'access_token' => 'access-token',
        'access_token_secret' => 'access-secret',
        'consumer_key' => 'consumer-key',
        'consumer_secret' => 'consumer-secret',
    ]);

    config(['api_x.credentials.consumer_secret' => '']);

    expect(XConfig::credentials())->toBeNull();
});

it('is the only place in the package that reads the config', function () {
    $offenders = [];

    foreach (['src', 'resources', 'routes'] as $directory) {
        $path = packageRoot($directory);

        if (! is_dir($path)) {
            continue;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(packageRoot('').'/', '', $file->getPathname());

            if (str_contains($relative, 'XConfig.php')) {
                continue;
            }

            if (preg_match("/config\(['\"]api_x\./", (string) file_get_contents($file->getPathname()))) {
                $offenders[] = $relative;
            }
        }
    }

    expect($offenders)->toBe([], 'these read the config directly instead of through XConfig');
});

it('keeps the config keys in alphabetical order, in every group', function () {
    $lines = file(packageRoot('config/api_x.php'), FILE_IGNORE_NEW_LINES);

    $top = [];
    $groups = [];
    $current = null;

    foreach ($lines as $line) {
        if (preg_match("/^    '([a-z_0-9]+)' =>/", $line, $match)) {
            $top[] = $match[1];
            $current = $match[1];
            $groups[$current] = [];

            continue;
        }

        if ($current !== null && preg_match("/^        '([a-z_0-9]+)' =>/", $line, $match)) {
            $groups[$current][] = $match[1];
        }
    }

    $sortedTop = $top;
    sort($sortedTop);

    expect($top)->toBe($sortedTop, 'the groups are not in alphabetical order');

    foreach ($groups as $group => $keys) {
        $sorted = $keys;
        sort($sorted);

        expect($keys)->toBe($sorted, "the keys in '{$group}' are not in alphabetical order");
    }
});
