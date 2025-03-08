<?php namespace de\thekid\crews\web;

use com\mongodb\{Database, Collection, Document, ObjectId};
use de\thekid\crews\{Markup, Events, User};
use util\Date;
use web\frontend\{Handler, Get, Post, Delete, Param, Put, Value, View};

#[Handler('/group/{group}')]
class Group {
  private Collection $groups, $posts;
  private $markup= new Markup();

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
  public function describe(#[Value] User $user, ObjectId $group, #[Param] string $description) {
    $result= $this->groups->modify($user->where($group, 'owner'), ['$set' => [
      'description' => $this->markup->transform($description),
    ]]);
    return View::named('group#description')->with($result->document()->properties());
  }

  #[Post('/posts')]
  public function create(#[Value] User $user, ObjectId $group, #[Param] string $body) {
    $insert= $this->posts->insert(new Document([
      'group'   => $group,
      'body'    => $this->markup->transform($body),
      'editor'  => $user->reference(),
      'created' => Date::now(),
    ]));
    $this->events->publish($user, $group, ['insert' => $insert->id()]);
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
  public function delete(#[Value] User $user, ObjectId $group, ObjectId $id) {
    $this->posts->delete($user->where($id, 'editor'));
    $this->events->publish($user, $group, ['delete' => $id]);
    return View::empty()->status(204);
  }

  #[Put('/posts/{id}')]
  public function update(#[Value] User $user, ObjectId $group, ObjectId $id, #[Param] string $body) {
    $this->posts->update($user->where($id, 'editor'), ['$set' => [
      'body'    => $this->markup->transform($body),
      'updated' => Date::now(),
    ]]);
    $this->events->publish($user, $group, ['update' => $id]);
    return View::empty()->status(204);
  }
}