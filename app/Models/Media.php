<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class Media extends Model
{
    /** সংবাদের ছবির ফোল্ডার: গ্যালারির ছবিও এখানেই থাকে, তাই পাবলিক সাইটের কোনো ভিউ বদলাতে হয় না। */
    public const DIR = 'uploads/news';

    protected $table = 'media';

    protected $fillable = ['filename', 'original_name', 'mime', 'size', 'uploaded_by'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return asset(self::DIR.'/'.$this->filename);
    }

    public function getSizeLabelAttribute(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1).' MB'
            : number_format($this->size / 1024).' KB';
    }

    /**
     * ফাইল সংরক্ষণ করে গ্যালারিতে যোগ করে। নাম এলোমেলো (UUID), ক্লায়েন্টের ফাইলনাম কখনো ব্যবহার হয় না।
     */
    public static function storeUpload(UploadedFile $file, ?User $user = null): self
    {
        // move() এর পরে ফাইলের তথ্য পাওয়া যায় না, তাই আগেই নিয়ে রাখছি।
        $name = Str::uuid().'.'.$file->extension();
        $original = Str::limit($file->getClientOriginalName(), 190, '');
        $mime = $file->getMimeType();
        $size = (int) $file->getSize();

        $file->move(public_path(self::DIR), $name);

        return static::create([
            'filename' => $name,
            'original_name' => $original,
            'mime' => $mime,
            'size' => $size,
            'uploaded_by' => $user?->id,
        ]);
    }
}