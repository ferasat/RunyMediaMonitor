<?php

namespace App\Console\Commands;

use App\Models\ContentFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RmmCleanup extends Command
{
    protected $signature = 'rmm:cleanup';
    protected $description = 'Delete downloaded media older than the retention period';

    public function handle(): int
    {
        $n = 0;
        ContentFile::whereNull('deleted_from_disk_at')->where('expires_at', '<', now())->with('content')
            ->chunkById(200, function ($files) use (&$n) {
                foreach ($files as $f) {
                    $abs = $f->absolutePath();
                    if (is_file($abs)) @unlink($abs);
                    $dir = dirname($abs);
                    if (is_dir($dir) && count(File::files($dir)) === 0) @rmdir($dir);
                    $f->update(['deleted_from_disk_at' => now()]);
                    if ($f->content && $f->content->files()->whereNull('deleted_from_disk_at')->doesntExist()) {
                        $f->content->update(['status' => $f->content->status === 'sent' ? 'expired' : $f->content->status]);
                    }
                    $n++;
                }
            });
        $this->info("deleted {$n} expired files");
        return self::SUCCESS;
    }
}
