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
        {--reply-to= : Id of, or link to, the post to reply to}
        {--dry-run : Check the post without sending it}';

    protected $description = 'Publish a post on X, optionally with an image';

    public function handle(XClient $client): int
    {
        // Read through the input directly: Larastan versions disagree on the type argument() returns.
        $text = $this->input->getArgument('text');
        $image = $this->option('image');
        $replyTo = $this->option('reply-to');

        try {
            $result = $client->post(
                is_string($text) ? $text : '',
                is_string($image) ? $image : null,
                (bool) $this->option('dry-run'),
                is_string($replyTo) ? $replyTo : null,
            );
        } catch (XException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info($result->dryRun
            ? 'Dry run: the post passed every check and was not sent.'
            : ($result->replyTo === null ? 'Posted: ' : 'Replied: ').$result->url());

        if ($result->remainingToday !== null) {
            $this->components->twoColumnDetail('Posts left today', (string) $result->remainingToday);
        }

        return self::SUCCESS;
    }
}
