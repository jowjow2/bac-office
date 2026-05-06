<?php

use Illuminate\Support\Str;

$firstEnv = static function (array $keys, mixed $fallback = null): mixed {
    foreach ($keys as $key) {
        $value = env($key);

        if ($value !== null && $value !== '') {
            return $value;
        }
    }

    return $fallback;
};

// Accept common managed-database env names so production can use a provider URL
// or provider-specific credentials without needing to rename every secret.
$sqliteUrl = $firstEnv(['SQLITE_URL', 'DB_URL', 'DATABASE_URL']);
$mysqlUrl = $firstEnv(['MYSQL_URL', 'MARIADB_URL', 'DB_URL', 'DATABASE_URL']);
$mariadbUrl = $firstEnv(['MARIADB_URL', 'MYSQL_URL', 'DB_URL', 'DATABASE_URL']);
$pgsqlUrl = $firstEnv(['POSTGRES_URL', 'POSTGRES_URL_NON_POOLING', 'POSTGRES_PRISMA_URL', 'DB_URL', 'DATABASE_URL']);
$sqlsrvUrl = $firstEnv(['SQLSERVER_URL', 'MSSQL_URL', 'DB_URL', 'DATABASE_URL']);

$defaultConnection = $firstEnv(['DB_CONNECTION']);

if (! $defaultConnection) {
    $sharedUrl = $firstEnv([
        'DB_URL',
        'DATABASE_URL',
        'MYSQL_URL',
        'MARIADB_URL',
        'POSTGRES_URL',
        'POSTGRES_URL_NON_POOLING',
        'POSTGRES_PRISMA_URL',
        'SQLSERVER_URL',
        'MSSQL_URL',
        'SQLITE_URL',
    ]);

    $sharedScheme = is_string($sharedUrl)
        ? strtolower((string) parse_url($sharedUrl, PHP_URL_SCHEME))
        : null;

    $defaultConnection = match ($sharedScheme) {
        'mysql' => 'mysql',
        'mariadb' => 'mariadb',
        'pgsql', 'postgres', 'postgresql' => 'pgsql',
        'sqlsrv', 'mssql' => 'sqlsrv',
        'sqlite' => 'sqlite',
        default => null,
    };
}

if (! $defaultConnection) {
    if ($pgsqlUrl) {
        $defaultConnection = 'pgsql';
    } elseif ($mariadbUrl) {
        $defaultConnection = 'mariadb';
    } elseif ($mysqlUrl) {
        $defaultConnection = 'mysql';
    } elseif ($sqlsrvUrl) {
        $defaultConnection = 'sqlsrv';
    } else {
        $defaultConnection = 'sqlite';
    }
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => $defaultConnection,

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => $sqliteUrl,
            'database' => $firstEnv(['DB_DATABASE', 'SQLITE_DATABASE'], database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => $mysqlUrl,
            'host' => $firstEnv(['DB_HOST', 'MYSQL_HOST', 'MYSQLHOST', 'MARIADB_HOST', 'MARIADBHOST'], '127.0.0.1'),
            'port' => $firstEnv(['DB_PORT', 'MYSQL_PORT', 'MYSQLPORT', 'MARIADB_PORT', 'MARIADBPORT'], '3306'),
            'database' => $firstEnv(['DB_DATABASE', 'MYSQL_DATABASE', 'MYSQLDATABASE', 'MARIADB_DATABASE', 'MARIADBDATABASE'], 'laravel'),
            'username' => $firstEnv(['DB_USERNAME', 'MYSQL_USERNAME', 'MYSQLUSER', 'MARIADB_USERNAME', 'MARIADBUSER'], 'root'),
            'password' => $firstEnv(['DB_PASSWORD', 'MYSQL_PASSWORD', 'MYSQLPASSWORD', 'MARIADB_PASSWORD', 'MARIADBPASSWORD'], ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_CA : \PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => $mariadbUrl,
            'host' => $firstEnv(['DB_HOST', 'MARIADB_HOST', 'MARIADBHOST', 'MYSQL_HOST', 'MYSQLHOST'], '127.0.0.1'),
            'port' => $firstEnv(['DB_PORT', 'MARIADB_PORT', 'MARIADBPORT', 'MYSQL_PORT', 'MYSQLPORT'], '3306'),
            'database' => $firstEnv(['DB_DATABASE', 'MARIADB_DATABASE', 'MARIADBDATABASE', 'MYSQL_DATABASE', 'MYSQLDATABASE'], 'laravel'),
            'username' => $firstEnv(['DB_USERNAME', 'MARIADB_USERNAME', 'MARIADBUSER', 'MYSQL_USERNAME', 'MYSQLUSER'], 'root'),
            'password' => $firstEnv(['DB_PASSWORD', 'MARIADB_PASSWORD', 'MARIADBPASSWORD', 'MYSQL_PASSWORD', 'MYSQLPASSWORD'], ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_CA : \PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => $pgsqlUrl,
            'host' => $firstEnv(['DB_HOST', 'POSTGRES_HOST', 'PGHOST'], '127.0.0.1'),
            'port' => $firstEnv(['DB_PORT', 'POSTGRES_PORT', 'PGPORT'], '5432'),
            'database' => $firstEnv(['DB_DATABASE', 'POSTGRES_DATABASE', 'PGDATABASE'], 'laravel'),
            'username' => $firstEnv(['DB_USERNAME', 'POSTGRES_USER', 'PGUSER'], 'root'),
            'password' => $firstEnv(['DB_PASSWORD', 'POSTGRES_PASSWORD', 'PGPASSWORD'], ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => $sqlsrvUrl,
            'host' => $firstEnv(['DB_HOST', 'SQLSERVER_HOST', 'MSSQL_HOST'], 'localhost'),
            'port' => $firstEnv(['DB_PORT', 'SQLSERVER_PORT', 'MSSQL_PORT'], '1433'),
            'database' => $firstEnv(['DB_DATABASE', 'SQLSERVER_DATABASE', 'MSSQL_DATABASE'], 'laravel'),
            'username' => $firstEnv(['DB_USERNAME', 'SQLSERVER_USERNAME', 'MSSQL_USERNAME'], 'root'),
            'password' => $firstEnv(['DB_PASSWORD', 'SQLSERVER_PASSWORD', 'MSSQL_PASSWORD'], ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
