<?php namespace de\thekid\crews\web;

use com\mongodb\{Database, Collection, Document, ObjectId};
use de\thekid\crews\Events;
use util\Date;
use web\frontend\{Handler, Get, Post, Delete, Put, View, Param};

#[Handler('/group/{group}')]
class Group {
  private Collection $groups, $posts;

  public function __construct(Database $db, private Events $events) {
    $this->groups= $db->collection('groups');
    $this->posts= $db->collection('posts');
  }

  #[Get]
  public function index(ObjectId $group) {
    $groups= $this->groups->find($group);
    $posts= $this->posts->aggregate([
      ['$match' => ['group' => $group]],
      ['$sort'  => ['created' => -1]],
      ['$limit' => 20],
    ]);
    return View::named('group')->with(['group' => $groups->first(), 'posts' => $posts->all()]);
  }

  #[Get('/{view}')]
  public function group(ObjectId $group, string $view) {
    return View::named('group')->fragment($view)->with($this->groups
      ->find($group)
      ->first()
      ->properties()
    );
  }

  #[Put]
  public function describe(ObjectId $group, #[Param] string $description) {
    $result= $this->groups->run('findAndModify', [
      'query'  => ['_id' => $group],
      'update' => ['$set' => ['description' => $description]],
      'new'    => true,  // Return modified document
      'upsert' => false,
    ]);
    return View::named('group#description')->with($result->value()['value']);
  }

  #[Post('/posts')]
  public function create(ObjectId $group, #[Param] string $body) {
    $insert= $this->posts->insert(new Document([
      'group'   => $group,
      'body'    => $body,
      'created' => Date::now(),
    ]));
    $this->events->publish($group, ['insert' => $insert->id()]);
    return View::empty()->status(204);
  }

  #[Get('/posts/{id}/{view}')]
  public function view(ObjectId $group, ObjectId $id, string $view) {
    return View::named('group')->fragment($view)->with($this->posts
      ->find($id)
      ->first()
      ->properties()
    );
  }

  #[Delete('/posts/{id}')]
  public function delete(ObjectId $group, ObjectId $id) {
    $this->posts->delete($id);
    $this->events->publish($group, ['delete' => $id]);
    return View::empty()->status(204);
  }

  #[Put('/posts/{id}')]
  public function update(ObjectId $group, ObjectId $id, #[Param] string $body) {
    $this->posts->update($id, ['$set' => [
      'body'    => $body,
      'updated' => Date::now(),
    ]]);
    $this->events->publish($group, ['update' => $id]);
    return View::empty()->status(204);
  }
}