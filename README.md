# Aura.SqlQuery

Provides query builders for MySQL, Postgres, SQLite, and Microsoft SQL Server.
These builders are independent of any particular database connection library,
although [PDO](http://php.net/PDO) in general is recommended.

## Installation and Autoloading

This package is installable and PSR-4 autoloadable via Composer as
[aura/sqlquery][]:

```
composer require aura/sqlquery
```

While 7.0 is in beta, ask for the pre-release explicitly with
`composer require aura/sqlquery:^7.0@beta`.

Alternatively, [download a release][], or clone this repository, then map
the `Aura\SqlQuery\` namespace to the package `src/` directory.


## Dependencies

This package requires PHP 8.4 or later; it has been tested on PHP 8.4 and 8.5. We recommend using the latest available version of PHP as a matter of principle.

Aura library packages may sometimes depend on external interfaces, but never on
external implementations. This allows compliance with community standards
without compromising flexibility. For specifics, please examine the package
[composer.json][] file.

## Quality

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/auraphp/Aura.SqlQuery/badges/quality-score.png?b=7.x)](https://scrutinizer-ci.com/g/auraphp/Aura.SqlQuery/)
[![codecov](https://codecov.io/gh/auraphp/Aura.SqlQuery/branch/7.x/graph/badge.svg?token=UASDouLxyc)](https://codecov.io/gh/auraphp/Aura.SqlQuery)
[![Continuous Integration](https://github.com/auraphp/Aura.SqlQuery/actions/workflows/continuous-integration.yml/badge.svg?branch=7.x)](https://github.com/auraphp/Aura.SqlQuery/actions/workflows/continuous-integration.yml)

This project adheres to [Semantic Versioning](http://semver.org/).

To run the unit tests at the command line, issue `composer install` and then
`./vendor/bin/phpunit` at the package root. This requires [Composer][] to be
available as `composer`.

This package attempts to comply with [PSR-1][], [PSR-4][], and [PSR-12][]. If
you notice compliance oversights, please send a patch via pull request.

## Community

To ask questions, provide feedback, or otherwise communicate with other Aura
users, please join our [Google Group][] or follow [@auraphp][].

## Documentation

This package is fully documented [here](./docs/index.md).

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-12]: https://www.php-fig.org/psr/psr-12/
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md
[Composer]: http://getcomposer.org/
[PHPUnit]: http://phpunit.de/
[Google Group]: http://groups.google.com/group/auraphp
[@auraphp]: http://twitter.com/auraphp
[download a release]: https://github.com/auraphp/Aura.SqlQuery/releases
[aura/sqlquery]: https://packagist.org/packages/aura/sqlquery
[composer.json]: ./composer.json
