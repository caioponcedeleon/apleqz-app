<?php

namespace App\Models;

use App\Services\StoredFileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationFile extends Model
{
    protected $fillable = [
        'application_id',
        'user_id',
        'original_name',
        'path',
        'mime_type',
        'size',
    ];

    protected static function booted(): void
    {
        static::deleting(function (ApplicationFile $file): void {
            app(StoredFileService::class)->delete($file->path);
        });
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
