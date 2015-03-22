This release incorporates two feature additions and one fix.

- ADD: The _Insert_ objects now support multiple-row inserts with the new `addRow()` and `addRows()` methods.

- ADD: The MySQL _Insert_ object now supports `ON DUPLICATE KEY UPDATE` functionality with the new `onDuplicateKeyUpdate*()` methods.

- FIX: The _Select_ methods regarding paging now interact better with LIMIT and OFFSET; in particular, both `setPaging()` now re-calculates the LIMIT and OFFSET values.
