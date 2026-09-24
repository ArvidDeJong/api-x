<?php

declare(strict_types=1);

use Darvis\ApiX\Support\PostText;

it('counts Latin text as one per character', function () {
    expect(PostText::weightedLength('darvis/api-x 1.0.0 is out'))->toBe(25)
        ->and(PostText::weightedLength('één café'))->toBe(8);
});

it('counts emoji and CJK as two', function () {
    expect(PostText::weightedLength('🚀'))->toBe(2)
        ->and(PostText::weightedLength('日本'))->toBe(4);
});

it('counts a link as 23 characters whatever its length', function () {
    expect(PostText::weightedLength('https://github.com/ArvidDeJong/api-x/releases/tag/v1.0.0'))->toBe(23);
});

it('recognises what X turns into a link', function (string $text) {
    expect(PostText::containsLink($text))->toBeTrue();
})->with([
    'https' => 'see https://arvid.nl',
    'http' => 'see http://example.test/page',
    'www' => 'see www.example.test',
    'bare domain' => 'more on arvid.nl',
    'domain with path' => 'on github.com/ArvidDeJong',
]);

it('leaves package names, versions and file names alone', function (string $text) {
    expect(PostText::containsLink($text))->toBeFalse();
})->with([
    'package' => 'darvis/api-x 1.2.0 is out',
    'file' => 'Updated CHANGELOG.md and config/api_x.php',
    'plain' => 'Working on the MCP server today',
]);
