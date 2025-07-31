<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function download(Attachment $attachment)
    {
        if (!Storage::disk('private')->exists($attachment->path)) {
            abort(404, '⁴⁰⁴ File Not Found ⁴⁰⁴');
        }

        return Storage::disk('private')->download($attachment->path, $attachment->name);
    }
}
