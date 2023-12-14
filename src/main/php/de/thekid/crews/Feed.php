<?php namespace de\thekid\crews;

use com\mongodb\{MongoConnection, ObjectId};
use io\Path;
use io\redis\RedisProtocol;
use websocket\{Listener, Listeners};
use xp\websockets\Handler;

/** WebSockets feed listeners */
class Feed extends Listeners {

  public function serve($events) {
    $conn= new MongoConnection($this->environment->variable('MONGO_URI'));
    $sub= new RedisProtocol($this->environment->variable('REDIS_URI'));
    $templates= new Templating(new Path('src/main/handlebars'));
    $db= $conn->database($this->environment->variable('MONGO_DB'));

    $listener= new class($sub) extends Listener {
      public $subscribers= [];

      public function __construct(private $sub) { }

      public function open($conn) {
        $group= basename($conn->path());
        if (!isset($this->subscribers[$group])) {
          $this->sub->command('SUBSCRIBE', $group);
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
          $this->sub->command('UNSUBSCRIBE', $group);
        }
      }
    };

    // Broadcast messages to all connected clients
    $posts= $db->collection('posts');
    $events->add($sub->socket(), function() use($sub, $posts, $templates, $listener) {
      [$type, $channel, $message]= $sub->receive();

      // Message is formatted e.g. as "insert=65758e56b0d77810acc80ded"
      [$action, $id]= explode('=', $message, 2);
      $fragment= match ($action) {
        'insert' => {
          $post= $posts->find(new ObjectId($id))->first();
          return sprintf(
            '<div id="posts" hx-swap-oob="afterbegin">%s</div>',
            $templates->render('group', $post->properties(), 'post')
          );
        },
        'update' => {
          $post= $posts->find(new ObjectId($id))->first();
          return $templates->render('group', $post->properties() + ['swap' => 'outerHTML'], 'post');
        },
        'delete' => {
          return $templates->render('group', ['_id' => $id, 'swap' => 'delete'], 'post');
        },
      };

      foreach ($listener->subscribers[$channel] as $connection) {
        $connection->send($fragment);
      }
    });

    return ['/' => $listener];
  }
}