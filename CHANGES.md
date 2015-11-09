- (DOC) Docblock and README updates; in particular, add an `@method getStatement()` to the QueryInterface for IDE auto-completion.

- (ADD) Select::hasCols() reports if there are any columsn in the Select.

- (ADD) Select::getCols() gets the existing columns in the Select.

- (ADD) Select::removeCol() removes a previously-added column.

- (FIX) Select::reset() now properly resets the table refs for a UNION.

- (FIX) Select::forUpdate() is now fluent.
