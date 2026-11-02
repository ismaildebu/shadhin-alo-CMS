<?php

namespace App\Models;

use App\Support\ArticleVisuals;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Chart extends Model
{
    /** গ্রাফের ধরন: প্রতিটির জন্য আলাদা ডেটা ফরম্যাট ব্যবহার হয়। */
    public const TYPES = [
        'grouped' => 'গ্রুপড কলাম (একাধিক সিরিজ)',
        'column' => 'খাড়া কলাম (এক সিরিজ)',
        'bar' => 'আনুভূমিক বার (এক সিরিজ)',
        'line' => 'লাইন চার্ট',
        'pie' => 'পাই চার্ট',
        'donut' => 'ডোনাট চার্ট',
        '3d' => 'থ্রিডি অ্যারো (উত্থান)',
    ];

    /** গ্রাফের দৃশ্য-স্টাইল: একই তথ্য ভিন্ন ডিজাইনে দেখানো যায়। */
    public const STYLES = [
        'classic' => 'ক্লাসিক',
        'gradient' => 'গ্রেডিয়েন্ট',
        'outline' => 'আউটলাইন',
        'rounded' => 'রাউন্ডেড',
        'dark' => 'ডার্ক',
        'mono' => 'মনোক্রোম',
    ];

    protected $fillable = ['title', 'type', 'style', 'unit', 'source', 'data', 'is_published', 'serial'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_published' => 'boolean',
            'serial' => 'integer',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('serial')->orderBy('id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function styleLabel(): string
    {
        return self::STYLES[$this->style] ?? self::STYLES['classic'];
    }

    /** @return array<string, string> */
    public function visualAttributes(): array
    {
        $data = is_array($this->data) ? $this->data : [];
        $type = array_key_exists((string) $this->type, self::TYPES) ? (string) $this->type : 'column';
        $style = array_key_exists((string) $this->style, self::STYLES) ? (string) $this->style : 'classic';

        $attributes = [
            'type' => $type,
            'style' => $style,
            'title' => (string) $this->title,
            'unit' => (string) ($this->unit ?? ''),
            'source' => (string) ($this->source ?? ''),
        ];

        if ($type === 'grouped') {
            $attributes['labels'] = implode('|', array_map('strval', (array) ($data['labels'] ?? [])));
            $lines = [];

            foreach ((array) ($data['series'] ?? []) as $series) {
                $name = (string) ($series['name'] ?? '');
                $values = array_map(fn ($value) => self::number((float) $value), (array) ($series['values'] ?? []));

                if ($name !== '' && $values !== []) {
                    $lines[] = $name.'='.implode(',', $values);
                }
            }

            $attributes['series'] = implode('|', $lines);

            return $attributes;
        }

        $attributes['labels'] = implode('|', array_map('strval', (array) ($data['labels'] ?? [])));
        $attributes['values'] = implode('|', array_map(fn ($value) => self::number((float) $value), (array) ($data['values'] ?? [])));

        return $attributes;
    }

    /** সংবাদ বা পেজে বসানোর জন্য প্রস্তুত শর্টকোড। */
    public function shortcode(): string
    {
        $parts = [];

        foreach ($this->visualAttributes() as $key => $value) {
            if ($key !== 'type' && $value === '') {
                continue;
            }

            $parts[] = $key.'="'.$this->attribute($value).'"';
        }

        return '[chart '.implode(' ', $parts).']';
    }

    public function render(): string
    {
        return ArticleVisuals::expand(e($this->shortcode()));
    }

    private function attribute(string $value): string
    {
        return trim(preg_replace('/["\[\]\r\n]+/u', ' ', $value) ?? $value);
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
