<?php namespace de\thekid\crews\web;

use com\mongodb\{Database, Collection, Document};
use web\frontend\{Handler, Get, View};

#[Handler('/')]
class Index {
  private Collection $groups;

  public function __construct(Database $db) {
    $this->groups= $db->collection('groups');
  }

  #[Get]
  public function index() {
    $groups= $this->groups->aggregate([
      ['$sort' => ['created' => -1]],
      ['$limit' => 20],
    ]);
    return View::named('index')->with(['groups' => $groups->all()]);
  }
}