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

  private function post(ObjectId $id, string $view= 'post') {
    return View::named('group')->fragment($view)->with($this->posts
      ->find($id)
      ->first()
      ->properties()
    );
  }

  #[Get]
  public function index(string $group) {
    $id= new ObjectId($group);
    $groups= $this->groups->find($id);
    $posts= $this->posts->aggregate([
      ['$match' => ['group' => $id]],
      ['$sort'  => ['created' => -1]],
      ['$limit' => 20],
    ]);
    return View::named('group')->with(['group' => $groups->first(), 'posts' => $posts->all()]);
  }

  #[Post('/posts')]
  public function create(string $group, #[Param] string $body) {
    $insert= $this->posts->insert(new Document([
      'group'   => new ObjectId($group),
      'body'    => $body,
      'created' => Date::now(),
    ]));
    $this->events->publish($group, ['insert' => $insert->id()]);
    return View::empty()->status(204);
  }

  #[Get('/posts/{id}/{view}')]
  public function view(string $group, string $id, string $view) {
    return $this->post(new ObjectId($id), $view);
  }

  #[Delete('/posts/{id}')]
  public function delete(string $group, string $id) {
    $this->posts->delete(new ObjectId($id));
    $this->events->publish($group, ['delete' => $id]);
    return View::empty()->status(204);
  }

  #[Put('/posts/{id}')]
  public function update(string $group, string $id, #[Param] string $body) {
    $post= new ObjectId($id);
    $this->posts->update($post, ['$set' => [
      'body'    => $body,
      'updated' => Date::now(),
    ]]);
    $this->events->publish($group, ['update' => $id]);
    return View::empty()->status(204);
  }
}