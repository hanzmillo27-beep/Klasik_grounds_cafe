<?php

namespace Core;

class SupabaseStorage
{
    private const MAX_FILE_SIZE = 5242880;

    public function uploadProductImage(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('The image upload failed. Please try again.');
        }

        if (($file['size'] ?? 0) > self::MAX_FILE_SIZE) {
            throw new \RuntimeException('Product images must be 5 MB or smaller.');
        }

        $tmpPath = $file['tmp_name'] ?? '';
        $imageInfo = $tmpPath !== '' ? @getimagesize($tmpPath) : false;
        $mime = is_array($imageInfo) ? ($imageInfo['mime'] ?? '') : '';
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($extensions[$mime])) {
            throw new \RuntimeException('Choose a JPG, PNG, or WebP image.');
        }

        $url = SUPABASE_URL;
        $key = SUPABASE_SERVICE_ROLE_KEY;
        if ($url === '' || $key === '') {
            throw new \RuntimeException('Product image storage is not configured on the server.');
        }

        $path = 'products/' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $endpoint = $url . '/storage/v1/object/' . rawurlencode(SUPABASE_STORAGE_BUCKET) . '/' . str_replace('%2F', '/', rawurlencode($path));
        $contents = file_get_contents($tmpPath);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read the uploaded image.');
        }

        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Authorization: Bearer {$key}\r\n" .
                "apikey: {$key}\r\n" .
                "Content-Type: {$mime}\r\n" .
                "x-upsert: true\r\n",
            'content' => $contents,
            'timeout' => 30,
            'ignore_errors' => true,
        ]]);

        $response = @file_get_contents($endpoint, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        if ($response === false || !preg_match('/\s2\d\d\s/', $statusLine)) {
            throw new \RuntimeException('Supabase could not store the image. Check the storage bucket and server key.');
        }

        return $path;
    }

    public static function publicUrl(?string $path): ?string
    {
        if (!$path || SUPABASE_URL === '') {
            return null;
        }

        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
        return SUPABASE_URL . '/storage/v1/object/public/' . rawurlencode(SUPABASE_STORAGE_BUCKET) . '/' . $encodedPath;
    }
}
