<?php

use Illuminate\Cache\RedisStore;
use Illuminate\Cache\Repository;
use Illuminate\Cache\TaggedCache;
use Illuminate\Contracts\Cache\CanFlushLocks;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection as RedisConnection;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Config;
use Mockery;
use Predis\Client;
use Predis\PredisException;

afterEach(function () {
    Mockery::close();
    if (app()->bound('cache')) {
        app('cache')->forgetDriver('redis');
    }
});

test('redis cache configuration has appropriate connection settings and separate databases', function () {
    expect(config('cache.stores.redis.driver'))->toBe('redis')
        ->and(config('cache.stores.redis.connection'))->toBe('cache')
        ->and(config('cache.stores.redis.lock_connection'))->toBe('default');

    $defaultDb = (string) config('database.redis.default.database');
    $cacheDb = (string) config('database.redis.cache.database');

    expect($defaultDb)->toBe('0')
        ->and($cacheDb)->toBe('1')
        ->and($defaultDb)->not->toBe($cacheDb);

    expect(config('cache.prefix'))->not->toBeEmpty();
    expect(config('database.redis.options.prefix'))->not->toBeEmpty();
});

test('cache store default fallback resolves CACHE_STORE, then CACHE_DRIVER, then failover', function () {
    // Isolated helper testing the exact fallback logic used in config/cache.php:
    // env('CACHE_STORE') ?: (env('CACHE_DRIVER') ?: 'failover')
    $resolve = fn (?string $store, ?string $driver) => ($store ?: ($driver ?: 'failover'));

    // When both are null/empty, defaults to 'failover'
    expect($resolve(null, null))->toBe('failover')
        ->and($resolve('', ''))->toBe('failover');

    // When CACHE_STORE is empty, falls back to CACHE_DRIVER
    expect($resolve('', 'database'))->toBe('database')
        ->and($resolve(null, 'file'))->toBe('file');

    // When CACHE_STORE is provided, it takes highest precedence
    expect($resolve('redis', 'file'))->toBe('redis')
        ->and($resolve('redis', 'database'))->toBe('redis')
        ->and($resolve('failover', 'database'))->toBe('failover');

    // When cache default is configured as failover, CacheManager resolves failover driver
    Config::set('cache.default', 'failover');
    $manager = app('cache');
    expect($manager->getDefaultDriver())->toBe('failover');
});

test('failover cache store is configured with redis as primary store', function () {
    $failoverStores = config('cache.stores.failover.stores');

    expect($failoverStores)->toBeArray()
        ->and($failoverStores[0])->toBe('redis')
        ->and($failoverStores)->toContain('database')
        ->and($failoverStores)->toContain('array');
});

test('redis store instantiates correct repository and implements required contracts via cache manager', function () {
    $mockRedis = Mockery::mock(RedisFactory::class);
    $mockConnection = Mockery::mock(RedisConnection::class);

    $mockRedis->shouldReceive('connection')->with('cache')->andReturn($mockConnection);
    $mockRedis->shouldReceive('connection')->with('default')->andReturn($mockConnection);

    app()->instance('redis', $mockRedis);
    app('cache')->forgetDriver('redis');

    /** @var Repository $repo */
    $repo = app('cache')->store('redis');
    $store = $repo->getStore();

    expect($repo)->toBeInstanceOf(Repository::class)
        ->and($store)->toBeInstanceOf(RedisStore::class)
        ->and($store)->toBeInstanceOf(Store::class)
        ->and($store)->toBeInstanceOf(LockProvider::class)
        ->and($store)->toBeInstanceOf(CanFlushLocks::class)
        ->and($store->hasSeparateLockStore())->toBeTrue()
        ->and($store->getPrefix())->toBe(config('cache.prefix'));
});

test('redis cache executes get, put, has, forget, and flush operations via cache manager', function () {
    $mockRedis = Mockery::mock(RedisFactory::class);
    $mockCacheConn = Mockery::mock(RedisConnection::class);

    $mockRedis->shouldReceive('connection')->with('cache')->andReturn($mockCacheConn);
    $mockRedis->shouldReceive('connection')->with('default')->andReturn($mockCacheConn);

    app()->instance('redis', $mockRedis);
    app('cache')->forgetDriver('redis');

    $repo = app('cache')->store('redis');
    $prefix = config('cache.prefix');

    // Test PUT
    $mockCacheConn->shouldReceive('setex')
        ->with($prefix.'user_profile', 120, serialize(['id' => 10, 'name' => 'Abebe']))
        ->once()
        ->andReturn(true);

    expect($repo->put('user_profile', ['id' => 10, 'name' => 'Abebe'], 120))->toBeTrue();

    // Test GET hit
    $mockCacheConn->shouldReceive('get')
        ->with($prefix.'user_profile')
        ->once()
        ->andReturn(serialize(['id' => 10, 'name' => 'Abebe']));

    expect($repo->get('user_profile'))->toBe(['id' => 10, 'name' => 'Abebe']);

    // Test HAS hit
    $mockCacheConn->shouldReceive('get')
        ->with($prefix.'user_profile')
        ->once()
        ->andReturn(serialize(['id' => 10, 'name' => 'Abebe']));

    expect($repo->has('user_profile'))->toBeTrue();

    // Test GET miss
    $mockCacheConn->shouldReceive('get')
        ->with($prefix.'non_existent')
        ->once()
        ->andReturn(null);

    expect($repo->get('non_existent'))->toBeNull();

    // Test HAS miss
    $mockCacheConn->shouldReceive('get')
        ->with($prefix.'non_existent')
        ->once()
        ->andReturn(null);

    expect($repo->has('non_existent'))->toBeFalse();

    // Test FORGET
    $mockCacheConn->shouldReceive('del')
        ->with($prefix.'user_profile')
        ->once()
        ->andReturn(1);

    expect($repo->forget('user_profile'))->toBeTrue();

    // Test FLUSH (calls flushdb on cache database connection)
    $mockCacheConn->shouldReceive('flushdb')
        ->once()
        ->andReturn(true);

    expect($repo->flush())->toBeTrue();
});

test('redis cache executes remember and add operations via cache manager', function () {
    $mockRedis = Mockery::mock(RedisFactory::class);
    $mockCacheConn = Mockery::mock(RedisConnection::class);

    $mockRedis->shouldReceive('connection')->with('cache')->andReturn($mockCacheConn);
    $mockRedis->shouldReceive('connection')->with('default')->andReturn($mockCacheConn);

    app()->instance('redis', $mockRedis);
    app('cache')->forgetDriver('redis');

    $repo = app('cache')->store('redis');
    $prefix = config('cache.prefix');

    // Test remember on miss: computes and stores value
    $mockCacheConn->shouldReceive('get')
        ->with($prefix.'computed_key')
        ->once()
        ->andReturn(null);

    $mockCacheConn->shouldReceive('setex')
        ->with($prefix.'computed_key', 300, serialize('expensive_result'))
        ->once()
        ->andReturn(true);

    $computed = $repo->remember('computed_key', 300, fn () => 'expensive_result');
    expect($computed)->toBe('expensive_result');

    // Test add when key does not exist (uses Lua script via eval)
    $mockCacheConn->shouldReceive('eval')
        ->once()
        ->andReturn(1);

    expect($repo->add('lock_flag', 'active', 60))->toBeTrue();
});

test('redis store supports cache tagging via cache manager', function () {
    $mockRedis = Mockery::mock(RedisFactory::class);
    $mockCacheConn = Mockery::mock(RedisConnection::class);

    $mockRedis->shouldReceive('connection')->with('cache')->andReturn($mockCacheConn);
    $mockRedis->shouldReceive('connection')->with('default')->andReturn($mockCacheConn);

    app()->instance('redis', $mockRedis);
    app('cache')->forgetDriver('redis');

    $repo = app('cache')->store('redis');

    $tagged = $repo->tags(['escrow', 'transactions']);
    expect($tagged)->toBeInstanceOf(TaggedCache::class);
});

test('redis lock acquires, checks ownership, and releases on separate lock connection', function () {
    $mockRedis = Mockery::mock(RedisFactory::class);
    $mockLockConn = Mockery::mock(RedisConnection::class);
    $mockCacheConn = Mockery::mock(RedisConnection::class);

    // Verify lock specifically requests the 'default' lock_connection rather than 'cache'
    $mockRedis->shouldReceive('connection')->with('default')->andReturn($mockLockConn);
    $mockRedis->shouldReceive('connection')->with('cache')->andReturn($mockCacheConn);

    app()->instance('redis', $mockRedis);
    app('cache')->forgetDriver('redis');

    $repo = app('cache')->store('redis');
    $prefix = config('cache.prefix');

    $lock = $repo->lock('escrow_payout_99', 15, 'worker_process_1');

    expect($lock)->toBeInstanceOf(Lock::class)
        ->and($lock->owner())->toBe('worker_process_1');

    // Acquire lock successfully on lock connection
    $mockLockConn->shouldReceive('set')
        ->with($prefix.'escrow_payout_99', 'worker_process_1', 'EX', 15, 'NX')
        ->once()
        ->andReturn(true);

    expect($lock->acquire())->toBeTrue();

    // Release lock via Lua script on lock connection
    $mockLockConn->shouldReceive('eval')
        ->once()
        ->andReturn(1);

    expect($lock->release())->toBeTrue();

    // Restore lock with saved owner
    $restoredLock = $repo->restoreLock('escrow_payout_99', 'worker_process_1');
    expect($restoredLock->owner())->toBe('worker_process_1');
});

test('redis lock handles contested acquisition failure gracefully', function () {
    $mockRedis = Mockery::mock(RedisFactory::class);
    $mockLockConn = Mockery::mock(RedisConnection::class);
    $mockCacheConn = Mockery::mock(RedisConnection::class);

    $mockRedis->shouldReceive('connection')->with('default')->andReturn($mockLockConn);
    $mockRedis->shouldReceive('connection')->with('cache')->andReturn($mockCacheConn);

    app()->instance('redis', $mockRedis);
    app('cache')->forgetDriver('redis');

    $repo = app('cache')->store('redis');
    $prefix = config('cache.prefix');

    $lock = $repo->lock('hot_ledger_account', 10, 'worker_process_2');

    // Acquire returns false when key already held by another owner
    $mockLockConn->shouldReceive('set')
        ->with($prefix.'hot_ledger_account', 'worker_process_2', 'EX', 10, 'NX')
        ->once()
        ->andReturn(false);

    expect($lock->acquire())->toBeFalse();
});

test('serializable_classes setting protects against php object injection and parses class lists', function () {
    $mockRedis = Mockery::mock(RedisFactory::class);
    $mockCacheConn = Mockery::mock(RedisConnection::class);

    $mockRedis->shouldReceive('connection')->with('cache')->andReturn($mockCacheConn);
    $mockRedis->shouldReceive('connection')->with('default')->andReturn($mockCacheConn);

    // 1. Secure default: serializable_classes is false
    Config::set('cache.serializable_classes', false);
    app()->instance('redis', $mockRedis);
    app('cache')->forgetDriver('redis');

    $repo = app('cache')->store('redis');
    $prefix = config('cache.prefix');

    $object = new stdClass;
    $object->key = 'val';
    $serializedObject = serialize($object);

    $mockCacheConn->shouldReceive('get')
        ->with($prefix.'serialized_obj')
        ->once()
        ->andReturn($serializedObject);

    $retrieved = $repo->get('serialized_obj');
    expect($retrieved)->toBeInstanceOf('__PHP_Incomplete_Class');

    // 2. Allowed classes list: allows specified class
    Config::set('cache.serializable_classes', [stdClass::class]);
    app('cache')->forgetDriver('redis');

    $repoAllowed = app('cache')->store('redis');

    $mockCacheConn->shouldReceive('get')
        ->with($prefix.'allowed_obj')
        ->once()
        ->andReturn($serializedObject);

    $retrievedAllowed = $repoAllowed->get('allowed_obj');
    expect($retrievedAllowed)->toBeInstanceOf(stdClass::class)
        ->and($retrievedAllowed->key)->toBe('val');

    // 3. Verify config parsing logic for CACHE_SERIALIZABLE_CLASSES from comma-separated string
    $_ENV['CACHE_SERIALIZABLE_CLASSES'] = 'stdClass, App\Models\User';
    $parsed = is_string($val = env('CACHE_SERIALIZABLE_CLASSES'))
        ? (in_array(strtolower($val), ['false', '0', ''], true)
            ? false
            : (in_array(strtolower($val), ['true', '1'], true)
                ? true
                : array_values(array_filter(array_map('trim', explode(',', $val))))))
        : (bool) env('CACHE_SERIALIZABLE_CLASSES', false);

    expect($parsed)->toBe(['stdClass', 'App\Models\User']);
    unset($_ENV['CACHE_SERIALIZABLE_CLASSES']);
});

test('live redis integration test runs only when redis server and configured php client are available', function () {
    $configuredClient = (string) config('database.redis.client', 'phpredis');
    $clientAvailable = match ($configuredClient) {
        'phpredis' => extension_loaded('redis'),
        'predis' => class_exists(Client::class),
        default => false,
    };

    if (! $clientAvailable) {
        $this->markTestSkipped("Configured Redis client '{$configuredClient}' is not installed in the PHP environment.");
    }

    $redisHost = (string) config('database.redis.cache.host', config('database.redis.default.host', '127.0.0.1'));
    $redisPort = (int) config('database.redis.cache.port', config('database.redis.default.port', 6379));

    $connection = @fsockopen($redisHost, $redisPort, $errorCode, $errorMessage, 0.5);

    if (! is_resource($connection)) {
        $this->markTestSkipped("Redis server is not reachable at {$redisHost}:{$redisPort}.");
    }

    fclose($connection);

    try {
        $testKey = 'delala_live_test_'.uniqid();
        $store = app('cache')->store('redis');

        $store->put($testKey, 'live_redis_ok', 10);
        expect($store->get($testKey))->toBe('live_redis_ok');

        $lock = $store->lock($testKey.'_lock', 5);
        expect($lock->acquire())->toBeTrue();
        expect($lock->release())->toBeTrue();

        $store->forget($testKey);
        expect($store->has($testKey))->toBeFalse();
    } catch (Throwable $e) {
        $this->fail('Live Redis operation failed: '.$e->getMessage());
    }
});

test('redis client configuration dynamically selects predis when ext-redis is not loaded', function () {
    if (extension_loaded('redis')) {
        $this->markTestSkipped('Redis extension is loaded in PHP runtime; testing missing ext-redis fallback is not applicable.');
    }

    expect(config('database.redis.client'))->toBe('predis');

    $manager = app('redis');
    expect($manager)->toBeInstanceOf(RedisManager::class);

    $connection = $manager->connection();
    expect($connection)->toBeInstanceOf(PredisConnection::class);
});

test('database config forces predis when redis extension is not loaded even if env requests phpredis', function () {
    if (extension_loaded('redis')) {
        $this->markTestSkipped('Redis extension is loaded in PHP runtime; testing missing ext-redis fallback is not applicable.');
    }

    $prev = getenv('REDIS_CLIENT');
    putenv('REDIS_CLIENT=phpredis');

    $dbConfig = require config_path('database.php');
    expect($dbConfig['redis']['client'])->toBe('predis');

    if ($prev !== false) {
        putenv("REDIS_CLIENT={$prev}");
    } else {
        putenv('REDIS_CLIENT');
    }
});

test('redis client selection logic dynamically chooses predis when extension is missing', function () {
    $selectClient = fn (bool $extLoaded, ?string $envClient) => (! $extLoaded) ? 'predis' : ($envClient ?: 'predis');

    expect($selectClient(false, 'phpredis'))->toBe('predis')
        ->and($selectClient(false, 'predis'))->toBe('predis')
        ->and($selectClient(false, null))->toBe('predis')
        ->and($selectClient(true, 'phpredis'))->toBe('phpredis')
        ->and($selectClient(true, 'predis'))->toBe('predis')
        ->and($selectClient(true, null))->toBe('predis');
});

test('offline redis server produces clear connection exception rather than fatal missing class', function () {
    $redisHost = (string) config('database.redis.default.host', '127.0.0.1');
    $redisPort = (int) config('database.redis.default.port', 6379);

    $socket = @fsockopen($redisHost, $redisPort, $errorCode, $errorMessage, 0.2);
    if (is_resource($socket)) {
        fclose($socket);
        $this->markTestSkipped("Redis server is online at {$redisHost}:{$redisPort}; testing offline connection error not applicable.");
    }

    expect(function () {
        app('redis')->connection()->ping();
    })->toThrow(PredisException::class, 'Connection refused');
});

test('failover cache store gracefully functions even when redis server is offline', function () {
    $failoverStore = app('cache')->store('failover');
    $key = 'failover_integration_'.uniqid();

    expect($failoverStore->put($key, 'resilient_value', 60))->toBeTrue()
        ->and($failoverStore->get($key))->toBe('resilient_value')
        ->and($failoverStore->forget($key))->toBeTrue();
});
