<?php

declare(strict_types=1);

namespace Darvis\ApiX\Mcp;

use Darvis\ApiX\Mcp\Tools\PostUpdate;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('X')]
#[Instructions('Posts short updates, optionally with one image, to the X account configured in this Laravel app. Keep a post under 280 characters, write it in the voice of the account owner and leave links out: posts with a link are refused unless the app allows them, because X bills them at a much higher rate.')]
class XServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        PostUpdate::class,
    ];
}
