<?php namespace de\thekid\crews;

/** Maps user attributes using a handlebars-style syntax */
class UserAttributes {

  public function __construct(private array<string, string> $mapping) { }

  public function __invoke($input) {
    $replace= function($m) use($input) {
      foreach (explode('.', trim($m[1])) as $segment) {
        $input= $input[$segment];
      }
      return $input;
    };
    foreach ($this->mapping as $field => $template) {
      yield $field => preg_replace_callback('/\{\{([^}]+)\}\}/', $replace, $template);
    }
  }
}