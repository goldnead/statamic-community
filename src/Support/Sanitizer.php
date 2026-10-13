<?php

namespace Goldnead\Community\Support;

use Rhukster\DomSanitizer\DOMSanitizer;

/** Bodies are HTML, but only the configured tags and attributes survive. */
class Sanitizer
{
    public function clean(string $html): string
    {
        $sanitizer = new DOMSanitizer(DOMSanitizer::HTML);
        $sanitizer->setAllowedTags(['html', 'body', ...config('community.allowed_tags')]); // the library parses into a document and needs its root
        $sanitizer->setAllowedAttributes(config('community.allowed_attributes'));

        return trim($sanitizer->sanitize($html, ['compress-output' => false]));
    }
}
