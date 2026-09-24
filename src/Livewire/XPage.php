<?php

declare(strict_types=1);

namespace Darvis\ApiX\Livewire;

use Darvis\ApiX\Exceptions\XException;
use Darvis\ApiX\Http\Middleware\AuthorizeXPage;
use Darvis\ApiX\Models\XPost;
use Darvis\ApiX\Support\PostText;
use Darvis\ApiX\Support\XConfig;
use Darvis\ApiX\XClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

/**
 * Post to X and see every post that went out, also the ones from x:post and the MCP tool.
 */
#[Title('X')]
class XPage extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $text = '';

    public string $imageUrl = '';

    /**
     * @var TemporaryUploadedFile|null
     */
    public $upload = null;

    public bool $dryRun = false;

    /**
     * @var array{ok: bool, message: string, url: string|null}|null
     */
    public ?array $result = null;

    /**
     * Runs on the page load and on every update request. It also covers the component when a
     * host application embeds it outside the package route, where the route middleware never runs.
     */
    public function boot(): void
    {
        AuthorizeXPage::authorize();
    }

    public function mount(): void
    {
        $this->dryRun = XConfig::dryRun();
    }

    public function post(XClient $client): void
    {
        AuthorizeXPage::authorize();

        $this->validate([
            'text' => ['required', 'string'],
            'imageUrl' => ['nullable', 'url:http,https'],
            'upload' => ['nullable', 'image', 'max:5120'],
        ]);

        $image = $this->upload instanceof TemporaryUploadedFile
            ? $this->upload->getRealPath()
            : (trim($this->imageUrl) !== '' ? trim($this->imageUrl) : null);

        try {
            $result = $client->post($this->text, $image ?: null, $this->dryRun);
        } catch (XException $exception) {
            $this->result = ['ok' => false, 'message' => $exception->getMessage(), 'url' => null];

            return;
        }

        if ($result->dryRun) {
            $this->result = ['ok' => true, 'message' => 'Dry run: the post passed every check and was not sent.', 'url' => null];

            return;
        }

        $this->result = ['ok' => true, 'message' => 'Posted.', 'url' => $result->url()];
        $this->reset('text', 'imageUrl', 'upload');
        $this->resetPage();
    }

    /**
     * The text length the way X counts it, for the counter under the text field.
     */
    #[Computed]
    public function length(): int
    {
        return PostText::weightedLength(trim($this->text));
    }

    /**
     * Posts left today, or null without a daily limit or with a broken cache.
     */
    #[Computed]
    public function remainingToday(): ?int
    {
        try {
            return app(XClient::class)->remainingToday();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return LengthAwarePaginator<int, XPost>|null
     */
    #[Computed]
    public function posts(): ?LengthAwarePaginator
    {
        try {
            return XPost::query()->latest('id')->paginate(XConfig::uiPerPage());
        } catch (Throwable) {
            // No x_posts table yet: the page still posts, the history asks for a migration.
            return null;
        }
    }

    public function render(): View
    {
        AuthorizeXPage::authorize();

        return view('api-x::livewire.x-page', [
            'maxLength' => PostText::MAX_LENGTH,
            'allowLinks' => XConfig::allowLinks(),
        ])->layout(XConfig::uiLayout());
    }
}
