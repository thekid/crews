<?php namespace de\thekid\crews\web;

use com\mongodb\{Database, Collection, Document};
use util\Date;
use web\frontend\{Handler, Get, Delete, Post, Param, View};

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

  #[Get('/dialog/{dialog}')]
  public function show($dialog) {
    return View::named('index')->fragment($dialog);
  }

  #[Delete('/dialog/{dialog}')]
  public function hide($dialog) {
    return View::empty()->status(201);
  }

  #[Post('/create')]
  public function create(#[Param] $name, #[Param] $description) {
    $name= trim($name);

    // Verify name is unique, return "Multiple Choices" status code
    if ($group= $this->groups->find(['name' => $name])->first()) {
      return View::named('index#form')
        ->status(300)
        ->with(['name' => $name, 'description' => $description, 'taken' => $group])
      ;
    }

    // Create, then trigger redirect
    $insert= $this->groups->insert(new Document([
      'name'        => $name,
      'description' => $description,
      'created'     => Date::now(),
    ]));
    return View::empty()->header('HX-Redirect', "/group/{$insert->id()}");
  }
}