<?php namespace de\thekid\crews;

use com\mongodb\MongoConnection;
use io\redis\RedisProtocol;
use web\Application;
use web\frontend\{Frontend, AssetsFrom, HandlersIn};

/** Web frontend */
class App extends Application {

  public function routes() {
    $conn= new MongoConnection($this->environment->variable('MONGO_URI'));
    $pub= new Events(new RedisProtocol($this->environment->variable('REDIS_URI')));
    $db= $conn->database($this->environment->variable('MONGO_DB'));

    return [
      '/static' => new AssetsFrom($this->environment->path('src/main/webapp')),
      '/'       => new Frontend(
        new HandlersIn('de.thekid.crews.web', fn($class) => $class->newInstance($db, $pub)),
        new Templating($this->environment->path('src/main/handlebars')),
      ),
    ];
  }
}