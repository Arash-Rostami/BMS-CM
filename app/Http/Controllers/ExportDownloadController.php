<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class ExportDownloadController extends Controller
{
    public function download(int $user, string $file)
    {
        abort_unless(auth()->id() === $user && preg_match('/^[\w.-]+$/', $file), 404, '⁴⁰⁴ File Not Found ⁴⁰⁴');

        $path = "exports/{$user}/{$file}";

        if (! Storage::disk('local')->exists($path)) {
            abort(404, '⁴⁰⁴ File Not Found ⁴⁰⁴');
        }

        return Storage::disk('local')->download($path);
    }
}
