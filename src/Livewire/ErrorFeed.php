<?php

declare(strict_types=1);

namespace WorkDoneRight\ApiGuardian\Livewire;

use Exception;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use WorkDoneRight\ApiGuardian\Services\ErrorService;

#[Title('Error Feed')]
final class ErrorFeed extends Component
{
    use WithPagination;

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $statusCode = null;

    public ?string $fromDate = null;

    public ?string $toDate = null;

    public int $perPage = 25;

    // Selected error IDs for bulk actions
    public array $selected = [];

    // Polling interval (10 seconds for error feed)
    public int $pollInterval = 10000;

    protected ErrorService $errorService;

    public function mount(ErrorService $errorService): void
    {
        $this->errorService = $errorService;
    }

    /**
     * Get filtered errors.
     */
    public function getErrorsProperty()
    {
        $filters = [
            'status' => $this->status !== 'all' ? $this->status : null,
            'status_code' => $this->statusCode,
            'search' => $this->search,
            'from_date' => $this->fromDate,
            'to_date' => $this->toDate,
        ];

        return $this->errorService->getErrors(
            array_filter($filters),
            $this->perPage
        );
    }

    /**
     * Clear all filters.
     */
    public function clearFilters(): void
    {
        $this->reset(['status', 'search', 'statusCode', 'fromDate', 'toDate']);
        $this->resetPage();
    }

    /**
     * Resolve a single error.
     */
    public function resolveError(string $id): void
    {
        try {
            $this->errorService->resolveError($id);
            $this->dispatch('error-resolved', id: $id);
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Error marked as resolved',
            ]);
        } catch (Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete a single error.
     */
    public function deleteError(string $id): void
    {
        try {
            $this->errorService->deleteError($id);
            $this->dispatch('error-deleted', id: $id);
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Error deleted successfully',
            ]);
        } catch (Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Bulk resolve selected errors.
     */
    public function bulkResolve(): void
    {
        if ($this->selected === []) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'No errors selected',
            ]);

            return;
        }

        try {
            $count = $this->errorService->bulkResolveErrors($this->selected);
            $this->selected = [];
            $this->dispatch('errors-bulk-resolved', count: $count);
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "{$count} errors marked as resolved",
            ]);
        } catch (Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Bulk delete selected errors.
     */
    public function bulkDelete(): void
    {
        if ($this->selected === []) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'No errors selected',
            ]);

            return;
        }

        try {
            $count = $this->errorService->bulkDeleteErrors($this->selected);
            $this->selected = [];
            $this->dispatch('errors-bulk-deleted', count: $count);
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "{$count} errors deleted successfully",
            ]);
        } catch (Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * When search or filters change, reset to first page.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedStatusCode(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('api-guardian::livewire.error-feed');
    }
}
