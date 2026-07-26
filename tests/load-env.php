<?php
/**
 * Loads integration-test credentials from a local .env file, so that they do
 * not have to be retyped on every run. The file is optional and git-ignored;
 * copy .env.example to .env to get started.
 *
 * A variable already present in the environment is left alone, which keeps CI
 * and one-off command-line overrides in charge.
 *
 * Shared by the PHPUnit bootstrap and by tests/db-create.php, so that both
 * read the same credentials.
 */
return function (string $file): void {
    if (! is_readable($file)) {
        return;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // strip one layer of matching quotes, so a value containing a space
        // or a '#' can be written the way it is on the command line
        $len = strlen($value);
        if ($len > 1 && $value[0] === $value[$len - 1] && ($value[0] === '"' || $value[0] === "'")) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) === false) {
            putenv("{$name}={$value}");
        }
    }
};
