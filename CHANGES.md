Initial 2.0 stable release.

- The package has been renamed from Sql_Query to SqlQuery, in line with the new Aura naming standards.

- Now compatible with PHP 5.3!

- Refactored traits into interfaces (thanks @mindplay-dk).

- Refactored the internal build process (thanks again @mindplay-dk).

- Added Select::leftJoin()/innerJoin() methods (thanks @stanlemon).

- Methods bindValue() and bindValues() are now fluent (thanks @karikt).

- Select now throws an exception when there are no columns selected.

- In joins, the condition type (ON or USING) may now be part of the condition.

- Extracted new class, Quoter, for quoting identifer names.

- Extracted new class, AbstractDmlQuery, for Insert/Update/Delete queries.

- Select::cols() now accepts `colname => alias` pairs mixed in with sequential colname values.

- Added functionality to map last-insert-id names to alternative sequence names, esp. for Postgres and inherited/extended tables. Cf. QueryFactory::setLastInsertIdNames() and Insert::setLastInsertIdNames().
