<?php

declare(strict_types=1);

namespace Darvis\ApiX\Console\Commands;

use Darvis\ApiX\Exceptions\XException;
use Darvis\ApiX\XClient;
use Illuminate\Console\Command;

class XPostCommand extends Command
{
    protected $signature = 'x:post
        {text : The post text}
        {--image= : Local path or http(s) URL of an image}
        {--dry-run : Check the post without sending it}';

    protected $description = 'Publish a post on X, optionally with an image';

    public function handle(XClient $client): int
    {
        $image = $this->option('image');

        try {
            $result = $client->post(
                (string) $this->argument('text'),
                is_string($image) ? $image : null,
                (bool) $this->option('dry-run'),
            );
        } catch (XException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info($result->dryRun
            ? 'Dry run: the post passed every check and was not sent.'
            : 'Posted: '.$result->url());

        if ($result->remainingToday !== null) {
            $this->components->twoColumnDetail('Posts left today', (string) $result->remainingToday);
        }

        return self::SUCCESS;
    }
}
