<?php namespace de\thekid\crews;

use com\mongodb\{MongoConnection, ObjectId};
use io\redis\RedisProtocol;
use lang\Throwable;
use web\frontend\Handlebars;
use web\frontend\helpers\{Dates, Functions};
use websocket\Listeners;

/** WebSockets feed listeners */
class Feed extends Listeners {

  public function serve($events) {
    $conn= new MongoConnection($this->environment->variable('MONGO_URI'));
    $db= $conn->database($this->environment->variable('MONGO_DB'));
    $sub= new RedisProtocol($this->environment->variable('REDIS_URI'));
    $templates= new Handlebars('.', [
      new Dates(null),
      new Functions([
        'emoji' => fn($node, $context, $options) => preg_match('/^\\p{So}+$/u', $options[0])
      ])
    ]);

    // Broadcast messages to all connected clients
    $posts= $db->collection('posts');
    $sub->command('SUBSCRIBE', 'messages');
    $events->add($sub->socket(), function() use($sub, $posts, $templates) {
      [$type, $channel, $message]= $sub->receive();

      // Message is formatted e.g. as "insert=65758e56b0d77810acc80ded"
      [$action, $id]= explode('=', $message, 2);
      $fragment= match ($action) {
        'insert' => {
          $post= $posts->find(new ObjectId($id))->first();
          return sprintf(
            '<div id="posts" hx-swap-oob="afterbegin">%s</div>',
            $templates->render('news', $post->properties(), 'post')
          );
        },
        'update' => {
          $post= $posts->find(new ObjectId($id))->first();
          return $templates->render('news', $post->properties() + ['swap' => 'outerHTML'], 'post');
        },
        'delete' => {
          return $templates->render('news', ['_id' => $id, 'swap' => 'delete'], 'post');
        },
      };

      foreach ($this->connections as $connection) {
        $connection->send($fragment);
      }
    });

    // TODO: Subscribe to walls
    return [
      '/' => function($connection, $message) {
        return 'ACCEPTED';
      }
    ];
  }
}