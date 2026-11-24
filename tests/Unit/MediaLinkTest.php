<?php

namespace Tests\Unit;

use App\Support\MediaLink;
use PHPUnit\Framework\TestCase;

class MediaLinkTest extends TestCase
{
    public function test_parses_supported_links(): void
    {
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', MediaLink::parse('https://youtu.be/dQw4w9WgXcQ?si=x')->url);
        $this->assertTrue(MediaLink::parse('https://www.youtube.com/shorts/QM5vh0ZKvZo')->isShort);
        $this->assertSame('dQw4w9WgXcQ', MediaLink::parse('https://m.youtube.com/watch?v=dQw4w9WgXcQ&t=10')->id);
        $ig = MediaLink::parse('https://www.instagram.com/reel/Df3Rp0bKNdr/?igsh=abc');
        $this->assertSame(['instagram', 'reel', 'https://www.instagram.com/reel/Df3Rp0bKNdr/'], [$ig->platform, $ig->kind, $ig->url]);
        $this->assertSame('post', MediaLink::parse('https://instagram.com/nasa/p/DgFz7DylwcQ/')->kind);
    }

    public function test_rejects_other_hosts_and_profiles(): void
    {
        foreach (['https://evil.com/watch?v=dQw4w9WgXcQ', 'https://youtube.com.evil.com/watch?v=dQw4w9WgXcQ',
                  'https://www.instagram.com/nasa/', 'https://www.youtube.com/@TED', 'ftp://youtu.be/dQw4w9WgXcQ',
                  'javascript:alert(1)', 'https://notinstagram.com/p/ABCDEF/'] as $bad) {
            $this->assertNull(MediaLink::parse($bad), $bad);
        }
    }
}
