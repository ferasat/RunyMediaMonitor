<?php

namespace App\Http\Controllers;

use App\Models\ContentFile;

class MediaDownloadController extends Controller
{
    public function __invoke(string $token, string $name)
    {
        $file = ContentFile::where('token', $token)->first();
        if (! $file || ! $file->isAvailable()) {
            abort(410, 'این فایل منقضی یا حذف شده است.');
        }
        $prefix = config('rmm.accel_prefix');
        if ($prefix) {
            return response('', 200, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="'.addslashes($file->name).'"',
                'X-Accel-Redirect' => rtrim($prefix, '/').'/'.$file->path,
            ]);
        }
        return response()->download($file->absolutePath(), $file->name);
    }
}
