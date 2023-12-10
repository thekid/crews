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

      sscanf($message, '%[^=]=%[0-9a-f]', $action, $id);
      switch ($action) {
        case 'insert':
          $post= $posts->find(new ObjectId($id))->first();
          $fragment= sprintf(
            '<div id="posts" hx-swap-oob="afterbegin">%s</div>',
            $templates->render('news', $post->properties(), 'post')
          );
          break;

        case 'update':
          $post= $posts->find(new ObjectId($id))->first();
          $fragment= $templates->render('news', $post->properties() + ['swap' => 'outerHTML'], 'post');
          break;

        case 'delete':
          $fragment= $templates->render('news', ['_id' => $id, 'swap' => 'delete'], 'post');
          break;

        default:
          // Ignore
          return;
      }

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