<?php

namespace Tests\Unit;

use App\Support\BrandLogoSvgSanitizer;
use Tests\TestCase;

class BrandLogoSvgSanitizerTest extends TestCase
{
    public function test_clean_keeps_viewbox_path_and_gradient(): void
    {
        $dirty = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 40">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0" stop-color="#7c5a3b"/>
      <stop offset="1" stop-color="#2c1810"/>
    </linearGradient>
  </defs>
  <g>
    <path d="M8 8h104v24H8z" fill="url(#g)"/>
  </g>
</svg>
SVG;

        $clean = (new BrandLogoSvgSanitizer)->clean($dirty);

        $this->assertIsString($clean);
        $this->assertStringContainsString('viewBox="0 0 120 40"', $clean);
        $this->assertStringContainsString('<path', $clean);
        $this->assertStringContainsStringIgnoringCase('linearGradient', $clean);
        $this->assertStringContainsString('<g', $clean);
    }

    public function test_clean_strips_active_content(): void
    {
        $dirty = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert(1)">
  <script>alert(1)</script>
  <a href="javascript:alert(1)"><path d="M0 0h10v10H0z" fill="#7c5a3b"/></a>
  <foreignObject width="10" height="10"><body xmlns="http://www.w3.org/1999/xhtml">x</body></foreignObject>
</svg>
SVG;

        $clean = (new BrandLogoSvgSanitizer)->clean($dirty);

        $this->assertIsString($clean);
        $this->assertStringContainsString('<path', $clean);
        $this->assertStringNotContainsString('<script', strtolower($clean));
        $this->assertStringNotContainsString('onload=', strtolower($clean));
        $this->assertStringNotContainsString('javascript:', strtolower($clean));
        $this->assertStringNotContainsString('foreignobject', strtolower($clean));
    }

    public function test_clean_rejects_invalid_xml(): void
    {
        $this->assertNull((new BrandLogoSvgSanitizer)->clean('<svg><<<<'));
    }
}
