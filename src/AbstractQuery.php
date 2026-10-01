<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery;

use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\Common\QuoterInterface;
use Closure;

/**
 *
 * Abstract query object.
 *
 * @package Aura.SqlQuery
 *
 */
abstract class AbstractQuery
{
    /**
     *
     * Data to be bound to the query.
     *
     * @var array<int|string, mixed>
     *
     */
    protected array $bind_values = [];

    /**
     *
     * Which part of the query bound each placeholder name; null means it was
     * bound by hand. Keys match $bind_values.
     *
     * @var array<int|string, string>
     *
     */
    protected array $bind_sources = [];

    /**
     *
     * A second claimant on a name a rendered UNION branch owns, for the case
     * where the active branch asks for the value already bound. Ownership
     * stays with the union, but the name is not free while this clause is
     * still using it. Keys match $bind_sources.
     *
     * @var array<int|string, string>
     *
     */
    protected array $bind_shared = [];

    /**
     *
     * Human-readable names for the $bind_sources values, for error messages.
     *
     * @var array<string, string>
     *
     */
    protected array $bind_source_labels = [
        'col' => 'cols()',
        'cond' => 'a condition',
        'where' => 'a WHERE condition',
        'having' => 'a HAVING condition',
        'join' => 'a JOIN condition',
        'table' => 'a sub-select in the FROM clause',
        'union' => 'a rendered UNION branch',
        'with' => 'a common table expression',
        'conflict' => 'doUpdateCol()',
        'duplicate_key' => 'onDuplicateKeyUpdateCol()',
        'bulk_col' => 'a bulk-insert row',
    ];

    /**
     *
     * The list of WHERE conditions.
     *
     * @var list<string>
     *
     */
    protected array $where = [];

    /**
     *
     * ORDER BY these columns.
     *
     * @var list<string>
     *
     */
    protected array $order_by = [];

    /**
     *
     * The list of flags.
     *
     * @var array<string, true>
     *
     */
    protected array $flags = [];

    /**
     *
     * A helper for quoting identifier names.
     *
     * @var Common\QuoterInterface
     *
     */
    protected Common\QuoterInterface $quoter;

    /**
     *
     * A builder for the query.
     *
     * @var Common\AbstractBuilder
     *
     */
    protected $builder;

    /**
     * @var int
     */
    protected int $inlineCount = 0;

    /**
     *
     * Regex alternatives matching the SQL that spells no placeholder: string
     * literals and comments, in the forms this dialect reads them.
     *
     * A quote inside a literal is written by doubling it, and a comment runs
     * to the end of its line or to the close of its block. Dialects that read
     * more than this -- MySQL, whose backslash escapes the quote after it and
     * whose hash begins a comment -- say so by overriding this; the reading
     * here is the standard one, so a backslash is an ordinary character and a
     * literal ends at the next lone quote whatever precedes it.
     *
     * @return string
     *
     * @see rebuildCondAndBindValues()
     *
     * @see Common\Select::resetAfterRendering()
     *
     */
    protected function getTextPattern(): string
    {
        return "'(?:[^']|'')*+'|--[^\n]*|\/\*.*?\*\/";
    }

    /**
     *
     * Constructor.
     *
     * @param Common\QuoterInterface $quoter A helper for quoting identifier names.
     *
     * @param Common\AbstractBuilder $builder A builder for the query.
     *
     */
    public function __construct(QuoterInterface $quoter, Common\AbstractBuilder $builder)
    {
        $this->quoter = $quoter;
        $this->builder = $builder;
    }

    /**
     *
     * Returns this query object as an SQL statement string.
     *
     * @return string
     *
     */
    public function __toString(): string
    {
        return $this->getStatement();
    }

    /**
     *
     * Returns this query object as an SQL statement string.
     *
     * Left to the subclass because a statement is more than the clauses
     * build() renders: Select writes the union branches above them, and both
     * it and the data-modifying queries write the WITH clause above that.
     *
     * @return string
     *
     */
    abstract public function getStatement(): string;

    /**
     *
     * Builds this query object into a string.
     *
     * @return string
     *
     */
    abstract protected function build(): string;

    /**
     *
     * A regex alternative matching a quoted identifier, in the quoting this
     * dialect writes: backticks on MySQL, brackets on SQL Server, double
     * quotes elsewhere.
     *
     * A colon inside one is part of the name and no placeholder can stand
     * there, so a scan for placeholder names must read past it. The quoting
     * comes from the quoter rather than being spelled here, so that what is
     * read back is what the builder wrote; the closing quote doubled is how a
     * name containing one is written, and the name runs on past it.
     *
     * @return string
     *
     * @see rebuildCondAndBindValues()
     *
     * @see Common\Select::resetAfterRendering()
     *
     */
    protected function getQuotedNamePattern(): string
    {
        $prefix = preg_quote($this->getQuoteNamePrefix(), '/');
        $suffix = preg_quote($this->getQuoteNameSuffix(), '/');

        return "{$prefix}(?:[^{$suffix}]|{$suffix}{$suffix})*+{$suffix}";
    }

    /**
     *
     * Returns the prefix to use when quoting identifier names.
     *
     * @return string
     *
     */
    public function getQuoteNamePrefix(): string
    {
        return $this->quoter->getQuoteNamePrefix();
    }

    /**
     *
     * Returns the suffix to use when quoting identifier names.
     *
     * @return string
     *
     */
    public function getQuoteNameSuffix(): string
    {
        return $this->quoter->getQuoteNameSuffix();
    }

    /**
     *
     * Binds multiple values to placeholders; merges with existing values.
     *
     * @param array<int|string, mixed> $bind_values Values to bind to
     * placeholders.
     *
     * @return $this
     *
     */
    public function bindValues(array $bind_values): static
    {
        // array_merge() renumbers integer keys, which is bad for
        // question-mark placeholders
        foreach ($bind_values as $key => $val) {
            $this->bindValue($key, $val);
        }
        return $this;
    }

    /**
     *
     * Binds a single value to the query.
     *
     * @param int|string $name The placeholder name or number.
     *
     * @param mixed $value The value to bind to the placeholder.
     *
     * @return $this
     *
     */
    public function bindValue(int|string $name, mixed $value): static
    {
        return $this->bindValueFrom($name, $value, null);
    }

    /**
     *
     * Binds a single value, recording which part of the query asked for it.
     *
     * Two different parts of a query claiming the same placeholder name is a
     * mistake: only one value can survive in the flat bind array, so the
     * other is silently discarded and the statement runs with the wrong data.
     * Throw instead of losing it -- even when the two values happen to agree
     * today, since either part may revise its value afterwards and there is
     * nothing to re-check it against.
     *
     * A null source means the caller bound the value by hand, which may
     * always overwrite: rebinding before execution, and reusing a query
     * object with fresh values, are both legitimate.
     *
     * @param int|string $name The placeholder name or number.
     *
     * @param mixed $value The value to bind to the placeholder.
     *
     * @param string|null $source The part of the query binding the value:
     * 'col', 'where', 'having', 'join', 'cond' for a condition with no
     * clause of its own, 'conflict', 'duplicate_key', 'union', 'with', or
     * null when bound by hand.
     *
     * @return $this
     *
     * @throws Exception\LogicException when two different parts of the query
     * claim one placeholder name.
     *
     */
    protected function bindValueFrom(int|string $name, mixed $value, ?string $source): static
    {
        $prior = isset($this->bind_sources[$name])
            ? $this->bind_sources[$name]
            : null;

        // A rendered UNION branch owns its placeholders, and so does a CTE:
        // both are SQL the statement keeps whole, and neither has a clause
        // left to hand its names back to. A later clause filtering on the
        // same value -- one tenant id in the CTE and in the outer WHERE --
        // asks for exactly what is already bound, and nothing is lost by
        // letting it through. Ownership stays with the union or the CTE:
        // were it to pass to the clause, a later resetWhere() would free a
        // name that retained SQL still binds. Record the clause as a second
        // claimant all the same, so that resetUnions() or resetWith() hands
        // the name over to it instead of freeing a name it is still using.
        //
        // A CTE also shares in the other direction, arriving after the clause
        // that bound the name. It is written at the top of the statement
        // whenever the caller reaches with(), so which of the two was called
        // first says nothing about what the statement means, and making the
        // order matter would only be a rule to remember.
        //
        // A union branch keeps the reading it already had, where the branch
        // must be the one holding the name first. Widening that is a change
        // to how union() behaves, wanted or not, and it belongs to union
        // rather than to the clause being added here.
        $prior_owns = in_array($prior, ['union', 'with'], true);
        $source_owns = $source === 'with';

        if (
            ($prior_owns || $source_owns)
            && array_key_exists($name, $this->bind_values)
            && $this->bind_values[$name] === $value
        ) {
            $owner = $prior_owns ? $prior : $source;
            $sharer = $prior_owns ? $source : $prior;

            // a hand-bound value claims nothing, so there is no second
            // claimant to record: null in bind_shared would name a clause
            // that does not exist. Nothing reads it as one today, since
            // removeBindSources() asks isset() and a null entry answers the
            // same as an absent one -- this keeps the list honest rather
            // than fixing a behaviour, and is why no test can tell.
            if ($sharer !== null && $sharer !== $owner) {
                $shared = isset($this->bind_shared[$name])
                    ? $this->bind_shared[$name]
                    : null;
                if ($shared !== null && $shared !== $sharer) {
                    $this->throwCollision($name, $shared, $sharer);
                }

                $this->bind_shared[$name] = $sharer;
            }

            // the owner may be the source arriving now, when a clause held
            // the name first and a CTE has come for it: the CTE is the SQL
            // that outlives the clause, so the claim passes to it and the
            // clause becomes the sharer recorded above.
            $this->bind_sources[$name] = $owner;
            return $this;
        }

        if (
            $source !== null
            && $prior !== null
            && array_key_exists($name, $this->bind_values)
            && (
                $prior !== $source
                || (
                    $this->bind_values[$name] !== $value
                    && !in_array($source, ['col', 'conflict', 'duplicate_key'], true)
                )
            )
        ) {
            $this->throwCollision($name, $prior, $source);
        }

        $this->bind_values[$name] = $value;

        // A hand-bound value overwrites the value but does not claim the
        // name: otherwise binding by hand between two parts of the query
        // would erase the record of who claimed it first, and the collision
        // they would have had goes undetected. Any other source reaching
        // here either claimed the name already or found it free, since a
        // second claimant throws above.
        if ($source !== null) {
            $this->bind_sources[$name] = $source;
        }

        return $this;
    }

    /**
     *
     * Renders a sub-select and takes over the values it has bound, claiming
     * them for the clause the sub-select was rendered into.
     *
     * The names inlineArray() generated are renamed from this query's own
     * sequence on the way in. Every query numbers them from one, so two
     * queries that each bound an array both hold `:__1__`: taken over as
     * they are, the second would collide with the first over a name neither
     * caller wrote and neither can change. Renaming each import keeps them
     * apart however the queries are nested, cloned or combined.
     *
     * The sub-select's own clause labels are deliberately not carried over.
     * They describe parts of a different query: recording them here would
     * have the outer query hold a name against a WHERE it may not even have,
     * which no reset of the outer query can reach -- resetTables() releases
     * the clause the sub-select actually sits in, and would leave the name
     * claimed forever. The message would name that absent clause too, and
     * send the reader looking for it.
     *
     * @param SelectInterface $select The sub-select to render.
     *
     * @param string $source The part of THIS query the sub-select is
     * rendered into.
     *
     * @return string The sub-select statement, with any generated
     * placeholder names renamed.
     *
     */
    protected function importSelect(SelectInterface $select, string $source): string
    {
        $statement = $select->getStatement();

        $renamed = [];
        $values = [];
        foreach ($select->getBindValues() as $name => $value) {
            if ($this->isGeneratedName($name)) {
                $renamed[$name] = $this->nextInlineName();
                $name = $renamed[$name];
            }
            $values[$name] = $value;
        }

        if ($renamed) {
            $statement = (string) preg_replace_callback(
                '/:(__\d+__)(?!\w)/',
                fn (array $m): string => isset($renamed[$m[1]])
                    ? ':' . $renamed[$m[1]]
                    : $m[0],
                $statement
            );
        }

        foreach ($values as $name => $value) {
            $this->bindValueFrom($name, $value, $source);
        }

        return $statement;
    }

    /**
     *
     * Applies a change to this query, putting every property back the way
     * it was if the change throws.
     *
     * A method that binds values usually records part of its work first --
     * the column, the branch, the table reference -- and the collision that
     * ends it comes later. A caller catching that exception would otherwise
     * be left holding a query with half the change in it, whose next
     * statement binds the wrong values or claims names for SQL it does not
     * contain. The arrays are copied on write, so the snapshot is cheap.
     *
     * @param callable(): mixed $change The change to apply.
     *
     * @return $this
     *
     */
    protected function atomically(callable $change): static
    {
        $before = get_object_vars($this);

        try {
            $change();
        } catch (\Throwable $e) {
            foreach ($before as $property => $value) {
                $this->$property = $value;
            }
            throw $e;
        }

        return $this;
    }

    /**
     *
     * Formats a sub-SELECT statement, binding values from a Select object as
     * needed.
     *
     * @param string|SelectInterface $spec A sub-SELECT specification.
     *
     * @param string $indent Indent each line with this string.
     *
     * @param string $source The part of this query the sub-select is being
     * rendered into, which claims the names it binds.
     *
     * @return string The sub-SELECT string.
     *
     */
    protected function subSelect(string|SelectInterface $spec, string $indent, string $source = 'table'): string
    {
        if ($spec instanceof SelectInterface) {
            $spec = $this->importSelect($spec, $source);
        }

        return PHP_EOL . $indent
            . ltrim((string) preg_replace('/^/m', $indent, $spec))
            . PHP_EOL;
    }

    /**
     *
     * Reports two parts of the query claiming one placeholder name.
     *
     * @param int|string $name The placeholder name.
     *
     * @param string $prior The part of the query holding the name.
     *
     * @param string $source The part of the query asking for it as well.
     *
     * @return void
     *
     * @throws Exception\LogicException always.
     *
     */
    protected function throwCollision(int|string $name, string $prior, string $source): void
    {
        $was = isset($this->bind_source_labels[$prior])
            ? $this->bind_source_labels[$prior]
            : $prior;
        $now = isset($this->bind_source_labels[$source])
            ? $this->bind_source_labels[$source]
            : $source;

        // "a WHERE condition ... a WHERE condition" reads like a bug when
        // both halves are the same clause; say "another" instead, taking
        // the article off the label first.
        if ($prior === $source) {
            $now = 'another ' . preg_replace('/^an? /', '', $now);
        }

        // a positional placeholder is bound by number and keeps its `?` in
        // the statement, so quoting it as ':1' would name a token the query
        // does not contain. It is numbered by its offset in the values array,
        // which starts again at zero on every call, so "use a different one"
        // is advice the caller cannot act on: naming them is the only way to
        // keep the two apart.
        $positional = ctype_digit((string) $name);

        $which = $positional
            ? "The positional placeholder {$name}"
            : "The placeholder ':{$name}'";

        $remedy = $positional
            ? "Give the placeholders names instead of '?', so that each one "
            . "carries its own value."
            : "Use a different placeholder for one of them.";

        throw new Exception\LogicException(
            "{$which} is already in use by {$was}, so "
            . "{$now} cannot bind it as well: one value would overwrite "
            . "the other. {$remedy}"
        );
    }

    /**
     *
     * Gets the values to bind to placeholders.
     *
     * @return array<int|string, mixed>
     *
     */
    public function getBindValues(): array
    {
        return $this->bind_values;
    }

    /**
     *
     * Reset all values bound to named placeholders.
     *
     * @return $this
     *
     */
    public function resetBindValues(): static
    {
        $this->bind_values = [];
        $this->bind_sources = [];
        $this->bind_shared = [];
        return $this;
    }

    /**
     *
     * Releases the placeholder names a query source claimed, so they may be
     * claimed again. The bound values themselves are kept: the clause resets
     * have never removed them, and union() depends on that. Generated names
     * are the exception, dropped unless retained SQL still spells them.
     *
     * A name another clause is sharing passes to that clause rather than
     * being released, since it is still in use.
     *
     * @param string $source The source to remove (e.g. 'where', 'having').
     *
     * @return $this
     *
     */
    protected function removeBindSources(string $source): static
    {
        // read only when a generated name is released, and then only once
        $retained = null;

        // drop the record of who claimed the name, but keep the value: the
        // clause resets never removed bound values, and union() depends on
        // that -- it renders the current half to SQL, placeholders and all,
        // then resets, so deleting the values leaves that SQL with tokens
        // nothing can bind.
        foreach ($this->bind_shared as $name => $src) {
            if ($src === $source) {
                unset($this->bind_shared[$name]);
            }
        }

        foreach ($this->bind_sources as $name => $src) {
            if ($src !== $source) {
                continue;
            }
            // a name another clause is still using is not free: hand it over
            // rather than releasing it, or that clause's placeholder could be
            // rebound to a different value with nothing to report the clash.
            if (isset($this->bind_shared[$name])) {
                $this->bind_sources[$name] = $this->bind_shared[$name];
                unset($this->bind_shared[$name]);
                continue;
            }
            unset($this->bind_sources[$name]);

            // A generated name is the exception to keeping the value. Nobody
            // wrote it, so nobody can rebind or reuse it; the next list or `?`
            // gets a fresh one. Left behind, it is a parameter the statement no
            // longer spells, which PDO rejects at execute(). SQL that was
            // already rendered -- a UNION branch -- may still spell it, and
            // then it stays.
            if ($this->isGeneratedName($name)) {
                $retained ??= $this->getRetainedSpelledNames();
                if (! isset($retained[$name])) {
                    unset($this->bind_values[$name]);
                }
            }
        }
        return $this;
    }

    /**
     *
     * Returns the placeholder names spelled by SQL this query has already
     * rendered and kept -- a UNION branch -- which a clause reset must leave
     * bound. A query with no such SQL has none.
     *
     * @return array<string, int> The names, as keys.
     *
     */
    protected function getRetainedSpelledNames(): array
    {
        return [];
    }

    /**
     *
     * Sets or unsets specified flag.
     *
     * @param string $flag Flag to set or unset
     *
     * @param bool $enable Flag status - enabled or not (default true)
     *
     * @return void
     *
     */
    protected function setFlag(string $flag, bool $enable = true): void
    {
        if ($enable) {
            $this->flags[$flag] = true;
        } else {
            unset($this->flags[$flag]);
        }
    }

    /**
     *
     * Returns true if the specified flag was enabled by setFlag().
     *
     * @param string $flag Flag to check
     *
     * @return bool
     *
     */
    protected function hasFlag(string $flag): bool
    {
        return isset($this->flags[$flag]);
    }

    /**
     *
     * Reset all query flags.
     *
     * @return $this
     *
     */
    public function resetFlags(): static
    {
        $this->flags = [];
        return $this;
    }

    /**
     *
     * Adds conditions and binds values to a clause.
     *
     * @param string $clause The clause to work with, typically 'where' or
     * 'having'.
     *
     * @param string $andor Add the condition using this operator, typically
     * 'AND' or 'OR'.
     *
     * @param string|Closure $cond The WHERE condition.
     *
     * @param array<int|string, mixed> $bind arguments to bind to
     * placeholders
     *
     * @return void
     *
     */
    protected function addClauseCondWithBind(string $clause, string $andor, string|Closure $cond, array $bind): void
    {
        $this->atomically(function () use ($clause, $andor, $cond, $bind): void {
            $this->addClauseCond($clause, $andor, $cond, $bind);
        });
    }

    /**
     *
     * Does the work of addClauseCondWithBind(), which undoes it on failure.
     *
     * @param string $clause The clause to work with.
     *
     * @param string $andor 'AND' or 'OR'.
     *
     * @param string|Closure $cond The condition.
     *
     * @param array<int|string, mixed> $bind Values to bind.
     *
     * @return void
     *
     */
    private function addClauseCond(string $clause, string $andor, string|Closure $cond, array $bind): void
    {
        if ($cond instanceof Closure) {
            $this->addClauseCondClosure($clause, $andor, $cond);
            foreach ($bind as $key => $val) {
                $this->bindValueFrom($key, $val, $clause);
            }
            return;
        }

        $cond = $this->quoter->quoteNamesIn($cond);
        $cond = $this->rebuildCondAndBindValues($cond, $bind, $clause);

        $clause =& $this->$clause;
        if ($clause) {
            $clause[] = "$andor $cond";
        } else {
            $clause[] = $cond;
        }
    }

    /**
     *
     * Adds to a clause through a closure, enclosing within parentheses.
     *
     * @param string $clause The clause to work with, typically 'where' or
     * 'having'.
     *
     * @param string $andor Add the condition using this operator, typically
     * 'AND' or 'OR'.
     *
     * @param callable $closure The closure that adds to the clause.
     *
     * @return void
     *
     */
    protected function addClauseCondClosure(string $clause, string $andor, callable $closure): void
    {
        // retain the prior set of conditions, and temporarily reset the clause
        // for the closure to work with (otherwise there will be an extraneous
        // opening AND/OR keyword)
        $set = $this->$clause;
        $this->$clause = [];

        // invoke the closure, which will re-populate the $this->$clause
        $closure($this);

        // are there new clause elements? PHPStan does not model the closure
        // above repopulating the clause, so it still sees the empty array
        // assigned before the call: this test reads as always true to it, and
        // everything after it as unreachable.
        /** @phpstan-ignore booleanNot.alwaysTrue */
        if (! $this->$clause) {
            // no: restore the old ones, and done
            $this->$clause = $set;
            return;
        }

        // append an opening parenthesis to the prior set of conditions,
        // with AND/OR as needed ...
        /** @phpstan-ignore deadCode.unreachable */
        if ($set) {
            $set[] = "{$andor} (";
        } else {
            $set[] = "(";
        }

        // append the new conditions to the set, with indenting
        foreach ($this->$clause as $cond) {
            $set[] = "    {$cond}";
        }
        $set[] = ")";

        // ... then put the full set of conditions back into $this->$clause
        $this->$clause = $set;
    }

    /**
     *
     * Rebuilds a condition string, replacing sequential placeholders with
     * named placeholders, and binding the sequential values to the named
     * placeholders.
     *
     * @param string $cond The condition with sequential placeholders.
     *
     * @param array<int|string, mixed> $bind_values The values to bind to the
     * sequential placeholders under their named versions.
     *
     * @param string $clause The source clause name.
     *
     * @return string The rebuilt condition string.
     *
     */
    protected function rebuildCondAndBindValues(string $cond, array $bind_values, string $clause = 'cond'): string
    {
        // replacements for named placeholders, and for positional ones by
        // their order among the positional values -- not by key, and not by
        // their offset among all the values, since a named value in between
        // takes up no `?`
        $named = [];
        $positional = [];
        $position = 0;

        // one pass, in the order the values were given, so that generated
        // names are numbered in that order -- a sub-select's own names come
        // where the sub-select does, not after every other value
        foreach ($bind_values as $key => $val) {
            $slot = is_int($key) ? $position++ : $key;

            if ($val instanceof SelectInterface) {
                $text = $this->importSelect($val, $clause);
            } elseif (is_array($val)) {
                $text = $this->inlineArray($val, $clause);
            } elseif (is_int($key)) {
                // a value for a `?` is bound under a generated name as well,
                // so the statement never mixes `?` with named placeholders --
                // which plain PDO rejects on MySQL and PostgreSQL -- and two
                // `?` from separate calls do not both claim the number 0
                $name = $this->nextInlineName();
                $this->bindValueFrom($name, $val, $clause);
                $text = ":{$name}";
            } else {
                $this->bindValueFrom($key, $val, $clause);
                continue;
            }

            if (is_int($slot)) {
                $positional[$slot] = $text;
            } else {
                $named[$slot] = $text;
            }
        }

        if (! $named && ! $positional) {
            return $cond;
        }

        // One pass over the condition, so that nothing written in is read
        // again: a sub-select spelling `:id` of its own, or a list of
        // generated names, is not a placeholder of this condition. String
        // literals and quoted identifiers are passed over whole, so a `?` or
        // a `:name` inside one is left as written. A placeholder is matched
        // only as a whole name (`:id` is not the start of `:id_2`), and not
        // after a second colon, which is a PostgreSQL cast (`x::int`). A
        // doubled `??` is PDO's escape for a literal `?`, such as the
        // PostgreSQL JSON operator, and is not a placeholder either.
        //
        // String literals and comments are read the way this dialect reads
        // them -- getTextPattern() -- since they differ: on MySQL a backslash
        // escapes a quote and `--` begins a comment only before whitespace,
        // so `a--?` there is subtraction and a placeholder. Anything in
        // double quotes or backticks is passed over as well, whichever the
        // dialect makes of it, since neither an identifier nor a string can
        // hold a placeholder.
        $seen = 0;
        $cond = (string) preg_replace_callback(
            "/{$this->getQuotedNamePattern()}|{$this->getTextPattern()}"
            . '|"(?:[^"\\\\]|\\\\.)*"|`[^`]*`|(?<!:):(?<name>\w+)|\?\?|\?/s',
            function (array $m) use ($named, $positional, &$seen): string {
                if ($m[0] === '?') {
                    $slot = $seen++;
                    return $positional[$slot] ?? '?';
                }

                if (isset($m['name'], $named[$m['name']])) {
                    return $named[$m['name']];
                }

                return $m[0];
            },
            $cond
        );

        // values for `?` have to match the `?` one for one: an extra value
        // would stay bound to nothing, and a `?` left without one would be
        // the only positional placeholder in an otherwise named statement
        if ($positional && count($positional) !== $seen) {
            throw new Exception\InvalidArgumentException(
                'The condition has ' . $seen . " '?' placeholder(s), but "
                . count($positional) . ' value(s) were given for them.'
            );
        }

        return $cond;
    }

    /**
     *
     * Binds each value of an array under a generated name, and returns the
     * placeholder list to write in place of the array.
     *
     * @param array<array-key, mixed> $array The values to bind.
     *
     * @param string $source The clause the list is written into, which
     * claims the names -- and whose reset releases them.
     *
     * @return string The comma-separated placeholder names.
     *
     */
    protected function inlineArray(array $array, string $source = 'cond'): string
    {
        $keys = [];
        foreach ($array as $val) {
            $key = $this->nextInlineName();
            $this->bindValueFrom($key, $val, $source);
            $keys[] = ":{$key}";
        }
        return implode(', ', $keys);
    }

    /**
     *
     * Returns the placeholder name for a column's value.
     *
     * A placeholder name is letters, digits and underscores, so the column
     * name is used as it is when it can be; a qualified one such as `t.a`
     * would otherwise be read by PDO as `:t` followed by `.a`.
     *
     * @param string $col The column name.
     *
     * @return string
     *
     */
    protected function placeholderFor(string $col): string
    {
        return (string) preg_replace('/\W/', '_', $col);
    }

    /**
     *
     * Returns the next name in this query's sequence of generated
     * placeholder names.
     *
     * @return string
     *
     */
    protected function nextInlineName(): string
    {
        $this->inlineCount++;
        return "__{$this->inlineCount}__";
    }

    /**
     *
     * Is this a name nextInlineName() generated, rather than one a caller
     * wrote?
     *
     * @param int|string $name The placeholder name.
     *
     * @return bool
     *
     */
    protected function isGeneratedName(int|string $name): bool
    {
        return is_string($name) && preg_match('/^__\d+__$/', $name) === 1;
    }

    /**
     *
     * Adds a column order to the query.
     *
     * @param array<array-key, string> $spec The columns and direction to
     * order by.
     *
     * @return $this
     *
     */
    protected function addOrderBy(array $spec): static
    {
        foreach ($spec as $col) {
            $this->order_by[] = $this->quoter->quoteNamesIn($col);
        }
        return $this;
    }
}
