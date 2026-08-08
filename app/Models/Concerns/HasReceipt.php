<?php

namespace App\Models\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    /**
     * Files a second copy of another record's receipt. One invoice can cover
     * several entries, and storing the same UploadedFile twice would hand them
     * a shared path - deleting one entry would then take the other's proof
     * with it.
     *
     * @param  self  $source  another model using this trait
     */
    public function copyReceiptFrom($source): void
    {
        if (! $source->hasReceipt()) {
            return;
        }

        $this->deleteReceipt();

        $extension = pathinfo($source->receipt_path, PATHINFO_EXTENSION);
        $path = 'receipts/'.Str::random(40).($extension ? '.'.$extension : '');

        Storage::disk('local')->copy($source->receipt_path, $path);

        $this->receipt_path = $path;
        $this->receipt_name = $source->receipt_name;
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
