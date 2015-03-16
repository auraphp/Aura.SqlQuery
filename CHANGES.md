This release has several new features.

1. The various `join()` methods now have an extra `$bind` param that allows you to bind values to ?-placeholders in the condition, just as with `where()` and `having()`.

2. The _Select_ class now tracks table references internally, and will throw an exception if you try to use the same table name or alias more than once.

3. The method `getStatement()` has been added to all queries, to allow you to get the text of the statement being built. Among other things, this is to avoid exception-related blowups related to PHP's string casting.

4. When binding a value to a sequential placeholder in `where()`, `having()`, etc, the _Select_ class now examind the value to see if it is a query object. If so, it converts the object to a string and replaces the ?-placeholder inline with the string instead of attempting to bind it proper. It also binds the existing sequential placholder values into the current _Select_ in a non-conflicting fashion. (Previously, no binding from the sub-select took place at all.)

5. In `fromSubSelect()` and `joinSubSelect()`, the _Select_ class now binds the sub-select object sequential values to the current _Select_ in a non-conflicting fashion.  (Previously, no binding from the sub-select took place at all.)

The change log follows:

- REF: Extract rebuilding of condition and binding sequential values.

- FIX: Allow binding of values as part of join() methods. Fixes #27.

- NEW: Method Select::addTableRef(), to track table references and prevent double-use of aliases. Fixes #38.

- REF: Extract statement-building to AbstractQuery::getStatement() method. Fixes #30.

- FIX: #47, if value for sequential placeholder is a Query, place it as a string inline

- ADD: Sequential-placeholder prefixing

- ADD: bind values from sub-selects, and modify indenting

- ADD: QueryFactory now sets the sequntial bind prefix

- FIX: Fix line endings in queries to be sure tests will pass on windows and mac. Merge pull request #53 from ksimka/fix-tests-remove-line-endings: Fixed tests for windows.

- Merge pull request #50 from auraphp/bindonjoin: Allow binding of values as part of join() methods.

- Merge pull request #51 from auraphp/aliastracking: Add table-reference tracking to disallow duplicate references.

- Merge pull request #52 from auraphp/bindsubselect. Bind Values From Sub-Selects.

- DOC: Update documentation and support files.
