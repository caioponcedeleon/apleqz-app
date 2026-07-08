<?php

namespace App\Models;

use App\Models\Concerns\HasStoredFileLabel;
use App\Services\StoredFileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFile extends Model
{
    use HasStoredFileLabel;

    protected $fillable = [
        'user_id',
        'original_name',
        'display_name',
        'path',
        'mime_type',
        'size',
    ];

    protected static function booted(): void
    {
        static::deleting(function (UserFile $file): void {
            app(StoredFileService::class)->delete($file->path);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
