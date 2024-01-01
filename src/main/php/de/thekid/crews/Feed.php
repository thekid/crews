<?php namespace de\thekid\crews;

use com\mongodb\{MongoConnection, ObjectId};
use io\Path;
use io\redis\RedisProtocol;
use websocket\{Listener, Listeners};
use xp\websockets\Handler;

/** WebSockets feed listeners */
class Feed extends Listeners {

  public function serve($listeners) {
    $config= $this->environment->properties('config');
    $conn= new MongoConnection($config->readString('mongo', 'connect'));
    $events= new Events(new RedisProtocol($config->readString('redis', 'connect')));
    $db= $conn->database($config->readString('mongo', 'database'));
    $templates= new Templating(new Path('src/main/handlebars'));

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
          unset($this->subscribers[$group]);
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
    $logging= $this->environment->logging();
    $listeners->add($events->socket(), function() use($logging, $events, $render, $listener) {
      foreach ($events->receive() as $group => $event) {
        $logging->log(0, "BROADCAST<{$group}>", $event);

        $ctx= ['request' => ['values' => ['user' => ['_id' => $event['user']]]]];
        $fragment= match ($event['kind']) {
          'insert' => "<div id='posts' hx-swap-oob='afterbegin'>{$render($event['argument'], $ctx)}</div>"
          'update' => $render($event['argument'], ['swap' => 'outerHTML', ...$ctx]),
          'delete' => $render(null, ['_id' => $event['argument'], 'swap' => 'delete', ...$ctx]),
        };

        foreach ($listener->subscribers[$group] as $connection) {
          $connection->send($fragment);
        }
      }
    });

    return $listener;
  }
}