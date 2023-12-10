<?php

use com\mongodb\{MongoConnection, Document, ObjectId};
use io\redis\RedisProtocol;
use util\Date;
use web\frontend\helpers\{Dates, Functions};
use web\frontend\{Frontend, AssetsFrom, Handlebars, Get, Post, Delete, Put, View, Param};
use web\{Application, Handler};

class App extends Application {

  public function routes() {
    $conn= new MongoConnection($this->environment->variable('MONGO_URI'));
    $db= $conn->database($this->environment->variable('MONGO_DB'));
    $pub= new RedisProtocol($this->environment->variable('REDIS_URI'));
    
    $impl= new class($db, $pub) {

      public function __construct(private $db, private $pub) { }

      private function post(ObjectId $id, string $view= 'post') {
        return View::named('news')->fragment($view)->with($this->db->collection('posts')
          ->find($id)
          ->first()
          ->properties()
        );
      }

      #[Get]
      public function index() {
        $posts= $this->db->collection('posts')->aggregate([
          ['$sort' => ['created' => -1]],
          ['$limit' => 20],
        ]);
        return View::named('news')->with(['posts' => $posts->all()]);
      }

      #[Post('/posts')]
      public function create(#[Param] string $body) {
        $insert= $this->db->collection('posts')->insert(new Document([
          'body'    => $body,
          'created' => Date::now(),
        ]));
        $this->pub->command('PUBLISH', 'messages', "insert={$insert->id()}");
        return View::empty()->status(204); // $this->post($insert->id());
      }

      #[Get('/posts/{id}/{view}')]
      public function view(string $id, string $view) {
        return $this->post(new ObjectId($id), $view);
      }

      #[Delete('/posts/{id}')]
      public function delete(string $id) {
        $this->db->collection('posts')->delete(new ObjectId($id));
        $this->pub->command('PUBLISH', 'messages', "delete={$id}");
        return View::empty()->status(204); // 202
      }

      #[Put('/posts/{id}')]
      public function update(string $id, #[Param] string $body) {
        $post= new ObjectId($id);
        $this->db->collection('posts')->update($post, ['$set' => [
          'body'    => $body,
          'updated' => Date::now(),
        ]]);
        $this->pub->command('PUBLISH', 'messages', "update={$id}");
        return View::empty()->status(204); // $this->post($post);
      }
    };

    $templates= new Handlebars('.', [
      new Dates(null),
      new Functions([
        'emoji' => fn($node, $context, $options) => preg_match('/^\\p{So}+$/u', $options[0])
      ])
    ]);
    return [
      '/static' => new AssetsFrom($this->environment->webroot()),
      '/'       => new Frontend($impl, $templates)
    ];
  }
}