<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A published article waiting for, or already sent to, the Facebook page.
 *
 * The row is created the moment an article is published, so nothing is lost while a
 * card is still missing. Auto-versus-manual is not stored: it comes from the
 * category tick when the row is read (see App\Support\FacebookShare).
 */
class FacebookPost extends Model
{
    /** কার্ড তৈরি হয়নি — ফটোকার্ড কেন্দ্র থেকে বানাতে হবে। */
    public const NEEDS_CARD = 'needs_card';

    /** কার্ড তৈরি; টিক করা ক্যাটাগরি হলে আপনি চলবে, না হলে ছাড়পত্র বাকি। */
    public const QUEUED = 'queued';

    public const POSTED = 'posted';

    public const FAILED = 'failed';

    public const STATUS_LABELS = [
        self::NEEDS_CARD => 'কার্ড লাগবে',
        self::QUEUED => 'অপেক্ষমাণ',
        self::POSTED => 'পোস্ট হয়েছে',
        self::FAILED => 'ব্যর্থ',
    ];

    protected $fillable = [
        'news_id',
        'status',
        'message',
        'comment',
        'photo_id',
        'post_id',
        'error',
        'attempts',
        'approved_by',
        'posted_at',
    ];

    protected $attributes = [
        'status' => self::NEEDS_CARD,
        'error' => '',
        'attempts' => 0,
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** অ্যাডমিন টেবিলের রঙিন পিলের ক্লাস (public/css/admin.css এর st-*)। */
    public function getBadgeAttribute(): string
    {
        return match ($this->status) {
            self::POSTED => 'st-published',
            self::QUEUED => 'st-scheduled',
            self::FAILED => 'st-off',
            default => 'st-pending',
        };
    }

    /** ফেসবুকের পোস্টটি ব্রাউজারে খোলার ঠিকানা। */
    public function getPermalinkAttribute(): ?string
    {
        return $this->post_id ? 'https://www.facebook.com/'.$this->post_id : null;
    }
}
