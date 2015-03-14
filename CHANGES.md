@@@WRITE MAILING LIST MESSAGE HERE

- REF: Extract rebuilding of condition and binding sequential values
- FIX: Allow binding of values as part of join() methods. fixes #27
- NEW: Method Select::addTableRef(), still need to test it
- FIX: Fixes #38
- Merge branch 'aliastracking' of github.com:auraphp/Aura.SqlQuery into aliastracking
- REF: Extract statement-building to AbstractQuery::getStatement() method. fixes #30
- FIX: #47, if value for sequential placeholder is a Query, place it as a string inline
- ADD: Sequential-placeholder prefixing
- ADD: bind values from sub-selects, and modify indenting
- ADD: QueryFactory now sets the sequntial bind prefix
- FIX: Remove all line endings from queries to be sure tests will pass on windows and mac. Merge pull request #53 from ksimka/fix-tests-remove-line-endings. Fixed tests for windows
- Merge pull request #50 from auraphp/bindonjoin. Allow binding of values as part of join() methods
- Merge pull request #51 from auraphp/aliastracking. Add table-reference tracking to disallow duplicate references
- Merge pull request #52 from auraphp/bindsubselect. Bind Values From Sub-Selects

- DOC: Readme updates
- DOC: Add CONTRIBUTING.md file.
- DOC: Update license years
