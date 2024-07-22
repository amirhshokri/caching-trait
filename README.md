# How to use Cachable Trait?

This Trait is used to add simple caching logic to laravel models.

### Usage Instructions:

1. First, add the Trait to the desired model:

```php
use Cachable;
```

2. Then, add the `cacheKey$` property to the model. This property should include the names of the foreign keys you wish to cache. For example:

```php
protected array $cacheKey = ['user_id', 'country_id'];
```

3. That's all! Now, you can use the following methods to retrieve the desired results:

```php
App\Models\Model::getOneById($value);

App\Models\Model::getCollectionByIds(array $ids);

App\Models\Model::getCollectionBy(string $key, $value);
```

#### Additional Notes:

It is obvious that the search is first conducted in the cache, and if the desired data is not found in the cache, the result is retrieved from the database and cached for future use.

Currently, it is not possible to cache models in groups, and each instance of the model is cached individually during creation or updating.

The `id` referred to in the `getOneById` and `getCollectionByIds` methods is the primary key of the desired model, which is automatically detected by the Trait.

If you want to use the `getCollectionBy` method, ensure that the desired foreign key is present in the `$cacheKey` property. For example:

```php
    $results = App\Models\Model::getCollectionBy('country_id', 10);
```

### How Does This Trait Work?

During the creation or updating of a model, the cache is updated using the `$model::created` and `$model::updated` events to ensure that the results are always consistent with the database.

The cache key structure is designed as `prefix:model_name:key:value`.

For example:

```
prefix:Log:id:9511
prefix:Log:user_id:10
prefix:Log:country_id:23
```

In this example, the keys `user_id` and `country_id` are retrieved and cached from the `$cacheKey` property. In the value of these keys, there are reference IDs to the desired models. For example:

```
"a:3:{i:0;s:11:\"Log:id:9511\";i:1;s:11:\"Log:id:9510\";i:2;s:11:\"Log:id:9509\";}"
```

These values change simultaneously with the creation or updating of the model.

Additionally, a TTL (Time To Live) is considered at the beginning of the Trait, which is changeable. The default value is one day or 86400 seconds:

```php
private static int $ttl = 86400; 
```
