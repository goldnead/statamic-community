<?php

namespace Goldnead\Community\Tests\Feature;

use Goldnead\Community\Exceptions\CommunityException;
use Goldnead\Community\Services\PostService;
use Goldnead\Community\Support\Sanitizer;
use Goldnead\Community\Tests\TestCase;

class HardeningTest extends TestCase
{
    private function confirmed()
    {
        $user = $this->makeUser('singer@example.test');
        $user->set('email_verified_at', now()->toIso8601String())->save();

        return $user;
    }

    public function test_links_keep_only_safe_schemes(): void
    {
        $clean = app(Sanitizer::class);

        foreach (['vbscript:x', 'data:text/html;base64,AAA', 'file:///etc/passwd', 'ftp://example.test', 'javascript:alert(1)'] as $href) {
            $this->assertStringNotContainsString('href', $clean->clean('<a href="'.$href.'">x</a>'), $href);
        }

        foreach (['https://example.test/a', 'http://example.test', 'mailto:a@example.test', 'tel:+4912345', '/intern', '#anker'] as $href) {
            $this->assertStringContainsString('href="'.$href.'"', $clean->clean('<a href="'.$href.'">x</a>'), $href);
        }
    }

    public function test_title_and_region_are_cut_to_the_column_width(): void
    {
        $post = app(PostService::class)->create($this->confirmed(), '<p>Hi</p>', region: str_repeat('ä', 400), title: str_repeat('ö', 400));

        $this->assertSame(255, mb_strlen($post->title));
        $this->assertSame(255, mb_strlen($post->region));
    }

    public function test_oversized_body_is_refused_not_truncated(): void
    {
        $this->expectException(CommunityException::class);

        app(PostService::class)->create($this->confirmed(), '<p>'.str_repeat('a', 70000).'</p>');
    }
}
