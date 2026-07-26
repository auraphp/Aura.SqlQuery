<?php
/**
 * Creates the throwaway databases the integration suite expects, for whichever
 * dialects are configured in .env or the environment. Run it once:
 *
 *     composer db-create
 *
 * A dialect with no DSN is skipped. An existing database is left alone; this
 * script only ever creates, never drops. The test suite itself does not do
 * this, so that running the tests never needs CREATE DATABASE rights.
 *
 * The DSN names the database to create, so this connects to the server without
 * it: MySQL with no database selected, PostgreSQL through its 'postgres'
 * maintenance database, SQL Server through 'master'.
 */

$load_env = require __DIR__ . '/load-env.php';
$load_env(dirname(__DIR__) . '/.env');

$dialects = [
    'mysql' => 'DB_MYSQL_',
    'pgsql' => 'DB_PGSQL_',
    'sqlsrv' => 'DB_SQLSRV_',
];

$status = 0;
foreach ($dialects as $dialect => $prefix) {
    $dsn = (string) getenv($prefix . 'DSN');
    if ($dsn === '') {
        echo "{$dialect}: no {$prefix}DSN, skipping\n";
        continue;
    }

    try {
        $status |= createDatabase(
            $dialect,
            $dsn,
            getenv($prefix . 'USER') ?: null,
            getenv($prefix . 'PASS') ?: null
        );
    } catch (Throwable $e) {
        echo "{$dialect}: ERROR {$e->getMessage()}\n";
        $status = 1;
    }
}

exit($status);

/**
 * Creates one dialect's database, and reports what happened.
 */
function createDatabase(string $dialect, string $dsn, ?string $user, ?string $pass): int
{
    list($name, $server_dsn) = splitDsn($dialect, $dsn);

    if ($name === null) {
        echo "{$dialect}: no database name in the DSN, skipping\n";
        return 0;
    }

    // the name goes into DDL, which takes no placeholders, so allow only
    // what an unquoted identifier may contain rather than trying to escape it
    if (! preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        echo "{$dialect}: refusing to create '{$name}'; use letters, digits and underscores\n";
        return 1;
    }

    $pdo = new PDO($server_dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    switch ($dialect) {
        case 'mysql':
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}`");
            break;
        case 'pgsql':
            // PostgreSQL has no IF NOT EXISTS for CREATE DATABASE
            $sth = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
            $sth->execute([$name]);
            if ($sth->fetchColumn() === false) {
                $pdo->exec("CREATE DATABASE \"{$name}\"");
            }
            break;
        case 'sqlsrv':
            $pdo->exec("IF DB_ID('{$name}') IS NULL CREATE DATABASE [{$name}]");
            break;
    }

    echo "{$dialect}: '{$name}' is ready\n";
    return 0;
}

/**
 * Returns the database name in a DSN, and the DSN to reach the server without
 * it, as a two-element list.
 *
 * @return array{0: ?string, 1: string}
 */
function splitDsn(string $dialect, string $dsn): array
{
    list($scheme, $rest) = explode(':', $dsn, 2);

    // 'sqlsrv' spells its parts Server=/Database=; the others use PDO's
    // lower-case host=/dbname=
    $key = $dialect === 'sqlsrv' ? 'database' : 'dbname';

    $name = null;
    $keep = [];
    foreach (explode(';', $rest) as $part) {
        if ($part === '') {
            continue;
        }
        if (strpos($part, '=') === false) {
            $keep[] = $part;
            continue;
        }
        list($part_key, $part_value) = explode('=', $part, 2);
        if (strtolower(trim($part_key)) === $key) {
            $name = trim($part_value);
            continue;
        }
        $keep[] = $part;
    }

    // connect through a database that is always present, where the server
    // requires one
    if ($dialect === 'pgsql') {
        $keep[] = 'dbname=postgres';
    } elseif ($dialect === 'sqlsrv') {
        $keep[] = 'Database=master';
    }

    return [$name, $scheme . ':' . implode(';', $keep)];
}
