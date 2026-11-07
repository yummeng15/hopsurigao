<?php

namespace App\Services;

class UploadPath
{
    public static function package(int $packageId): string
    {
        return "packages/{$packageId}";
    }

    public static function profile(int $userId): string
    {
        return "profiles/{$userId}";
    }

    public static function pickupLocation(int $id): string
    {
        return "pickup-locations/{$id}";
    }

    public static function boat(int $id): string
    {
        return "boats/{$id}";
    }

    public static function payment(int $bookingId): string
    {
        return "payments/{$bookingId}";
    }
}