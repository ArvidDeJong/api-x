<?php

declare(strict_types=1);

namespace Darvis\ApiX\Mcp;

use Darvis\ApiX\Mcp\Tools\ListMentions;
use Darvis\ApiX\Mcp\Tools\PostUpdate;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('X')]
#[Instructions('Posts short updates, optionally with one image, to the X account configured in this Laravel app, reads the posts that mention it and replies to them. Keep a post short (the post-update tool states the maximum length for this account), write it in the voice of the account owner and leave links out: posts with a link are refused unless the app allows them, because X bills them at a much higher rate. A reply goes out publicly under the owner\'s name: show it to the owner before you send it, unless they said otherwise. Every post list-mentions returns is billed as a read, so pass since_id.')]
class XServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        PostUpdate::class,
        ListMentions::class,
    ];
}
