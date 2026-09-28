<?php

declare(strict_types=1);

namespace Darvis\ApiX\Console\Commands;

use Darvis\ApiX\Exceptions\XException;
use Darvis\ApiX\XClient;
use Illuminate\Console\Command;

class XMentionsCommand extends Command
{
    protected $signature = 'x:mentions
        {--since= : Only posts newer than this post id}
        {--limit=10 : How many posts to read, 5 to 100}';

    protected $description = 'Read the newest posts on X that mention the account (billed as reads)';

    public function handle(XClient $client): int
    {
        $since = $this->option('since');

        try {
            $mentions = $client->mentions(
                is_string($since) ? $since : null,
                (int) $this->option('limit'),
            );
        } catch (XException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($mentions === []) {
            $this->components->info('No mentions.');

            return self::SUCCESS;
        }

        foreach ($mentions as $mention) {
            $this->components->twoColumnDetail(
                '<fg=gray>'.$mention->id.'</> '.($mention->authorUsername === null ? $mention->authorId : '@'.$mention->authorUsername),
                (string) $mention->createdAt,
            );
            $this->line('  '.$mention->text);
            $this->line('  <fg=gray>'.$mention->url().'</>');
            $this->newLine();
        }

        $this->components->twoColumnDetail('Newest id, for --since next time', $mentions[0]->id);

        return self::SUCCESS;
    }
}
