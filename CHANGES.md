- [DOC] Numerous docblock and README updates.

- [ADD] Add various `Select::reset*()` methods. Fixes #84, #95, #94, #91.

- [FIX] On SELECT, allow OFFSET even when LIMIT not specified. Fixes #88.

- [FIX] On SELECT, allow `join*()` before `from*()`. Joins-before-from are added
  to the first from. If no from is ever added, the joins will never be built
  into the statement. Fixes #69, #90.

