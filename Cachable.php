<?php

namespace App\Models;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

trait Cachable
{
    /**
     * @var int
     */
    private static int $ttl = 86400; //24 hours

    /**
     * @return void
     */
    protected static function bootCachable(): void
    {
        try {
            static::created(function ($modelDB) {
                $cacheKey = self::createCacheKey($modelDB->primaryKey, $modelDB->getKey());
                self::createCache($cacheKey, $modelDB);
            });

            static::updated(function ($modelDB) {
                $cacheKey = self::createCacheKey($modelDB->primaryKey, $modelDB->getKey());
                self::createCache($cacheKey, $modelDB);
            });
        }catch (Throwable $exception){
            //do nothing
        }
    }

    /**
     * @param $value
     * @return mixed
     */
    public static function getOneById($value)
    {
        $cacheKey = self::createCacheKey(self::getPrimaryKey(), $value);
        $cacheExists = Cache::has($cacheKey);

        if($cacheExists){
            return Cache::get($cacheKey);
        }else{
            $modelDB = static::query()->find($value);
            self::createCache($cacheKey, $modelDB);
            return $modelDB;
        }
    }

    /**
     * @param array $ids
     * @return Collection
     */
    public static function getCollectionByIds(array $ids): Collection
    {
        $cachedModels = [];

        //check if cache exists
        foreach ($ids as $key => $value){
            $cacheKey = self::createCacheKey(self::getPrimaryKey(), $value);
            $cacheExists = Cache::has($cacheKey);

            if(!$cacheExists){
                continue;
            }

            $cachedModels[] = Cache::get($cacheKey);
            unset($ids[$key]);
        }

        if(!count($ids)){
            return collect($cachedModels);
        }

        //if cache does not exist, get objects form db and store cache
        $modelsDB = static::query()->whereIn(self::getPrimaryKey(), $ids)->get()->all();

        foreach ($modelsDB as $modelDB){
            $cacheKey = self::createCacheKey(self::getPrimaryKey(), $modelDB->getKey());
            self::createCache($cacheKey, $modelDB);
        }

        //merge both results
        return collect(array_merge($modelsDB, $cachedModels));
    }

    /**
     * @param string $key
     * @param $value
     * @return Collection
     */
    public static function getCollectionBy(string $key, $value): Collection
    {
        //check if cache exists
        $cacheKey = self::createCacheKey($key, $value);
        $cacheExists = Cache::has($cacheKey);

        if($cacheExists){
            //extract ids
            $ids = [];

            $cacheValue = Cache::get($cacheKey);

            foreach ($cacheValue as $_value){
                $ids[] = explode(":", $_value)[2];
            }
        }else{
            //if cache does not exist, get objects form db and store cache
            $modelsDB = static::query()
                ->where($key, $value)
                ->get();

            foreach ($modelsDB as $modelDB){
                $cacheKey = self::createCacheKey(self::getPrimaryKey(), $modelDB->getKey());
                self::createCache($cacheKey, $modelDB);
            }

            $ids = $modelsDB->pluck(self::getPrimaryKey())->all();
        }

        return self::getCollectionByIds($ids);
    }

    /**
     * @return self
     */
    private static function getModel(): self
    {
        return new static;
    }

    /**
     * @return string
     */
    private static function getPrimaryKey(): string
    {
        return self::getModel()->primaryKey;
    }

    /**
     * @param $key
     * @param $value
     * @return string
     */
    private static function createCacheKey($key, $value): string
    {
        $modelName = class_basename(self::getModel());

        return $modelName.":".$key.":".$value;
    }

    /**
     * @param $cacheKey
     * @param $modelDB
     * @return void
     */
    private static function createCache($cacheKey, $modelDB): void
    {
        Cache::put($cacheKey, $modelDB, self::$ttl);

        self::createRelationCache($cacheKey, $modelDB);
    }

    /**
     * @param $cacheKey
     * @param $modelDB
     * @return void
     */
    private static function createRelationCache($cacheKey, $modelDB): void
    {
        self::updatePreviousRelations($cacheKey, $modelDB);

        self::createNewRelations($cacheKey, $modelDB);
    }

    /**
     * @param $modelDB
     * @return array
     */
    private static function getCachableRelation($modelDB): array
    {
        $relations = [];

        if(isset($modelDB->cacheKey)){
            foreach ($modelDB->cacheKey as $cacheKey){
                $relations[] = [
                    "name" => $cacheKey,
                    "value" => $modelDB->{$cacheKey},
                ];
            }
        }

        return $relations;
    }

    /**
     * @param $cacheKey
     * @param $modelDB
     * @return void
     */
    private static function updatePreviousRelations($cacheKey, $modelDB): void
    {
        $modelChanges = array_intersect_key($modelDB->getOriginal(), $modelDB->getChanges());

        foreach ($modelChanges as $key => $value){
            $previousForeignCacheKey = self::createCacheKey($key, $value);

            $foreignCacheValue = Cache::get($previousForeignCacheKey) ?? [];

            $key = array_search($cacheKey, $foreignCacheValue);

            if($key !== false) {
                unset($foreignCacheValue[$key]);
                (count($foreignCacheValue)) ? Cache::put($previousForeignCacheKey, $foreignCacheValue, self::$ttl) : Cache::forget($previousForeignCacheKey);
            }
        }
    }

    /**
     * @param $cacheKey
     * @param $modelDB
     * @return void
     */
    private static function createNewRelations($cacheKey, $modelDB): void
    {
        $relations = self::getCachableRelation($modelDB);

        foreach ($relations as $relation){
            $foreignCacheKey = self::createCacheKey($relation["name"], $relation["value"]);

            //get previous data from cache
            $previousForeignCacheValue = Cache::get($foreignCacheKey) ?? [];

            //merge new data and previous data, then remove duplicates
            $newForeignCacheValue = array_unique(array_merge($previousForeignCacheValue, [$cacheKey]));

            //store new data in cache
            Cache::put($foreignCacheKey, $newForeignCacheValue, self::$ttl);
        }
    }
}
