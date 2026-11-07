<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\ChatAttachment;
use Illuminate\Support\Facades\Storage;

/** Attachments are never public URLs: each download checks that you may see the conversation. */
class AttachmentController extends Controller
{
    public function show(string $uuid)
    {
        $attachment = ChatAttachment::where('uuid', $uuid)->with('message.thread')->firstOrFail();

        // Same answer for "missing" and "not yours".
        abort_unless(auth()->user()->can('view', $attachment->message->thread), 404);

        $disk = Storage::disk(config('commerce.chat_disk', 'local'));
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ], $attachment->isImage() ? 'inline' : 'attachment');
    }
}
