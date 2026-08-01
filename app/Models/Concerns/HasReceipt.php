<?php

namespace App\Models\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores an uploaded invoice on the private disk. Files are never served
 * directly by the web server - they only go out through a policy-checked
 * download route.
 */
trait HasReceipt
{
    public static function bootHasReceipt(): void
    {
        static::deleting(function ($model) {
            $model->deleteReceipt();
        });
    }

    public function storeReceipt(UploadedFile $file): void
    {
        $this->deleteReceipt();

        $this->receipt_path = $file->store('receipts', 'local');
        // Keep the original name for display only - never for building paths.
        $this->receipt_name = mb_substr($file->getClientOriginalName(), 0, 255);
    }

    public function deleteReceipt(): void
    {
        if ($this->receipt_path) {
            Storage::disk('local')->delete($this->receipt_path);
            $this->receipt_path = null;
            $this->receipt_name = null;
        }
    }

    public function hasReceipt(): bool
    {
        return (bool) $this->receipt_path;
    }
}
