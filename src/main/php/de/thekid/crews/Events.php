<?php namespace de\thekid\crews;

use io\redis\RedisProtocol;

class Events {

  public function __construct(private RedisProtocol $redis) { }

  /** @return peer.Socket */
  public function socket() { return $this->redis->socket(); }

  /** Publish an event in a given group */
  public function publish(string $group, array<string, mixed> $event): void {
    $pass= '';
    foreach ($event as $key => $value) {
      $pass.= '&'.urlencode($key).'='.urlencode($value);
    }
    $this->redis->command('PUBLISH', $group, substr($pass, 1));
  }

  /** Subscribe to updates from a given group */
  public function subscribe(string $group): void {
    $this->redis->command('SUBSCRIBE', $group);
  }

  /** Receive updates, yielding group and event */
  public function receive(): iterable {
    [$type, $group, $message]= $this->redis->receive();
    parse_str($message, $event);
    yield $group => $event;
  }

  /** Unsubscribe from updates of a given group */
  public function unsubscribe(string $group): void {
    $this->redis->command('UNSUBSCRIBE', $group);
  }
}