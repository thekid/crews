<?php namespace de\thekid\crews;

use com\mongodb\ObjectId;
use io\redis\RedisProtocol;

class Events {

  public function __construct(private RedisProtocol $redis) { }

  /** @return peer.Socket */
  public function socket() { return $this->redis->socket(); }

  /** Publish an event of the form `[kind => argument]` in a given group */
  public function publish(User $user, string|ObjectId $group, array<string, mixed> $event): void {
    $this->redis->command('PUBLISH', (string)$group, sprintf(
      'user=%s&kind=%s&argument=%s',
      $user->id,
      key($event),
      current($event)
    ));
  }

  /** Subscribe to updates from a given group */
  public function subscribe(string|ObjectId $group): void {
    $this->redis->command('SUBSCRIBE', (string)$group);
  }

  /** Receive updates, yielding group and event */
  public function receive(): iterable {
    [$type, $group, $message]= $this->redis->receive();
    parse_str($message, $event);
    yield $group => $event;
  }

  /** Unsubscribe from updates of a given group */
  public function unsubscribe(string|ObjectId $group): void {
    $this->redis->command('UNSUBSCRIBE', (string)$group);
  }
}