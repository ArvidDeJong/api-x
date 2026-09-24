<?php

declare(strict_types=1);

namespace Darvis\ApiX\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One post that went to X, from the page, the command, the MCP tool or code. Dry runs and
 * posts the package refused before calling X are not stored.
 *
 * @property int $id
 * @property string $text
 * @property string|null $image
 * @property string $status
 * @property string|null $x_id
 * @property string|null $media_id
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class XPost extends Model
{
    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $table = 'x_posts';

    protected $guarded = ['id'];

    /**
     * Link to the post on X, or null when it was not sent.
     */
    public function url(): ?string
    {
        return $this->x_id === null ? null : 'https://x.com/i/web/status/'.$this->x_id;
    }
}
