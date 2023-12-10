<?php namespace de\thekid\crews\web;

use com\mongodb\{Database, Document, ObjectId};
use io\redis\RedisProtocol;
use util\Date;
use web\frontend\{Handler, Get, Post, Delete, Put, View, Param};

#[Handler('/group/{group}')]
class Group {

  public function __construct(private Database $db, private RedisProtocol $pub) { }

  private function post(ObjectId $id, string $view= 'post') {
    return View::named('group')->fragment($view)->with($this->db->collection('posts')
      ->find($id)
      ->first()
      ->properties()
    );
  }

  #[Get]
  public function index(string $group) {
    $id= new ObjectId($group);
    $groups= $this->db->collection('groups')->find($id);
    $posts= $this->db->collection('posts')->aggregate([
      ['$match' => ['group' => $id]],
      ['$sort'  => ['created' => -1]],
      ['$limit' => 20],
    ]);
    return View::named('group')->with(['group' => $groups->first(), 'posts' => $posts->all()]);
  }

  #[Post('/posts')]
  public function create(string $group, #[Param] string $body) {
    $insert= $this->db->collection('posts')->insert(new Document([
      'group'   => new ObjectId($group),
      'body'    => $body,
      'created' => Date::now(),
    ]));
    $this->pub->command('PUBLISH', 'messages', "insert={$insert->id()}");
    return View::empty()->status(204); // $this->post($insert->id());
  }

  #[Get('/posts/{id}/{view}')]
  public function view(string $group, string $id, string $view) {
    return $this->post(new ObjectId($id), $view);
  }

  #[Delete('/posts/{id}')]
  public function delete(string $group, string $id) {
    $this->db->collection('posts')->delete(new ObjectId($id));
    $this->pub->command('PUBLISH', 'messages', "delete={$id}");
    return View::empty()->status(204); // 202
  }

  #[Put('/posts/{id}')]
  public function update(string $group, string $id, #[Param] string $body) {
    $post= new ObjectId($id);
    $this->db->collection('posts')->update($post, ['$set' => [
      'body'    => $body,
      'updated' => Date::now(),
    ]]);
    $this->pub->command('PUBLISH', 'messages', "update={$id}");
    return View::empty()->status(204); // $this->post($post);
  }
}