<?php namespace de\thekid\crews\unittest;

use de\thekid\crews\Markup;
use test\{Assert, Test, Values};

class MarkupTest {

  #[Test]
  public function can_create() {
    new Markup();
  }

  #[Test]
  public function empty_text() {
    $fixture= new Markup();
    Assert::equals('', $fixture->transform(''));
  }

  #[Test]
  public function whitespace_only() {
    $fixture= new Markup();
    Assert::equals("\r\n\t ", $fixture->transform("\r\n\t "));
  }

  #[Test]
  public function text() {
    $fixture= new Markup();
    Assert::equals('This is a test', $fixture->transform('This is a test'));
  }

  #[Test]
  public function markup() {
    $fixture= new Markup();
    Assert::equals('This <b>is</b> a test', $fixture->transform('This <b>is</b> a test'));
  }

  #[Test, Values([
    ['<strong>test</strong>', '<b>test</b>'],
    ['<em>test</em>', '<i>test</i>'],
  ])]
  public function rewrites($input, $expected) {
    $fixture= new Markup();
    Assert::equals($expected, $fixture->transform($input));
  }

  #[Test, Values([
    ['1 < 2'],
    ['1 & 2'],
    ['Test <&">&euro;&unknown; Works'],
  ])]
  public function does_not_contain_unescaped_entities($input) {
    $fixture= new Markup();
    $transformed= $fixture->transform($input);

    foreach (['/</', '/>/', '/"/', '/&(?![a-z]+;)/'] as $pattern) {
      Assert::equals(0, preg_match($pattern, $fixture->transform($input)));
    }
  }

  #[Test]
  public function html_entities_are_escaped() {
    $fixture= new Markup();
    Assert::equals(
      'Test &lt;&amp;&quot;&gt;€&amp;unknown; Works',
      $fixture->transform('Test &lt;&amp;&quot;&gt;&euro;&unknown; Works')
    );
  }

  #[Test, Values([
    ['Übercoder'],
    ['👉🙂'],
    ['中国'],  // "China"
  ])]
  public function loads_utf8($input) {
    $fixture= new Markup();
    Assert::equals($input, $fixture->transform($input));
  }

  #[Test, Values([
    ['<script>console.log("Test");</script>'],
    ['<script type="module">console.log("Test");</script>'],
    ['<SCRIPT>console.log("Test");</SCRIPT>'],
    ['<Script>console.log("Test");</Script>'],
  ])]
  public function removes_script_tags($input) {
    $fixture= new Markup();
    Assert::equals('', $fixture->transform($input));
  }

  #[Test]
  public function removes_unsupported_attributes() {
    $fixture= new Markup();
    Assert::equals(
      '<a href="/&quot;">test</a>',
      $fixture->transform('<a href="/&quot;" target="_new">test</a>')
    );
  }

  #[Test]
  public function removes_divs() {
    $fixture= new Markup();
    Assert::equals(
      'The container is gone',
      $fixture->transform('<div>The container is gone</div>')
    );
  }

  #[Test]
  public function removes_comments() {
    $fixture= new Markup();
    Assert::equals(
      'The  is gone',
      $fixture->transform('The <!-- comment --> is gone')
    );
  }

  #[Test, Values([
    ['<!ENTITY xxe SYSTEM "php://filter/read=convert.base64-encode/resource=http://example.com/logs">'],
    ['<!ENTITY xxe SYSTEM "http://example.com/entity1.xml">']
  ])]
  public function does_not_parse_doctype_with($entity) {
    $fixture= new Markup();
    Assert::equals(
      "]&gt;\n<p>&amp;xxe;</p>",
      ltrim($fixture->transform("<!DOCTYPE results [\n".$entity."\n]>\n<p>&xxe;</p>"))
    );
  }

  #[Test]
  public function svg_mutation_xss() {
    $fixture= new Markup();
    $exploit= '<svg></p><style><a id="</style><img src=1 onerror=alert(1)>">';
    Assert::equals('&lt;a id=&quot;&quot;&gt;', $fixture->transform($exploit));
  }

  #[Test]
  public function namespace_confusion_xss() {
    $fixture= new Markup();
    $exploit= '<form><math><mtext></form><form><mglyph><style></math><img src onerror=alert(1)>';
    Assert::equals('&lt;img src onerror=alert(1)&gt;', $fixture->transform($exploit));
  }
}