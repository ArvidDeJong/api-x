<?php

/**
 * Guards the rules of the GitHub Pages site in docs/: front matter, Liquid use,
 * single-source facts and the developer credit. Nothing here breaks loudly otherwise.
 */
function docsSitePath(string $path = ''): string
{
    return dirname(__DIR__).'/docs'.($path === '' ? '' : '/'.$path);
}

/**
 * @return array<string, string>
 */
function docsFrontMatter(string $file): array
{
    preg_match('/\A---\n(.*?)\n---\n/s', (string) file_get_contents($file), $match);

    $values = [];
    foreach (explode("\n", $match[1] ?? '') as $line) {
        if (preg_match('/^(\w+):\s*(.*)$/', $line, $pair)) {
            $value = trim($pair[2]);

            // An unquoted ": " makes the YAML invalid, and Jekyll then silently ignores all front matter.
            expect(str_contains($value, ': ') && ! str_starts_with($value, '"'))
                ->toBeFalse(basename($file).': quote the value of '.$pair[1]);

            $values[$pair[1]] = trim($value, '"');
        }
    }

    return $values;
}

/**
 * Site pages. Every page sits directly in docs/; there is no docs/README.md, the site is the index.
 *
 * @return array<int, string>
 */
function docsSitePages(): array
{
    return array_values((array) glob(docsSitePath('*.md')));
}

test('every page has a title, a unique description and a unique nav order', function () {
    $pages = docsSitePages();
    $descriptions = [];
    $navOrders = [];

    foreach ($pages as $page) {
        $meta = docsFrontMatter($page);

        expect($meta)->toHaveKeys(['title', 'description', 'nav_order'], basename($page));
        expect($meta)->not->toHaveKey('parent', basename($page));

        // Long enough to say what the page answers, short enough for a search result.
        expect(strlen($meta['description']))->toBeGreaterThanOrEqual(110, basename($page))
            ->toBeLessThanOrEqual(160, basename($page));

        $descriptions[] = $meta['description'];
        $navOrders[] = $meta['nav_order'];
    }

    expect(array_unique($descriptions))->toHaveCount(count($pages));
    expect(array_unique($navOrders))->toHaveCount(count($pages));
});

test('the pages a beginner needs exist and the index links every page', function () {
    $index = (string) file_get_contents(docsSitePath('index.md'));

    foreach (['installation', 'quickstart', 'testing', 'troubleshooting', 'faq'] as $required) {
        expect(is_file(docsSitePath($required.'.md')))->toBeTrue($required.'.md is missing');
    }

    foreach (docsSitePages() as $page) {
        if (basename($page) === 'index.md') {
            continue;
        }

        expect($index)->toContain('('.basename($page).')');
    }

    // docs/README.md duplicated the site; GitHub would also show it instead of index.md.
    expect(is_file(docsSitePath('README.md')))->toBeFalse();
});

test('relative links between pages point at pages that exist', function () {
    foreach (docsSitePages() as $page) {
        preg_match_all('/\]\((?!https?:|mailto:|#)([^)#\s]+)(?:#[^)]*)?\)/', (string) file_get_contents($page), $links);

        foreach ($links[1] as $link) {
            expect(is_file(dirname($page).'/'.$link))->toBeTrue(basename($page).' links to '.$link);
        }
    }
});

test('pages use Liquid only where it is intended', function () {
    foreach (docsSitePages() as $page) {
        if (basename($page) === 'faq.md') {
            continue;
        }

        expect(file_get_contents($page))->not->toMatch('/\{\{|\{%/', basename($page));
    }
});

test('the FAQ, structured data and llms.txt read from the shared data', function () {
    $faq = (string) file_get_contents(docsSitePath('_data/faq.yml'));

    expect(substr_count($faq, "\n- q: ") + (str_starts_with(ltrim($faq), '- q: ') ? 1 : 0))->toBeGreaterThanOrEqual(6);
    expect(substr_count($faq, '- q: '))->toBe(substr_count($faq, '  a: '));

    expect(file_get_contents(docsSitePath('faq.md')))->toContain('site.data.faq');
    expect(file_get_contents(docsSitePath('llms.txt')))
        ->toContain('permalink: /llms.txt')
        ->toContain('site.data.faq')
        ->toContain('site.pages');
    expect(file_get_contents(docsSitePath('_includes/head_custom.html')))
        ->toContain('"FAQPage"')
        ->toContain('"SoftwareSourceCode"')
        ->toContain('site.data.faq');
});

test('the YAML files build, because one bad value fails the whole Pages build', function () {
    // An unquoted ": " makes the YAML invalid. Jekyll then aborts the build and GitHub keeps
    // serving the last version that did build, so the site looks fine while it is months old.
    foreach (['_config.yml', '_data/faq.yml'] as $file) {
        $path = docsSitePath($file);

        if (! is_file($path)) {
            continue;
        }

        $blockIndent = null;

        foreach (file($path, FILE_IGNORE_NEW_LINES) as $number => $line) {
            $indent = strlen($line) - strlen(ltrim($line));

            if ($blockIndent !== null) {
                // Inside a > or | block every line is text, whatever it contains.
                if (trim($line) === '' || $indent > $blockIndent) {
                    continue;
                }

                $blockIndent = null;
            }

            if (! preg_match('/^(\s*)(?:-\s+)?(\w+):(?:\s+(\S.*))?$/', $line, $pair)) {
                continue;
            }

            $value = trim($pair[3] ?? '');

            if ($value === '') {
                continue;
            }

            if (str_starts_with($value, '>') || str_starts_with($value, '|')) {
                $blockIndent = strlen($pair[1]);

                continue;
            }

            $quoted = str_starts_with($value, '"')
                || str_starts_with($value, "'")
                || str_starts_with($value, '[');

            expect(str_contains($value, ': ') && ! $quoted)
                ->toBeFalse($file.' line '.($number + 1).': quote the value of '.$pair[2]);
        }
    }
});

test('the config holds the package facts and the sitemap plugin', function () {
    expect(file_get_contents(docsSitePath('_config.yml')))
        ->toContain('- jekyll-sitemap')
        ->toContain('name: darvis/api-x')
        ->toContain('company: ARVID.NL')
        ->toContain('url: https://arvid.nl')
        ->not->toContain('footer_content');
});

test('the site, the README and the FAQ say the package is unofficial', function () {
    expect(file_get_contents(docsSitePath('index.md')))
        ->toContain('unofficial')
        ->toContain('not an official X product');
    expect(file_get_contents(docsSitePath('llms.txt')))->toContain('not affiliated with X Corp');
    expect(file_get_contents(docsSitePath('_data/faq.yml')))->toContain('Is darvis/api-x an official X package?');
    expect(file_get_contents(dirname(__DIR__).'/README.md'))
        ->toContain('Unofficial')
        ->toContain('not an official X product');
});

test('the troubleshooting page quotes every message the package throws', function () {
    $page = (string) file_get_contents(docsSitePath('troubleshooting.md'));

    foreach (['The post text is empty.', 'With an X subscription, set X_SUBSCRIPTION to allow up to 25,000.', 'X_ALLOW_LINKS=true to allow them.', 'Raise X_DAILY_LIMIT or try again tomorrow.', 'X credentials are missing.', 'The daily limit needs a working cache', 'The image could not be read', 'is not supported; use JPEG, PNG, GIF or WebP.', 'X accepts at most 5 MB.', 'X refused to create the post', 'X refused to upload the image', 'X refused to check the keys'] as $message) {
        expect($page)->toContain($message);
    }
});

test('the footer credits ARVID.NL without a personal name', function () {
    $footer = (string) file_get_contents(docsSitePath('_includes/footer_custom.html'));

    expect($footer)->toContain('site.developer.company')
        ->toContain('site.developer.url')
        ->not->toContain('Arvid de Jong')
        ->not->toMatch('/developed by|made by/i');

    expect(file_get_contents(docsSitePath('_includes/head_custom.html')))->not->toContain('"Person"');
});
