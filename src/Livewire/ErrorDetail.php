<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Livewire;

use Exception;
use Livewire\Attributes\Title;
use Livewire\Component;
use WorkDoneRight\ApiGuardian\Models\ApiError;
use WorkDoneRight\ApiGuardian\Services\ErrorService;

#[Title('Error Details')]
final class ErrorDetail extends Component
{
    public string $errorId;

    public ?ApiError $error = null;

    public $relatedErrors = [];

    public bool $showRaw = false;

    protected ErrorService $errorService;

    public function mount(string $id, ErrorService $errorService): void
    {
        $this->errorId = $id;
        $this->errorService = $errorService;
        $this->loadError();
    }

    /**
     * Load error details.
     */
    public function loadError(): void
    {
        try {
            $this->error = $this->errorService->findError($this->errorId);
            $this->relatedErrors = $this->errorService->getRelatedErrors($this->error);
        } catch (Exception) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Error not found',
            ]);
            $this->redirect(route('api-guardian.livewire.errors'));
        }
    }

    /**
     * Toggle raw data view.
     */
    public function toggleRaw(): void
    {
        $this->showRaw = ! $this->showRaw;
    }

    /**
     * Resolve this error.
     */
    public function resolve(): void
    {
        try {
            $this->errorService->resolveError($this->errorId);
            $this->loadError();
            $this->dispatch('error-resolved', id: $this->errorId);
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Error marked as resolved',
            ]);
        } catch (Exception $exception) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Delete this error.
     */
    public function delete(): void
    {
        try {
            $this->errorService->deleteError($this->errorId);
            $this->dispatch('error-deleted', id: $this->errorId);
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Error deleted successfully',
            ]);
            $this->redirect(route('api-guardian.livewire.errors'));
        } catch (Exception $exception) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Copy error ID to clipboard.
     */
    public function copyId(): void
    {
        $this->dispatch('copy-to-clipboard', text: $this->errorId);
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Error ID copied to clipboard',
        ]);
    }

    public function render()
    {
        return view('api-guardian::livewire.error-detail');
    }
}
