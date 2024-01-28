<?php namespace de\thekid\crews;

use com\mongodb\{MongoConnection, Document};
use io\redis\RedisProtocol;
use web\Application;
use web\auth\SessionBased;
use web\auth\oauth\{OAuth2Flow, BySecret};
use web\frontend\{Frontend, AssetsFrom, HandlersIn, HtmxFlow};
use web\session\InFileSystem;

/** Web frontend */
class App extends Application {

  public function routes() {
    $config= $this->environment->properties('config');
    $conn= new MongoConnection($config->readString('mongo', 'connect'));
    $events= new Events(new RedisProtocol($config->readString('redis', 'connect')));
    $db= $conn->database($config->readString('mongo', 'database'));

    $sessions= new InFileSystem($this->environment->tempDir());
    $sessions->cookies()->insecure('dev' === $this->environment->profile());

    // Map OAuth user to local users
    $flow= new OAuth2Flow(
      $config->readString('oauth', 'authorize'),
      $config->readString('oauth', 'token'),
      new BySecret($config->readString('oauth', 'client'), $config->readString('oauth', 'secret')),
      '/',
      $config->readArray('oauth', 'scopes'),
    );
    $auth= new SessionBased(new HtmxFlow($flow), $sessions, $flow->fetchUser($config->readString('oauth', 'userinfo'))
      ->map(new UserAttributes($config->readSection('user')))
      ->map(fn($user) => $db->collection('users')
        ->modify(['handle' => $user['handle']], ['$set' => $user], upsert: true)
        ->document()
        ->properties()
      )
    );

    return [
      '/static' => new AssetsFrom($this->environment->path('src/main/webapp')),
      '/'       => $auth->required(new Frontend(
        new HandlersIn('de.thekid.crews.web', fn($class) => $class->newInstance($db, $events)),
        new Templating($this->environment->path('src/main/handlebars')),
      )),
    ];
  }
}