- [DOC] Numerous docblock and README updates.

- [ADD] Add various `Select::reset*()` methods. Fixes #84, #95, #94, #91.

- [FIX] On SELECT, allow OFFSET even when LIMIT not specified. Fixes #88.

- [FIX] On SELECT, allow `join*()` before `from*()`. Joins-before-from are added
  to the first from. If no from is ever added, the joins will never be built
  into the statement. Fixes #69, #90.

- [BRK] Bumped the minimum version to PHP 5.3.9 (vs 5.3.0). Fixes #74. This is
  to address a language-level bug in PHP. Technically I think this is a BC
  break, but I hope it is understandable, given that PHP 5.3.x is end-of-life,
  and that Aura.SqlQuery itself simply will not operate on versions earlier
  than that. Updated README to reflect the version requirement.

