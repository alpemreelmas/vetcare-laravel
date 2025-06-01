<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MedicalDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'medical_record_id',
        'pet_id',
        'uploaded_by',
        'title',
        'description',
        'type',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'file_hash',
        'document_date',
        'tags',
        'is_sensitive',
        'is_archived',
        'visibility',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'tags' => 'array',
            'is_sensitive' => 'boolean',
            'is_archived' => 'boolean',
            'file_size' => 'integer',
        ];
    }

    /**
     * Get the medical record this document belongs to.
     */
    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    /**
     * Get the pet this document belongs to.
     */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * Get the user who uploaded this document.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope to filter by pet.
     */
    public function scopeForPet($query, int $petId)
    {
        return $query->where('pet_id', $petId);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter by visibility.
     */
    public function scopeByVisibility($query, string $visibility)
    {
        return $query->where('visibility', $visibility);
    }

    /**
     * Scope to get non-archived documents.
     */
    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    /**
     * Scope to get archived documents.
     */
    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }

    /**
     * Scope to get sensitive documents.
     */
    public function scopeSensitive($query)
    {
        return $query->where('is_sensitive', true);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('document_date', [$startDate, $endDate]);
    }

    /**
     * Scope to search by tags.
     */
    public function scopeWithTag($query, string $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }

    /**
     * Scope to search in title and description.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', '%' . $search . '%')
              ->orWhere('description', 'like', '%' . $search . '%');
        });
    }

    /**
     * Get the file URL for download.
     */
    public function getFileUrl(): string
    {
        return Storage::url($this->file_path);
    }

    /**
     * Get the file size in human readable format.
     */
    public function getHumanReadableSize(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if the file exists in storage.
     */
    public function fileExists(): bool
    {
        return Storage::exists($this->file_path);
    }

    /**
     * Delete the file from storage.
     */
    public function deleteFile(): bool
    {
        if ($this->fileExists()) {
            return Storage::delete($this->file_path);
        }
        return true;
    }

    /**
     * Archive this document.
     */
    public function archive(): bool
    {
        return $this->update(['is_archived' => true]);
    }

    /**
     * Unarchive this document.
     */
    public function unarchive(): bool
    {
        return $this->update(['is_archived' => false]);
    }

    /**
     * Check if user can view this document.
     */
    public function canBeViewedBy(User $user): bool
    {
        // Admin can view all documents
        if ($user->hasRole('admin')) {
            return true;
        }

        // Doctor can view doctor_only and owner_and_doctor documents
        if ($user->hasRole('doctor')) {
            return in_array($this->visibility, ['doctor_only', 'owner_and_doctor', 'public']);
        }

        // Pet owner can view owner_and_doctor and public documents for their pets
        if ($this->pet->owner_id === $user->id) {
            return in_array($this->visibility, ['owner_and_doctor', 'public']);
        }

        // Public documents can be viewed by anyone
        return $this->visibility === 'public';
    }

    /**
     * Check if this is an image file.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->file_type, 'image/');
    }

    /**
     * Check if this is a PDF file.
     */
    public function isPdf(): bool
    {
        return $this->file_type === 'application/pdf';
    }

    /**
     * Get the file extension.
     */
    public function getFileExtension(): string
    {
        return pathinfo($this->file_name, PATHINFO_EXTENSION);
    }

    /**
     * Boot method to handle model events.
     */
    protected static function boot()
    {
        parent::boot();

        // Delete file when model is deleted
        static::deleting(function ($document) {
            $document->deleteFile();
        });
    }
}
