<?php

namespace App\Services\Commerce;

/**
 * Computes 64-bit gradient difference hash (dHash) for visual proof deduplication.
 * Invariant to minor crops, quality re-compression, and format conversions.
 */
class DHashService
{
    /**
     * Compute 64-bit dHash hex string (16 characters) from raw image bytes.
     */
    public static function compute(string $rawImageBytes): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring($rawImageBytes);
        if ($source === false) {
            return null;
        }

        $width = 9;
        $height = 8;
        $resized = imagecreatetruecolor($width, $height);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        imagedestroy($source);

        $differenceBits = '';
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width - 1; $x++) {
                $rgbLeft = imagecolorat($resized, $x, $y);
                $grayLeft = ($rgbLeft >> 16 & 0xFF) * 0.299 + ($rgbLeft >> 8 & 0xFF) * 0.587 + ($rgbLeft & 0xFF) * 0.114;

                $rgbRight = imagecolorat($resized, $x + 1, $y);
                $grayRight = ($rgbRight >> 16 & 0xFF) * 0.299 + ($rgbRight >> 8 & 0xFF) * 0.587 + ($rgbRight & 0xFF) * 0.114;

                $differenceBits .= ($grayLeft > $grayRight) ? '1' : '0';
            }
        }
        imagedestroy($resized);

        // Convert 64 bits to 16-char hex
        $hex = '';
        for ($i = 0; $i < 64; $i += 4) {
            $nibble = substr($differenceBits, $i, 4);
            $hex .= dechex(bindec($nibble));
        }

        return str_pad($hex, 16, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate Hamming distance between two 64-bit dHash strings.
     * Distance <= 5 indicates virtually identical or slightly cropped images.
     */
    public static function distance(string $hash1, string $hash2): int
    {
        if (strlen($hash1) !== 16 || strlen($hash2) !== 16) {
            return 64;
        }

        $bin1 = '';
        $bin2 = '';
        for ($i = 0; $i < 16; $i++) {
            $bin1 .= str_pad(decbin(hexdec($hash1[$i])), 4, '0', STR_PAD_LEFT);
            $bin2 .= str_pad(decbin(hexdec($hash2[$i])), 4, '0', STR_PAD_LEFT);
        }

        $distance = 0;
        for ($i = 0; $i < 64; $i++) {
            if ($bin1[$i] !== $bin2[$i]) {
                $distance++;
            }
        }

        return $distance;
    }

    /**
     * Find existing verified or approved payment proof with similar dHash.
     */
    public function findDuplicate(string $dhash, ?int $excludeProofId = null, int $maxDistance = 5): ?\App\Models\Commerce\PaymentProof
    {
        $existing = \App\Models\Commerce\PaymentProof::query()
            ->whereNotNull('dhash')
            ->when($excludeProofId, fn ($q) => $q->where('id', '!=', $excludeProofId))
            ->whereIn('status', [
                \App\Models\Commerce\PaymentProof::STATUS_APPROVED,
                \App\Models\Commerce\PaymentProof::STATUS_AUTO_VERIFIED,
            ])
            ->get();

        foreach ($existing as $proof) {
            if ($proof->dhash && self::distance($dhash, $proof->dhash) <= $maxDistance) {
                return $proof;
            }
        }

        return null;
    }
}

