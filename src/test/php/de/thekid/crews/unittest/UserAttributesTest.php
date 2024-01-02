<?php namespace de\thekid\crews\unittest;

use de\thekid\crews\UserAttributes;
use test\{Assert, Test};

class UserAttributesTest {

  #[Test]
  public function can_create() {
    new UserAttributes([]);
  }

  #[Test]
  public function map_directly() {
    $fixture= new UserAttributes(['handle' => '{{id}}']);
    Assert::equals(
      ['handle' => 'test'], 
      [...$fixture(['id' => 'test'])],
    );
  }

  #[Test]
  public function map_concatenating() {
    $fixture= new UserAttributes(['name' => '{{first}} {{last}}']);
    Assert::equals(
      ['name' => 'Timm Test'],
      [...$fixture(['first' => 'Timm', 'last' => 'Test'])],
    );
  }

  #[Test]
  public function path_access() {
    $fixture= new UserAttributes(['name' => '{{name.first}} {{name.middle.0}}. {{name.last}}']);
    Assert::equals(
      ['name' => 'Timm P. Test'],
      [...$fixture(['name' => ['first' => 'Timm', 'middle' => 'PHP', 'last' => 'Test']])]
    );
  }
}