<?php

namespace Goldnead\Community\Support;

use Rhukster\DomSanitizer\DOMSanitizer;

/** Bodies are HTML, but only the configured tags and attributes survive. */
class Sanitizer
{
    /** Bytes a `text` column holds on MySQL; longer bodies would fail the insert there but pass on SQLite. */
    public const MAX_BODY = 60000;

    public function clean(string $html): string
    {
        $sanitizer = new DOMSanitizer(DOMSanitizer::HTML);
        $sanitizer->setAllowedTags(['html', 'body', ...config('community.allowed_tags')]); // the library parses into a document and needs its root
        $sanitizer->setAllowedAttributes(config('community.allowed_attributes'));

        $clean = trim($sanitizer->sanitize(mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8'), ['compress-output' => false]));

        return $this->stripUnsafeLinks($clean);
    }

    /** The library drops `javascript:` but lets `vbscript:` and the like through, so links are allow-listed by scheme. */
    protected function stripUnsafeLinks(string $html): string
    {
        return preg_replace_callback('/\s+href="([^"]*)"/i', function (array $match) {
            $href = preg_replace('/[\x00-\x20]+/', '', html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if (! preg_match('/^([a-z][a-z0-9+.\-]*):/i', $href, $scheme)) {
                return $match[0]; // relative link or anchor
            }

            return in_array(strtolower($scheme[1]), ['http', 'https', 'mailto', 'tel'], true) ? $match[0] : '';
        }, $html) ?? '';
    }
}
