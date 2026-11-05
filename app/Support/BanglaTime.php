<?php

namespace App\Support;

use DateTimeInterface;

/** "৩১ মিনিট আগে" style relative time with Bengali digits. */
final class BanglaTime
{
    private const DIGITS = ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯'];

    private const MONTHS = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];

    private const WEEKDAYS = ['রবিবার', 'সোমবার', 'মঙ্গলবার', 'বুধবার', 'বৃহস্পতিবার', 'শুক্রবার', 'শনিবার'];

    /** "২৬ অক্টোবর ২০২৬" */
    public static function date(DateTimeInterface $date): string
    {
        return self::digits((int) $date->format('j')).' '.self::MONTHS[(int) $date->format('n') - 1].' '.self::digits($date->format('Y'));
    }

    /** "বুধবার" */
    public static function weekday(DateTimeInterface $date): string
    {
        return self::WEEKDAYS[(int) $date->format('w')];
    }

    /** "৩:৪৫ অপরাহ্ণ" */
    public static function time(DateTimeInterface $date): string
    {
        return self::digits($date->format('g:i')).' '.((int) $date->format('G') < 12 ? 'পূর্বাহ্ণ' : 'অপরাহ্ণ');
    }

    /** "৫ নভেম্বর ২০২৬, ৩:৪৫ অপরাহ্ণ" */
    public static function dateTime(DateTimeInterface $date): string
    {
        return self::date($date).', '.self::time($date);
    }

    public static function digits(int|string $number): string
    {
        return strtr((string) $number, self::DIGITS);
    }

    /**
     * @param  int|null  $now  unix timestamp to measure against (defaults to the current time; handy in tests)
     */
    public static function ago(DateTimeInterface $date, ?int $now = null): string
    {
        $now ??= now()->getTimestamp();
        $seconds = max(0, $now - $date->getTimestamp());

        if ($seconds < 60) {
            return 'এইমাত্র';
        }

        $minutes = intdiv($seconds, 60);

        if ($minutes < 60) {
            return self::digits($minutes).' মিনিট আগে';
        }

        $hours = intdiv($minutes, 60);

        if ($hours < 24) {
            return self::digits($hours).' ঘণ্টা আগে';
        }

        $days = intdiv($hours, 24);

        if ($days < 30) {
            return self::digits($days).' দিন আগে';
        }

        if ($days < 365) {
            return self::digits(intdiv($days, 30)).' মাস আগে';
        }

        return self::digits(intdiv($days, 365)).' বছর আগে';
    }
}
