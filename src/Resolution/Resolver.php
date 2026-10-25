<?php

declare(strict_types=1);

namespace Atannex\Share\Resolution;

use Atannex\Share\Attributes\ShareAs;
use Atannex\Share\Contracts\Shareable;
use Atannex\Share\Exceptions\CannotResolveShare;
use Atannex\Share\ShareData;
use Atannex\Share\Subject;
use Closure;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use JsonSerializable;
use ReflectionClass;
use Stringable;

/**
 * Turns anything into a ShareData:
 *
 *   ShareData             as it is
 *   Shareable             its toShare()
 *   a registered class    the resolver given to Share::resolveUsing()
 *   Model                 #[ShareAs] mapping, else common field names
 *   "https://…"           that address
 *   "any other text"      a text-only share (platforms that need a link are left out)
 *   array / Arrayable     its fields
 *   Closure               whatever it returns
 *   null                  the current page
 */
final class Resolver
{
    /** @var array<class-string, Closure(object): (ShareData|array<string, mixed>)> */
    private array $resolvers = [];

    /** @var array<class-string, ShareAs|false> */
    private array $attributes = [];

    private const TITLE = ['share_title', 'title', 'name', 'headline', 'subject', 'label'];

    private const TEXT = ['share_text', 'description', 'summary', 'excerpt', 'lead', 'intro', 'body', 'content'];

    private const IMAGE = ['share_image', 'og_image', 'image_url', 'image', 'cover_url', 'cover', 'thumbnail_url', 'thumbnail', 'photo_url', 'avatar_url'];

    private const URL = ['share_url', 'url', 'permalink', 'link'];

    private const HASHTAGS = ['hashtags', 'tags'];

    public function __construct(
        private readonly Normalizer $normalizer,
        private readonly UrlGenerator $urls,
        private readonly Router $router,
    ) {}

    /**
     * @param  class-string  $class
     * @param  Closure(object): (ShareData|array<string, mixed>)  $resolver
     */
    public function register(string $class, Closure $resolver): void
    {
        $this->resolvers[$class] = $resolver;
    }

    /**
     * @param  ShareData  $defaults  values used where the subject gives none
     */
    public function resolve(mixed $subject, ShareData $defaults = new ShareData): ShareData
    {
        $share = $this->toShare($subject, $defaults);

        // Defaults fill the gaps (site name, via…), they never override.
        $gaps = [];
        foreach (['via', 'siteName', 'locale'] as $field) {
            if ($share->{$field} === null && $defaults->{$field} !== null) {
                $gaps[$field] = $defaults->{$field};
            }
        }

        return $gaps === [] ? $share : $share->with($gaps);
    }

    private function toShare(mixed $subject, ShareData $defaults): ShareData
    {
        if ($subject instanceof ShareData) {
            return $subject;
        }

        if ($subject instanceof Closure) {
            return $this->toShare($subject(), $defaults);
        }

        if (is_object($subject)) {
            foreach ($this->resolvers as $class => $resolver) {
                if ($subject instanceof $class) {
                    return $this->fromValue($resolver($subject), $subject);
                }
            }
        }

        if ($subject instanceof Shareable) {
            return $this->fromValue($subject->toShare(), $subject);
        }

        if ($subject instanceof Model) {
            return $this->fromModel($subject);
        }

        if ($subject === null) {
            return $this->currentPage();
        }

        if (is_string($subject) || $subject instanceof Stringable) {
            return $this->fromString((string) $subject);
        }

        if (is_array($subject)) {
            return $this->normalizer->apply(new ShareData, $subject);
        }

        if ($subject instanceof Arrayable) {
            return $this->normalizer->apply(new ShareData, $this->known($subject->toArray()));
        }

        if ($subject instanceof JsonSerializable) {
            return $this->normalizer->apply(new ShareData, $this->known((array) $subject->jsonSerialize()));
        }

        throw CannotResolveShare::for($subject);
    }

    /**
     * @param  ShareData|array<string, mixed>  $value
     */
    private function fromValue(ShareData|array $value, object $source): ShareData
    {
        $share = $value instanceof ShareData ? $value : $this->normalizer->apply(new ShareData, $value);

        if ($share->subject === null && $source instanceof Model) {
            $share = $share->with(['subject' => Subject::of($source)]);
        }

        return $share;
    }

    private function fromString(string $value): ShareData
    {
        $value = trim($value);

        if (filter_var($value, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $value)) {
            return new ShareData(url: $value);
        }

        if (str_starts_with($value, '/') && ! str_contains($value, ' ')) {
            return new ShareData(url: $this->urls->to($value));
        }

        return $this->normalizer->apply(new ShareData, ['message' => $value]);
    }

    private function currentPage(): ShareData
    {
        return new ShareData(url: $this->urls->current());
    }

    private function fromModel(Model $model): ShareData
    {
        $map = $this->attribute($model);

        $values = [
            'url' => $this->modelUrl($model, $map),
            'title' => $this->pick($model, $map?->title, self::TITLE),
            'message' => $map?->message ? $this->field($model, $map->message) : null,
            'text' => $this->pick($model, $map?->text, self::TEXT),
            'image' => $this->pick($model, $map?->image, self::IMAGE),
            'hashtags' => $this->pick($model, $map?->hashtags, self::HASHTAGS),
            'via' => $map?->via ? $this->field($model, $map->via) : null,
            'type' => $map?->type,
            'subject' => $model,
        ];

        return $this->normalizer->apply(new ShareData, $values);
    }

    private function modelUrl(Model $model, ?ShareAs $map): string
    {
        if ($map?->url) {
            $url = $this->field($model, $map->url);
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        if ($map?->route) {
            $key = $map->routeKey ? $this->field($model, $map->routeKey) : $model->getRouteKey();
            $parameter = $map->routeParameter ?? $this->router->getRoutes()->getByName($map->route)?->parameterNames()[0] ?? null;

            return $this->urls->route($map->route, $parameter ? [$parameter => $key] : [$key]);
        }

        $url = $this->pick($model, null, self::URL);
        if (is_string($url) && $url !== '') {
            return $url;
        }

        foreach (['url', 'getUrl', 'shareUrl'] as $method) {
            if (method_exists($model, $method)) {
                return (string) $model->{$method}();
            }
        }

        throw CannotResolveShare::withoutUrl($model);
    }

    /**
     * The mapped field, else the first common name that has a value.
     *
     * @param  list<string>  $guesses
     */
    private function pick(Model $model, ?string $field, array $guesses): mixed
    {
        if ($field !== null) {
            return $this->field($model, $field);
        }

        $attributes = $model->getAttributes();

        foreach ($guesses as $name) {
            // Only attributes, accessors and loaded relations: guessing never runs a query or an arbitrary method.
            if (array_key_exists($name, $attributes) || $model->hasGetMutator($name) || $model->hasAttributeMutator($name) || $model->relationLoaded($name)) {
                $value = $model->relationLoaded($name) ? $model->getRelation($name) : $model->getAttribute($name);
                $value = $value instanceof Collection && $value->isEmpty() ? null : $value;

                if ($value !== null && $value !== '' && $value !== []) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * An attribute, a method or a dot path ("author.name", "tags.*.name").
     */
    private function field(Model $model, string $field): mixed
    {
        if (! str_contains($field, '.') && method_exists($model, $field) && ! $model->isRelation($field)) {
            return $model->{$field}();
        }

        return data_get($model, $field);
    }

    private function attribute(Model $model): ?ShareAs
    {
        $class = $model::class;

        if (! isset($this->attributes[$class])) {
            $attribute = (new ReflectionClass($model))->getAttributes(ShareAs::class)[0] ?? null;
            $this->attributes[$class] = $attribute?->newInstance() ?? false;
        }

        return $this->attributes[$class] ?: null;
    }

    /**
     * The fields of an array that ShareData knows.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function known(array $values): array
    {
        $fields = ['url', 'link', 'href', 'title', 'name', 'headline', 'message', 'text', 'description', 'summary', 'excerpt', 'image', 'hashtags', 'tags', 'via', 'video', 'files', 'embed'];

        return array_intersect_key($values, array_flip($fields));
    }
}
