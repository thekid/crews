<?php namespace de\thekid\crews;

use io\Path;
use web\frontend\Handlebars;
use web\frontend\helpers\{Dates, Functions};

class Templating extends Handlebars {

  /** Sets up templating for our project */
  public function __construct(Path $templates) {
    parent::__construct($templates, [
      new Dates(null),
      new Functions([
        'emoji' => fn($node, $context, $options) => preg_match('/^\\p{So}+$/u', $options[0]),
      ])
    ]);
  }
}