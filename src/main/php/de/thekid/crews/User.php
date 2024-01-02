<?php namespace de\thekid\crews;

use com\mongodb\ObjectId;

class User {
  public readonly ObjectId $id;

  /** Creates a new user */
  public function __construct(private array<string, mixed> $attributes) {
    $this->id= $attributes['_id'] instanceof ObjectId ? $attributes['_id'] : new ObjectId($attributes['_id']);
  }

  /** Returns criteria for lookup */
  public function where(ObjectId $id, string $role): array<string, mixed> {
    return ['_id' => $id, $role.'.id' => $this->id];
  }

  /** Returns a reference */
  public function reference(): array<string, mixed> {
    return ['id' => $this->id, 'name' => $this->attributes['name']];
  }
}