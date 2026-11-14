<?php
declare(strict_types=1);

namespace Webmail\Mail;

final class Uploads
{
    private const BLOCKED_EXT = ['exe', 'bat', 'cmd', 'com', 'scr', 'pif', 'msi', 'msp', 'vbs', 'vbe', 'js', 'jse', 'wsf', 'wsh', 'jar', 'ps1', 'lnk', 'reg', 'dll', 'cpl', 'hta', 'apk'];

    /**
     * Validate uploaded files from $_FILES['attachments'] and return base64 attachments.
     *
     * @param array<string,mixed> $files
     * @return array{attachments:list<array{filename:string,contentType:string,content:string,size:int}>,errors:list<string>,bytes:int}
     */
    public static function collect(array $files, int $maxFileBytes, int $maxTotalBytes, int $alreadyBytes = 0): array
    {
        $out = [];
        $errors = [];
        $total = $alreadyBytes;
        $f = $files['attachments'] ?? null;
        if (!is_array($f) || !isset($f['name'])) {
            return ['attachments' => [], 'errors' => [], 'bytes' => $total];
        }
        $names = (array) $f['name'];
        foreach ($names as $i => $orig) {
            $err = (int) (((array) $f['error'])[$i] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $label = self::cleanName((string) $orig);
            if ($err !== UPLOAD_ERR_OK) {
                $errors[] = match ($err) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "“{$label}” is larger than this server allows.",
                    UPLOAD_ERR_PARTIAL => "“{$label}” was only partly uploaded. Please try again.",
                    default => "“{$label}” could not be uploaded.",
                };
                continue;
            }
            $tmp = (string) (((array) $f['tmp_name'])[$i] ?? '');
            if (!is_uploaded_file($tmp)) {
                $errors[] = "“{$label}” could not be uploaded.";
                continue;
            }
            $size = (int) filesize($tmp);
            $ext = strtolower(pathinfo($label, PATHINFO_EXTENSION));
            if (in_array($ext, self::BLOCKED_EXT, true)) {
                $errors[] = "“{$label}” is a type of file that cannot be attached (.$ext).";
                continue;
            }
            if ($size > $maxFileBytes) {
                $errors[] = "“{$label}” is larger than " . self::mb($maxFileBytes) . '.';
                continue;
            }
            if ($total + $size > $maxTotalBytes) {
                $errors[] = 'Attachments together may not exceed ' . self::mb($maxTotalBytes) . '.';
                continue;
            }
            $total += $size;
            $type = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream';
            $out[] = ['filename' => $label, 'contentType' => $type, 'content' => base64_encode((string) file_get_contents($tmp)), 'size' => $size];
        }
        return ['attachments' => $out, 'errors' => $errors, 'bytes' => $total];
    }

    public static function cleanName(string $n): string
    {
        $n = basename(str_replace('\\', '/', $n));
        $n = trim(preg_replace('/[\x00-\x1f\x7f"<>|?*:]+/u', '', $n) ?? '', ". \t");
        return $n === '' ? 'attachment' : mb_substr($n, 0, 180);
    }

    private static function mb(int $b): string
    {
        return round($b / 1048576) . ' MB';
    }
}
