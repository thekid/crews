<?php namespace de\thekid\crews;

use com\mongodb\MongoConnection;
use io\redis\RedisProtocol;
use web\Application;
use web\frontend\helpers\{Dates, Functions};
use web\frontend\{Frontend, AssetsFrom, HandlersIn, Handlebars};

/** Web frontend */
class App extends Application {

  public function routes() {
    $conn= new MongoConnection($this->environment->variable('MONGO_URI'));
    $db= $conn->database($this->environment->variable('MONGO_DB'));
    $pub= new RedisProtocol($this->environment->variable('REDIS_URI'));

    $templates= new Handlebars($this->environment->path('src/main/handlebars'), [
      new Dates(null),
      new Functions([
        'emoji' => fn($node, $context, $options) => preg_match('/^\\p{So}+$/u', $options[0]),
      ])
    ]);
    return [
      '/static' => new AssetsFrom($this->environment->path('src/main/webapp')),
      '/'       => new Frontend(
        new HandlersIn('de.thekid.crews.web', fn($class) => $class->newInstance($db, $pub)),
        $templates,
      ),
    ];
  }
}