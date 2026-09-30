<?php

namespace Tests\Unit\Status;

use App\Services\Status\SvgSanitizer;
use PHPUnit\Framework\TestCase;

class SvgSanitizerTest extends TestCase
{
    private const OPEN = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10">';

    public function test_clean_svg_is_preserved(): void
    {
        $out = SvgSanitizer::sanitize(self::OPEN.'<rect width="10" height="10" fill="#fff"/></svg>');

        $this->assertNotNull($out);
        $this->assertStringContainsString('<rect', $out);
        $this->assertStringContainsString('viewBox', $out);
    }

    public function test_entities_and_doctype_are_rejected(): void
    {
        $xxe = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]>'
            .self::OPEN.'<text>&x;</text></svg>';

        $this->assertNull(SvgSanitizer::sanitize($xxe));
    }

    public function test_script_and_event_handlers_are_removed(): void
    {
        $out = SvgSanitizer::sanitize(self::OPEN.'<script>alert(1)</script><rect onload="alert(1)" width="1"/></svg>');

        $this->assertNotNull($out);
        $this->assertStringNotContainsString('script', $out);
        $this->assertStringNotContainsString('onload', $out);
    }

    public function test_javascript_links_are_removed_including_plain_and_obfuscated_hrefs(): void
    {
        $out = SvgSanitizer::sanitize(self::OPEN
            .'<use href="javascript:alert(1)"/>'
            .'<use xlink:href="java&#9;script:alert(1)"/>'
            .'<use href="data:text/html,x"/>'
            .'<use href="#ok"/></svg>');

        $this->assertNotNull($out);
        $this->assertStringNotContainsString('javascript', $out);
        $this->assertStringNotContainsString('java', $out);
        $this->assertStringNotContainsString('data:', $out);
        $this->assertStringContainsString('href="#ok"', $out);
    }

    public function test_foreign_object_iframe_and_animation_elements_are_dropped(): void
    {
        $out = SvgSanitizer::sanitize(self::OPEN
            .'<foreignObject><iframe src="https://evil.test"/></foreignObject>'
            .'<a href="https://evil.test"><rect width="1"/></a>'
            .'<set attributeName="href" to="javascript:alert(1)"/></svg>');

        $this->assertNotNull($out);
        $this->assertStringNotContainsString('foreignObject', $out);
        $this->assertStringNotContainsString('iframe', $out);
        $this->assertStringNotContainsString('<set', $out);
        $this->assertStringNotContainsString('evil.test', $out);
    }

    public function test_external_style_urls_are_removed(): void
    {
        $out = SvgSanitizer::sanitize(self::OPEN.'<rect style="fill:url(https://evil.test/x)" width="1"/></svg>');

        $this->assertNotNull($out);
        $this->assertStringNotContainsString('evil.test', $out);
    }

    public function test_non_svg_and_garbage_are_rejected(): void
    {
        $this->assertNull(SvgSanitizer::sanitize(''));
        $this->assertNull(SvgSanitizer::sanitize('<html><body/></html>'));
        $this->assertNull(SvgSanitizer::sanitize('<svg'));
    }
}
