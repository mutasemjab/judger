<?php

namespace App\Models;

use App\Enums\KnowledgeDocumentStatus;
use Closure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class KnowledgeDocument extends Model
{
    use HasFactory, SoftDeletes;

    public const MAX_UPLOAD_SIZE_KB = 51200;

    public const SUPPORTED_EXTENSIONS = ['pdf', 'doc', 'docx', 'pptx', 'txt'];

    protected $fillable = [
        'title', 'original_name', 'file_name', 'file_path', 'disk',
        'mime_type', 'file_size', 'category', 'status', 'uploaded_by',
        'qdrant_collection', 'qdrant_points_count', 'processing_error', 'processed_at',
    ];

    protected $hidden = ['file_path'];

    protected $casts = [
        'processed_at' => 'datetime',
        'status' => KnowledgeDocumentStatus::class,
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public static function uploadRules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'sometimes',
            'file',
            'max:' . self::MAX_UPLOAD_SIZE_KB,
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! $value instanceof UploadedFile) {
                    return;
                }

                $extension = strtolower($value->getClientOriginalExtension());

                if ($extension === 'ppt') {
                    $fail('Legacy PowerPoint .ppt files are not supported. Please save the file as .pptx and upload it again.');

                    return;
                }

                if (! in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
                    $fail('The ' . $attribute . ' must be a file of type: ' . implode(', ', self::SUPPORTED_EXTENSIONS) . '.');
                }
            },
        ];
    }

    public static function normalizeTitle(?string $title, ?string $originalName = null): string
    {
        $candidate = trim((string) Str::of((string) $title)->squish());

        if ($candidate !== '') {
            return $candidate;
        }

        $fallback = trim((string) Str::of(pathinfo((string) $originalName, PATHINFO_FILENAME))
            ->replace(['_', '-', '.'], ' ')
            ->squish()
            ->title());

        return $fallback !== '' ? $fallback : 'Untitled Document';
    }
}
