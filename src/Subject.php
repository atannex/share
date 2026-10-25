<?php

declare(strict_types=1);

namespace Atannex\Share;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The model a share is about, carried through signed share links as
 * "type:id" (the morph alias when one is registered).
 */
final class Subject
{
    public function __construct(
        public readonly string $type,
        public readonly string $id,
    ) {}

    public static function of(Model $model): self
    {
        return new self($model->getMorphClass(), (string) $model->getKey());
    }

    public static function parse(?string $reference): ?self
    {
        if ($reference === null || ! str_contains($reference, ':')) {
            return null;
        }

        [$type, $id] = explode(':', $reference, 2);

        return $type !== '' && $id !== '' ? new self($type, $id) : null;
    }

    public function reference(): string
    {
        return $this->type.':'.$this->id;
    }

    /**
     * The model class: the morph map's, else the type itself.
     *
     * @return class-string<Model>|null
     */
    public function modelClass(): ?string
    {
        $class = Relation::getMorphedModel($this->type) ?? $this->type;

        return class_exists($class) && is_subclass_of($class, Model::class) ? $class : null;
    }

    public function resolve(): ?Model
    {
        $class = $this->modelClass();

        return $class ? $class::query()->find($this->id) : null;
    }

    public function is(Model $model): bool
    {
        return $this->type === $model->getMorphClass() && $this->id === (string) $model->getKey();
    }

    /**
     * @return array{type: string, id: string}
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'id' => $this->id];
    }
}
