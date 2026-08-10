<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BucketFileController extends Controller
{
    public function show(string $path): StreamedResponse
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        $lastModified = $disk->lastModified($path);
        $etag = md5($path . $lastModified);

        if (request()->header('If-None-Match') === $etag) {
            return response()->stream(fn () => null, 304);
        }

        return new StreamedResponse(function () use ($disk, $path) {
            $stream = $disk->readStream($path);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type'   => $disk->mimeType($path),
            'Content-Length' => $disk->size($path),
            'Cache-Control'  => 'public, max-age=86400',
            'ETag'           => $etag,
            'Last-Modified'  => gmdate('D, d M Y H:i:s', $lastModified) . ' GMT',
        ]);
    }
}
