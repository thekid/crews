<?php namespace de\thekid\crews;

use DOMDocument;

/**
 * Transforms HTML markup to a subset we can render in the pages
 *
 * @test  de.thekid.crews.unittest.MarkupTest
 * @see   https://research.securitum.com/dompurify-bypass-using-mxss/
 * @see   https://research.securitum.com/mutation-xss-via-mathml-mutation-dompurify-2-0-17-bypass/
 */
class Markup {
  const REMOVE= false;
  const EXPAND= true;

  private $handle= [
    'h1'       => [],
    'h2'       => [],
    'h3'       => [],
    'p'        => [],
    'b'        => [],
    'i'        => [],
    'u'        => [],
    's'        => [],
    'pre'      => [],
    'code'     => [],
    'strike'   => [],
    'hr'       => [],
    'br'       => [],
    'a'        => ['attributes' => ['href']],
    'img'      => ['attributes' => ['src']],
    'strong'   => ['emit' => 'b'],
    'em'       => ['emit' => 'i'],
    'div'      => self::EXPAND,
    '#comment' => self::REMOVE,
    'script'   => self::REMOVE,
    'embed'    => self::REMOVE,
    'object'   => self::REMOVE,
    'iframe'   => self::REMOVE,
  ];

  public function __construct(array<string, mixed> $handle= []) {
    $this->handle+= $handle;
  }

  private function process($nodes) {
    $text= '';
    foreach ($nodes as $node) {
      $handle= $this->handle[$node->nodeName] ?? null;
      if (null === $handle) {
        $text.= htmlspecialchars($node->textContent);
      } else if (self::REMOVE === $handle) {
        // NOOP
      } else if (self::EXPAND === $handle) {
        $text.= $this->process($node->childNodes);
      } else {
        $tag= $handle['emit'] ?? $node->nodeName;
        $text.= "<{$tag}";
        foreach ($handle['attributes'] ?? [] as $attribute) {
          if ($node->hasAttribute($attribute)) {
            $text.= ' '.$attribute.'="'.htmlspecialchars($node->getAttribute($attribute)).'"';
          }
        }
        $text.= ">{$this->process($node->childNodes)}</{$tag}>";
      }
    }
    return $text;
  }

  public function transform(string $input): string {
    if (strlen($input) === strspn($input, "\r\n\t ")) return $input;

    libxml_clear_errors();
    $useInternal= libxml_use_internal_errors(true);
    $entityLoader= libxml_get_external_entity_loader();
    libxml_set_external_entity_loader(fn() => null);

    try {
      $doc= new DOMDocument('1.0', 'utf-8');
      $doc->loadHTML(
        "<html><head><meta charset='utf-8'></head><body>{$input}</body></html>",
        LIBXML_NONET
      );
    } finally {
      libxml_use_internal_errors($useInternal);
      libxml_set_external_entity_loader($entityLoader);
    }

    return $this->process($doc->getElementsByTagName('body')->item(0)->childNodes);
  }
}