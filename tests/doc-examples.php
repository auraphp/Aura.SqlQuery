<?php
/**
 *
 * Tells the shared doc-example checker how to run this package's examples.
 *
 *     php ../scripts/verify-doc-examples.php .
 *
 * Each dialect page is checked against a QueryFactory for that dialect, so the
 * SQL printed under a `php` block has to be the SQL the builder really emits.
 * See CONTRIBUTING.md.
 *
 */

use Aura\SqlQuery\QueryFactory;

return [
    'bootstrap' => __DIR__ . '/../autoload.php',

    'docs' => __DIR__ . '/../docs',

    /**
     * The examples open with `$queryFactory->newSelect()` and friends, so the
     * factory has to exist before one is evaluated. The dialect comes from the
     * page name; docs/other.md and the like fall back to 'common'.
     */
    'context' => function (string $file): array {
        $page = basename($file, '.md');
        $db_types = ['mysql', 'pgsql', 'sqlite', 'sqlsrv'];

        return [
            'queryFactory' => new QueryFactory(
                in_array($page, $db_types, true) ? $page : 'common'
            ),
        ];
    },

    /**
     * An example builds a query into $select, $insert, $update or $delete;
     * anything else is prose-only and has no SQL to compare.
     */
    'render' => function (array $vars): ?string {
        foreach (['select', 'insert', 'update', 'delete'] as $name) {
            if (isset($vars[$name]) && $vars[$name] instanceof Aura\SqlQuery\QueryInterface) {
                return $vars[$name]->getStatement();
            }
        }
        return null;
    },
];
