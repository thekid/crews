<?php namespace de\thekid\crews;

use com\mongodb\{MongoConnection, ObjectId};
use io\Path;
use io\redis\RedisProtocol;
use websocket\{Listener, Listeners};
use xp\websockets\Handler;

/** WebSockets feed listeners */
class Feed extends Listeners {

  public function serve($listeners) {
    $conn= new MongoConnection($this->environment->variable('MONGO_URI'));
    $events= new Events(new RedisProtocol($this->environment->variable('REDIS_URI')));
    $templates= new Templating(new Path('src/main/handlebars'));
    $db= $conn->database($this->environment->variable('MONGO_DB'));

    $listener= new class($events) extends Listener {
      public $subscribers= [];

      public function __construct(private $events) { }

      public function open($conn) {
        $group= basename($conn->path());
        if (!isset($this->subscribers[$group])) {
          $this->events->subscribe($group);
          $this->subscribers[$group]= [$conn->id() => $conn];
        } else {
          $this->subscribers[$group][$conn->id()]= $conn;
        }
      }

      public function message($conn, $data) {
        // NOOP
      }

      public function close($conn) {
        $group= basename($conn->path());
        unset($this->subscribers[$group][$conn->id()]);
        if (empty($this->subscribers[$group])) {
          $this->events->unsubscribe($group);
        }
      }
    };

    // Render a given post
    $posts= $db->collection('posts');
    $render= function($postId, $context= []) use($posts, $templates) {
      $postId && $context+= $posts->find(new ObjectId($postId))->first()->properties();
      return $templates->render('group', $context, 'post');
    };

    // Broadcast messages to all connected clients
    $listeners->add($events->socket(), function() use($events, $render, $listener) {
      foreach ($events->receive() as $group => $event) {
        $fragment= match (key($event)) {
          'insert' => sprintf('<div id="posts" hx-swap-oob="afterbegin">%s</div>', $render(current($event))),
          'update' => $render(current($event), ['swap' => 'outerHTML']),
          'delete' => $render(null, ['_id' => current($event), 'swap' => 'delete']),
        };

        foreach ($listener->subscribers[$group] as $connection) {
          $connection->send($fragment);
        }
      }
    });

    return $listener;
  }
}