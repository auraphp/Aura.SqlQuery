<?php
namespace Aura\SqlQuery;

use PHPUnit\Framework\TestCase;

/**
 *
 * Two different parts of one query must never claim the same placeholder
 * name: only one value survives the flat bind array, so the other is
 * silently discarded and the statement runs with the wrong data. See #238.
 *
 */
class CollisionTest extends TestCase
{
    protected $query_factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->query_factory = new QueryFactory('common');
    }

    protected function newFactory($db_type)
    {
        return new QueryFactory($db_type);
    }

    public function testColsAndWhereCollide()
    {
        $update = $this->query_factory->newUpdate();
        $update->table('t1')->cols(['id' => 1]);

        $this->expectException(Exception\LogicException::class);
        $update->where('id = :id', ['id' => 2]);
    }

    public function testDoUpdateColCollidesWithCols()
    {
        $insert = $this->newFactory('pgsql')->newInsert();
        $insert
            ->into('t1')
            ->cols(['status__on_conflict' => 'from cols'])
            ->onConflict('id');

        $this->expectException(Exception\LogicException::class);
        $insert->doUpdateCol('status', 'from do update');
    }

    public function testOnDuplicateKeyUpdateColCollidesWithCols()
    {
        $insert = $this->newFactory('mysql')->newInsert();
        $insert
            ->into('t1')
            ->cols(['status__on_duplicate_key' => 'from cols']);

        $this->expectException(Exception\LogicException::class);
        $insert->onDuplicateKeyUpdateCol('status', 'from on duplicate key');
    }

    /**
     *
     * The message has to name the placeholder and both parts fighting over
     * it, or it does not say enough to act on. Asserted through a catch
     * rather than expectExceptionMessage(), which replaces its expectation
     * on each call instead of accumulating -- so consecutive calls would
     * check only the last string and quietly drop the rest.
     *
     */
    public function testCollisionMessageNamesBothSources()
    {
        $update = $this->query_factory->newUpdate();
        $update->table('t1')->cols(['id' => 1]);

        try {
            $update->where('id = :id', ['id' => 2]);
            $this->fail('Expected a collision on the :id placeholder.');
        } catch (Exception\LogicException $e) {
            $message = $e->getMessage();
            $this->assertStringContainsString("':id'", $message);
            $this->assertStringContainsString('cols()', $message);
            $this->assertStringContainsString('WHERE condition', $message);
        }
    }

    /**
     *
     * Agreeing on the value today is not a reason to share the name: either
     * part may revise its value afterwards, and nothing re-checks the pair.
     * Sharing is rejected when it appears, not when it starts to hurt.
     *
     */
    public function testSameValueFromTwoSourcesStillCollides()
    {
        $update = $this->query_factory->newUpdate();
        $update->table('t1')->cols(['id' => 1]);

        $this->expectException(Exception\LogicException::class);
        $update->where('id = :id', ['id' => 1]);
    }

    /**
     *
     * Two separate where() calls are two separate parts of the query, even
     * though they land in the same clause: neither is revising the other's
     * value, so sharing a name loses one of them. Conditions carry the
     * clause as their source precisely so this is caught.
     *
     */
    public function testTwoConditionsCannotShareAPlaceholder()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('t1')->where('a = :id', ['id' => 1]);

        $this->expectException(Exception\LogicException::class);
        $select->where('b = :id', ['id' => 2]);
    }

    public function testWhereAndHavingCannotShareAPlaceholder()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['a'])->from('t1')->where('a = :x', ['x' => 1]);

        $this->expectException(Exception\LogicException::class);
        $select->having('b = :x', ['x' => 2]);
    }

    public function testJoinAndWhereCannotShareAPlaceholder()
    {
        $select = $this->query_factory->newSelect();
        $select
            ->cols(['a'])
            ->from('t1')
            ->join('LEFT', 't2', 't2.id = t1.id AND t2.status = :x', ['x' => 1]);

        $this->expectException(Exception\LogicException::class);
        $select->where('b = :x', ['x' => 2]);
    }

    public function testTwoJoinConditionsCannotShareAPlaceholder()
    {
        $select = $this->query_factory->newSelect();
        $select
            ->cols(['a'])
            ->from('t1')
            ->join('LEFT', 't2', 't2.status = :x', ['x' => 1]);

        $this->expectException(Exception\LogicException::class);
        $select->join('LEFT', 't3', 't3.status = :x', ['x' => 2]);
    }

    public function testJoinSubSelectConditionCannotShareAPlaceholder()
    {
        $subSelect = $this->query_factory->newSelect();
        $subSelect->cols(['id'])->from('users');

        $select = $this->query_factory->newSelect();
        $select
            ->cols(['a'])
            ->from('t1')
            ->where('b = :x', ['x' => 1]);

        $this->expectException(Exception\LogicException::class);
        $select->joinSubSelect('LEFT', $subSelect, 'sub', 'sub.status = :x', ['x' => 2]);
    }

    public function testRebindingByHandIsAllowed()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('t1')->where('id = :id', ['id' => 1]);

        // changing the value before execution is legitimate
        $select->bindValue('id', 2);
        $this->assertSame(['id' => 2], $select->getBindValues());

        // and so is reusing the query object with a fresh set of values
        $select->bindValues(['id' => 3]);
        $this->assertSame(['id' => 3], $select->getBindValues());
    }

    /**
     *
     * Binding by hand overwrites the value but must not take ownership of the
     * name. If it did, a hand bind sitting between cols() and where() would
     * erase the record of which part claimed the placeholder first, and the
     * collision would go undetected again.
     *
     */
    public function testHandBindDoesNotLaunderTheSource()
    {
        $update = $this->query_factory->newUpdate();
        $update->table('orders')->cols(['status' => 'shipped']);
        $update->bindValue('status', 'delivered');

        $this->expectException(Exception\LogicException::class);
        $update->where('status = :status', ['status' => 'pending']);
    }

    public function testHandBindStillOverwritesTheValue()
    {
        $update = $this->query_factory->newUpdate();
        $update->table('orders')->cols(['status' => 'shipped']);
        $update->bindValue('status', 'delivered');

        $this->assertSame(['status' => 'delivered'], $update->getBindValues());
    }

    public function testHandBoundValueMayBeClaimedByCols()
    {
        $update = $this->query_factory->newUpdate();
        $update->table('t1')->bindValue('id', 1);

        // a null source never blocks a later bind
        $update->cols(['id' => 2]);
        $this->assertSame(['id' => 2], $update->getBindValues());
    }

    /**
     *
     * The docs promise the builder methods "may be called multiple times", so
     * one part of the query revising its own placeholder is not a collision.
     * Only two *different* parts claiming one name is.
     *
     */
    public function testOnePartMayRebindItsOwnPlaceholder()
    {
        $update = $this->query_factory->newUpdate();
        $update->table('orders')->cols(['status' => 'first']);
        $update->cols(['status' => 'second']);

        $this->assertSame(['status' => 'second'], $update->getBindValues());
    }

    /**
     *
     * The sequence that made sharing on an equal value unsafe: the two parts
     * agree, then one of them revises its value, and the pair silently
     * disagrees with nothing left to catch it. Rejecting the share up front
     * removes the sequence entirely.
     *
     */
    public function testAgreeThenDivergeCannotHappen()
    {
        $update = $this->query_factory->newUpdate();
        $update->table('orders')->cols(['status' => 'first']);

        try {
            $update->where('status = :status', ['status' => 'first']);
            $this->fail('Expected the shared placeholder to be rejected.');
        } catch (Exception\LogicException $e) {
            // the condition never took the name, so cols() still owns it and
            // may revise its own value
            $update->cols(['status' => 'second']);
            $this->assertSame(
                ['status' => 'second'],
                $update->getBindValues()
            );
        }
    }

    public function testBulkInsertDoesNotCollideWithItself()
    {
        $insert = $this->query_factory->newInsert();
        $insert
            ->into('t1')
            ->cols(['c1' => 'v1-0'])
            ->addRow(['c1' => 'v1-1'])
            ->addRow(['c1' => 'v1-2']);

        $expect = ['c1_0' => 'v1-0', 'c1_1' => 'v1-1', 'c1_2' => 'v1-2'];
        $insert->getStatement();
        $this->assertSame($expect, $insert->getBindValues());
    }

    /**
     *
     * finishRow() empties $bind_values once a row is banked. If the source
     * map is not emptied with it, the stale entry makes a later bind of the
     * same name look like a collision when the value it would have clobbered
     * is already safely stored under its row-numbered name.
     *
     */
    public function testFinishedRowDoesNotLeaveAStaleSource()
    {
        $insert = $this->newFactory('pgsql')->newInsert();
        $insert
            ->into('t1')
            ->cols(['status__on_conflict' => 'banked into the row'])
            ->addRow();

        // the name is free again, so this is not a collision
        $insert->onConflict('id')->doUpdateCol('status', 'from do update');

        $bind_values = $insert->getBindValues();
        $this->assertSame('banked into the row', $bind_values['status__on_conflict_0']);
        $this->assertSame('from do update', $bind_values['status__on_conflict']);
    }

    /**
     *
     * Belt and braces for the check itself: a source recorded for a name that
     * has no value cannot be a collision, because there is no value left to
     * discard. finishRow() and resetBindValues() both keep the two arrays in
     * step, so this state should never arise -- but if it ever did, reading
     * the missing value would raise an undefined-key warning and then throw a
     * collision that is not real.
     *
     */
    public function testSourceWithoutAValueIsNotACollision()
    {
        $update = $this->query_factory->newUpdate();
        $update->table('t1')->cols(['id' => 1]);

        // force the desync the guard exists for
        $property = new \ReflectionProperty($update, 'bind_values');
        $property->setAccessible(true);
        $property->setValue($update, []);

        $update->where('id = :id', ['id' => 2]);
        $this->assertSame(['id' => 2], $update->getBindValues());
    }

    public function testResetBindValuesClearsTheSources()
    {
        $update = $this->query_factory->newUpdate();
        $update->table('t1')->cols(['id' => 1]);
        $update->resetBindValues();

        // with the source forgotten there is nothing left to collide with
        $update->where('id = :id', ['id' => 2]);
        $this->assertSame(['id' => 2], $update->getBindValues());
    }

    public function testClosureBindsDoNotBypassCollision()
    {
        $update = $this->query_factory->newUpdate();
        $update->table('orders')->cols(['status' => 'shipped']);

        $this->expectException(Exception\LogicException::class);
        $update->where(function($query) {}, ['status' => 'pending']);
    }

    public function testSubselectBindsDoNotBypassCollision()
    {
        $subSelect = $this->query_factory->newSelect();
        $subSelect->cols(['id'])->from('users')->where('status = :status', ['status' => 'active']);

        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('orders')
               ->where('status = :status', ['status' => 'pending']);

        $this->expectException(Exception\LogicException::class);
        $select->where('user_id IN (:sub)', ['sub' => $subSelect]);
    }

    public function testFromSubSelectBindsDoNotBypassCollision()
    {
        $subSelect = $this->query_factory->newSelect();
        $subSelect->cols(['id'])->from('users')->where('status = :status', ['status' => 'active']);

        $select = $this->query_factory->newSelect();
        $select->cols(['*'])
               ->fromSubSelect($subSelect, 'sub');

        $this->expectException(Exception\LogicException::class);
        $select->where('status = :status', ['status' => 'pending']);
    }

    /**
     *
     * A sub-select's names are claimed by the clause it was rendered into,
     * not by whichever of its own clauses bound them: the outer query has no
     * WHERE of its own here, and the sub-select's WHERE is not a part of the
     * outer query that any reset could reach. resetTables() discards the
     * sub-select's SQL, so its names go with it.
     *
     */
    public function testJoinSubSelectNamesAreReleasedByResetTables()
    {
        $subSelect = $this->query_factory->newSelect();
        $subSelect->cols(['id'])->from('u')->where('s = :s', ['s' => 'a']);

        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('t')
               ->joinSubSelect('INNER', $subSelect, 'sub', 'sub.id = t.id');
        $select->resetTables();

        $select->from('t')->join('INNER', 'u', 'u.s = :s', ['s' => 'b']);
        $this->assertSame(['s' => 'b'], $select->getBindValues());
    }

    public function testFromSubSelectNamesAreReleasedByResetTables()
    {
        $subSelect = $this->query_factory->newSelect();
        $subSelect->cols(['id'])->from('u')->where('s = :s', ['s' => 'a']);

        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->fromSubSelect($subSelect, 'sub');
        $select->resetTables();

        $select->from('t')->where('s = :s', ['s' => 'b']);
        $this->assertSame(['s' => 'b'], $select->getBindValues());
    }

    /**
     *
     * The other half of claiming for the rendered-into clause: resetWhere()
     * must not free a name a FROM sub-select is binding. It used to, because
     * the name was inherited as the sub-select's own 'where', which left the
     * rendered sub-select running on a later clause's value.
     *
     */
    public function testResetWhereCannotFreeAFromSubSelectsPlaceholder()
    {
        $subSelect = $this->query_factory->newSelect();
        $subSelect->cols(['id'])->from('u')->where('status = :status', ['status' => 'active']);

        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->fromSubSelect($subSelect, 'sub');
        $select->resetWhere();

        $this->expectException(Exception\LogicException::class);
        $select->where('x = :status', ['status' => 'CLOBBERED']);
    }

    /**
     *
     * A LATERAL JOIN sub-select is joined, not selected from, so it must say
     * so: resetTables() releases either way, but the message is the only
     * thing telling the reader which part of the query to go and look at.
     *
     */
    public function testLateralJoinSubSelectIsNamedAsAJoin()
    {
        $subSelect = $this->newFactory('pgsql')->newSelect();
        $subSelect->cols(['id'])->from('u')->where('s = :s', ['s' => 'a']);

        $select = $this->newFactory('pgsql')->newSelect();
        $select->cols(['*'])->from('t')
               ->lateralJoinSubSelect('INNER', $subSelect, 'sub', 'sub.id = t.id');

        try {
            $select->where('s = :s', ['s' => 'b']);
            $this->fail('Expected a collision on the :s placeholder.');
        } catch (Exception\LogicException $e) {
            $this->assertStringContainsString('JOIN', $e->getMessage());
        }
    }

    /**
     *
     * The message has to name a part of the query the reader can find. A
     * sub-select's internal WHERE is not one: the outer query never had a
     * WHERE clause to look at.
     *
     */
    public function testSubSelectCollisionNamesTheOuterClause()
    {
        $subSelect = $this->query_factory->newSelect();
        $subSelect->cols(['id'])->from('u')->where('s = :s', ['s' => 'a']);

        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->fromSubSelect($subSelect, 'sub');

        try {
            $select->where('s = :s', ['s' => 'b']);
            $this->fail('Expected a collision on the :s placeholder.');
        } catch (Exception\LogicException $e) {
            $message = $e->getMessage();
            $this->assertStringContainsString('sub-select', $message);
            $this->assertStringNotContainsString('already in use by a WHERE condition', $message);
        }
    }

    public function testSameSourceDifferentValueCollides()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('orders')
               ->where('status = :status', ['status' => 'pending']);

        $this->expectException(Exception\LogicException::class);
        $select->where('status = :status', ['status' => 'active']);
    }

    /**
     *
     * Resetting a clause frees the names it claimed, so the same placeholder
     * may be used again afterwards. The *values* deliberately survive the
     * reset, as they always have: union() renders the current half to SQL --
     * placeholders and all -- and then resets, so dropping them would leave
     * that SQL with tokens nothing can bind.
     *
     */
    public function testResetWhereFreesItsPlaceholderNames()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('orders')
               ->where('status = :status', ['status' => 'pending']);

        $select->resetWhere();

        // no collision, because the WHERE clause no longer claims the name
        $select->where('status = :status', ['status' => 'active']);
        $this->assertSame(['status' => 'active'], $select->getBindValues());
    }

    public function testResetHavingFreesItsPlaceholderNames()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('orders')
               ->having('status = :status', ['status' => 'pending']);

        $select->resetHaving();

        $select->having('status = :status', ['status' => 'active']);
        $this->assertSame(['status' => 'active'], $select->getBindValues());
    }

    /**
     *
     * A clause reset frees the name but keeps the value, so a name reset
     * before the union is bound without appearing anywhere in the rendered
     * branch. Nothing in that SQL can bind it, so the union has no claim to
     * stake, and the next branch must be free to use the name for its own
     * value.
     *
     */
    public function testANameResetBeforeTheUnionIsFreeInTheNextBranch()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')
               ->where('id = :id', ['id' => 1]);

        $select->resetWhere();
        $select->where('status = :status', ['status' => 'pending']);
        $select->union()->cols(['*'])->from('b');

        // the rendered branch spells :status, but never :id
        $statement = $select->getStatement();
        $this->assertStringContainsString('status = :status', $statement);
        $this->assertStringNotContainsString(':id', $statement);

        // so the next branch may claim :id for a value of its own
        $select->where('id = :id', ['id' => 2]);
        $this->assertSame(
            ['id' => 2, 'status' => 'pending'],
            $select->getBindValues()
        );
    }

    /**
     *
     * union() renders the current branch to SQL and retains it, placeholders
     * and all, so that SQL goes on binding the names it was rendered with. A
     * later branch reusing one of them for a different value overwrites the
     * value the rendered branch needs, and the first half of the union then
     * runs with the second half's data. The names stay claimed across the
     * reset precisely so this is caught.
     *
     */
    public function testUnionBranchesCannotShareAPlaceholder()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('id = :id', ['id' => 1])
               ->union()
               ->cols(['*'])->from('b');

        $this->expectException(Exception\LogicException::class);
        $select->where('id = :id', ['id' => 2]);
    }

    public function testUnionAllBranchesCannotShareAPlaceholder()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('id = :id', ['id' => 1])
               ->unionAll()
               ->cols(['*'])->from('b');

        $this->expectException(Exception\LogicException::class);
        $select->where('id = :id', ['id' => 2]);
    }

    /**
     *
     * The message must name the union rather than the clause the placeholder
     * was bound in: the clause has been reset and rebuilt, and pointing at it
     * would send the reader looking at the branch they are writing now.
     *
     */
    public function testUnionCollisionMessageNamesTheRenderedBranch()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('id = :id', ['id' => 1])
               ->union()
               ->cols(['*'])->from('b');

        try {
            $select->where('id = :id', ['id' => 2]);
            $this->fail('Expected the shared placeholder to be rejected.');
        } catch (Exception\LogicException $e) {
            $this->assertStringContainsString("':id'", $e->getMessage());
            $this->assertStringContainsString('UNION branch', $e->getMessage());
        }
    }

    /**
     *
     * A clause reset frees the names that clause claimed, so the ownership a
     * rendered branch keeps has to sit with the union instead: left with the
     * clause, a resetWhere() in the next branch would hand back a name the
     * retained SQL still binds, and the overwrite goes undetected again.
     *
     */
    public function testResetWhereCannotFreeARenderedBranchesPlaceholder()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('id = :id', ['id' => 1])
               ->union()
               ->cols(['*'])->from('b')
               ->resetWhere();

        $this->expectException(Exception\LogicException::class);
        $select->where('id = :id', ['id' => 2]);
    }

    public function testResetHavingCannotFreeARenderedBranchesPlaceholder()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->having('id = :id', ['id' => 1])
               ->union()
               ->cols(['*'])->from('b')
               ->resetHaving();

        $this->expectException(Exception\LogicException::class);
        $select->having('id = :id', ['id' => 2]);
    }

    public function testResetTablesCannotFreeARenderedBranchesPlaceholder()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')
               ->join('LEFT', 'j', 'j.id = a.id AND j.status = :id', ['id' => 1])
               ->union()
               ->cols(['*'])->from('b')
               ->resetTables();

        $this->expectException(Exception\LogicException::class);
        $select->from('c')->join('LEFT', 'k', 'k.status = :id', ['id' => 2]);
    }

    /**
     *
     * Both branches filtering on one value -- the same tenant either side of
     * the union -- ask for exactly what is already bound. Nothing is lost, so
     * nothing is wrong: rejecting it would break working queries.
     *
     */
    public function testUnionBranchesMayShareAPlaceholderOnTheSameValue()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('tenant = :t', ['t' => 5])
               ->union()
               ->cols(['*'])->from('b')->where('tenant = :t', ['t' => 5]);

        $statement = $select->getStatement();
        $this->assertSame(2, substr_count($statement, ':t'));
        $this->assertSame(['t' => 5], $select->getBindValues());
    }

    /**
     *
     * Sharing the name on an equal value must not move ownership to the new
     * clause: if it did, resetting that clause would free a name the rendered
     * branch still binds.
     *
     */
    public function testSharingOnTheSameValueLeavesOwnershipWithTheUnion()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('tenant = :t', ['t' => 5])
               ->union()
               ->cols(['*'])->from('b')->where('tenant = :t', ['t' => 5])
               ->resetWhere();

        $this->expectException(Exception\LogicException::class);
        $select->where('tenant = :t', ['t' => 6]);
    }

    /**
     *
     * resetUnions() throws away the rendered SQL, and with it the only reason
     * those placeholders were held: nothing binds them any more, so the names
     * are free again.
     *
     */
    public function testResetUnionsReleasesTheRenderedPlaceholders()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('id = :id', ['id' => 1])
               ->union()
               ->cols(['*'])->from('b');

        $select->resetUnions();

        $select->where('id = :id', ['id' => 2]);
        $this->assertSame(['id' => 2], $select->getBindValues());
    }

    /**
     *
     * A hand-bound value has no claimant, which is what lets it overwrite --
     * but the rendered branch can be written around it all the same, with the
     * condition naming :id and bindValue() supplying the value. The retained
     * SQL binds that name as surely as any other, so the union takes it over
     * too and a later clause cannot rebind it to something else.
     *
     */
    public function testHandBoundPlaceholderOfARenderedBranchIsHeld()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('id = :id');
        $select->bindValue('id', 1);
        $select->union()->cols(['*'])->from('b');

        $this->expectException(Exception\LogicException::class);
        $select->where('id = :id', ['id' => 2]);
    }

    /**
     *
     * The union holds every name bound when the branch was rendered, not the
     * subset its SQL spells out as ':name'. A positional placeholder is the
     * case that shows why: the retained SQL keeps the '?' token and the value
     * is keyed by number, so reading the SQL back cannot recover the name.
     *
     * Narrowing the claim to the names a branch visibly mentions looks like a
     * tidy-up and passes every other test in this file. It frees this one,
     * and the first branch then runs on the second branch's value with
     * nothing raised. That is what this test is here to stop.
     *
     */
    public function testPositionalPlaceholderOfARenderedBranchIsHeld()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('id = ?');
        $select->bindValue(1, 5);
        $select->union()->cols(['*'])->from('b');

        // the retained branch spells no name to scan for
        $this->assertStringContainsString('id = ?', $select->getStatement());

        $this->expectException(Exception\LogicException::class);
        $select->where('id = ?', [1 => 6]);
    }

    /**
     *
     * The message has to describe the query the reader is looking at. A
     * positional placeholder keeps its `?` in the statement, so quoting it as
     * ':1' would name a token that is not there.
     *
     */
    public function testCollisionMessageDoesNotNameAPositionalPlaceholder()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('id = ?');
        $select->bindValue(1, 5);
        $select->union()->cols(['*'])->from('b');

        try {
            $select->where('id = ?', [1 => 6]);
            $this->fail('Expected a collision on the positional placeholder.');
        } catch (Exception\LogicException $e) {
            $message = $e->getMessage();
            $this->assertStringContainsString('positional placeholder 1', $message);
            $this->assertStringNotContainsString("':1'", $message);

            // "use a different placeholder" is not something the caller can
            // do with `?`, whose number is its offset in the values array
            $this->assertStringContainsString('names instead', $message);
        }
    }

    public function testHandBoundPlaceholderOfARenderedBranchMayBeSharedOnTheSameValue()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('tenant = :t');
        $select->bindValue('t', 5);
        $select->union()->cols(['*'])->from('b')->where('tenant = :t', ['t' => 5]);

        $this->assertSame(['t' => 5], $select->getBindValues());
    }

    /**
     *
     * A name that starts with no claimant can acquire one. Hand-bound, then
     * taken over by the union at render time, then shared by the next
     * branch's WHERE: resetUnions() hands it to that WHERE, which is still in
     * the query and still binding it, so a second clause rebinding it is the
     * ordinary clash. Being hand-bound to begin with does not exempt it --
     * only bindValue() itself keeps that privilege.
     *
     */
    public function testAHandBoundNameSharedByALiveClauseBecomesItsToHold()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('id = :id');
        $select->bindValue('id', 1);
        $select->union()->cols(['*'])->from('b')->where('id = :id', ['id' => 1]);
        $select->resetUnions();

        // the sharing WHERE outlives the union it was sharing with
        $this->assertStringContainsString('id = :id', $select->getStatement());

        $this->expectException(Exception\LogicException::class);
        $select->having('x = :id', ['id' => 2]);
    }

    /**
     *
     * The same name with no live claimant is free after resetUnions(), so the
     * handover cannot be blaming the union for a hold nothing needs.
     *
     */
    public function testAHandBoundNameNoClauseSharesIsFreeAfterResetUnions()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('id = :id');
        $select->bindValue('id', 1);
        $select->union()->cols(['*'])->from('b');
        $select->resetUnions();

        $select->having('x = :id', ['id' => 2]);
        $this->assertSame(['id' => 2], $select->getBindValues());
    }

    /**
     *
     * Binding by hand stays the escape hatch it is everywhere else: a null
     * source overwrites the value without the union's claim standing in the
     * way.
     *
     */
    public function testHandBindStillOverwritesARenderedBranchesValue()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('id = :id', ['id' => 1])
               ->union()
               ->cols(['*'])->from('b');

        $select->bindValue('id', 9);
        $this->assertSame(['id' => 9], $select->getBindValues());
    }

    /**
     *
     * Sharing on an equal value leaves the name with the union, so the active
     * branch's own condition is never recorded as a claimant. resetUnions()
     * then frees the name while that condition is still in the query.
     *
     */
    public function testResetUnionsCannotFreeANameTheActiveBranchStillUses()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('tenant = :t', ['t' => 5])
               ->union()
               ->cols(['*'])->from('b')->where('tenant = :t', ['t' => 5])
               ->resetUnions();

        $this->expectException(Exception\LogicException::class);
        $select->where('other = :t', ['t' => 6]);
    }

    /**
     *
     * Sharing a union's name is one clause's privilege: two different clauses
     * of the active branch asking for it are the ordinary cross-clause clash,
     * since either may revise its value afterwards.
     *
     */
    public function testTwoClausesCannotBothShareARenderedBranchesPlaceholder()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('tenant = :t', ['t' => 5])
               ->union()
               ->cols(['*'])->from('b')->where('tenant = :t', ['t' => 5]);

        $this->expectException(Exception\LogicException::class);
        $select->having('tenant = :t', ['t' => 5]);
    }

    /**
     *
     * Once resetUnions() has handed the name to the clause that was sharing
     * it, resetting that clause frees it for real: nothing binds it any more.
     *
     */
    public function testResetUnionsThenResettingTheSharingClauseFreesTheName()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('tenant = :t', ['t' => 5])
               ->union()
               ->cols(['*'])->from('b')->where('tenant = :t', ['t' => 5])
               ->resetUnions()
               ->resetWhere();

        $select->where('other = :t', ['t' => 6]);
        $this->assertSame(['t' => 6], $select->getBindValues());
    }

    /**
     *
     * A clause that has been reset is no longer using the name it shared, so
     * a later resetUnions() must not hand the name back to it -- that would
     * refuse a placeholder nothing in the query binds.
     *
     */
    public function testResettingTheSharingClauseFirstStillFreesTheName()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('tenant = :t', ['t' => 5])
               ->union()
               ->cols(['*'])->from('b')->where('tenant = :t', ['t' => 5])
               ->resetWhere()
               ->resetUnions();

        $select->where('other = :t', ['t' => 6]);
        $this->assertSame(['t' => 6], $select->getBindValues());
    }

    /**
     *
     * A sharing clause that has itself been rendered into a union branch is
     * no longer a live claimant: resetUnions() throws away that SQL too, so
     * the name is free rather than owed back to the clause.
     *
     */
    public function testASharingClauseRenderedIntoItsOwnBranchDoesNotHoldTheName()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('tenant = :t', ['t' => 5])
               ->union()
               ->cols(['*'])->from('b')->where('tenant = :t', ['t' => 5])
               ->union()
               ->cols(['*'])->from('c')
               ->resetUnions();

        $select->where('other = :t', ['t' => 6]);
        $this->assertSame(['t' => 6], $select->getBindValues());
    }

    /**
     *
     * Same again for a sub-select's names, which no clause reset releases:
     * once the branch holding the sub-select is rendered, resetUnions() must
     * still free them.
     *
     */
    public function testASharedSubSelectNameIsFreedAfterItsBranchIsRendered()
    {
        $sub = $this->query_factory->newSelect();
        $sub->cols(['*'])->from('s');
        $sub->bindValue('t', 5);

        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('tenant = :t', ['t' => 5])
               ->union()
               ->cols(['*'])->fromSubSelect($sub, 'x')
               ->union()
               ->cols(['*'])->from('c')
               ->resetUnions();

        $select->where('other = :t', ['t' => 6]);
        $this->assertSame(['t' => 6], $select->getBindValues());
    }

    /**
     *
     * union() builds the current half into SQL and then resets, so every
     * value bound so far must still be there to bind against the retained
     * statement. Clearing bind values on reset breaks this, and only the
     * integration suite catches it -- the statement is well-formed, PDO just
     * has nothing to bind.
     *
     */
    public function testUnionKeepsTheValuesOfEveryHalf()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('orders')
               ->where('status = :first', ['first' => 'pending'])
               ->union()
               ->cols(['*'])->from('orders')
               ->where('status = :second', ['second' => 'shipped']);

        $statement = $select->getStatement();
        $bind_values = $select->getBindValues();

        $this->assertStringContainsString(':first', $statement);
        $this->assertStringContainsString(':second', $statement);
        $this->assertSame(
            ['first' => 'pending', 'second' => 'shipped'],
            $bind_values
        );
    }

    /**
     *
     * A bulk insert renames each row's placeholders to `<name>_<row>` and
     * banks them. Those generated names are as much a claim on the flat bind
     * array as any other, so a clause binding one of them by hand afterwards
     * would lose its value to the banked one: the merge in getBindValues()
     * comes last. This is the reproduction filed as #241.
     *
     */
    public function testABankedBulkNameCollidesWithALaterCondition()
    {
        $insert = $this->newFactory('pgsql')->newInsert();
        $insert->into('t')
               ->cols(['status' => 'row0'])
               ->addRow(['status' => 'row1']);

        $this->expectException(Exception\LogicException::class);
        $this->expectExceptionMessage("':status_0' is already in use by a bulk-insert row");
        $insert->onConflict('id')
               ->doUpdateCol('status')
               ->doUpdateWhere('t.note = :status_0', ['status_0' => 'from the condition']);
    }

    /**
     *
     * And the other way round: the clause claimed the name first, so the row
     * that would bank over it is the second claimant.
     *
     */
    public function testARowCannotBankOverANameAClauseAlreadyClaimed()
    {
        $insert = $this->newFactory('pgsql')->newInsert();
        $insert->into('t')
               ->cols(['status' => 'row0'])
               ->onConflict('id')
               ->doUpdateCol('status')
               ->doUpdateWhere('t.note = :status_0', ['status_0' => 'from the condition']);

        $this->expectException(Exception\LogicException::class);
        $this->expectExceptionMessage("':status_0' is already in use by a condition");
        $insert->addRow(['status' => 'row1']);
    }

    /**
     *
     * A hand bind overwrites the value wherever it lands, as it does
     * everywhere else -- rebinding before execution is legitimate. On the
     * bulk path the banked value used to win regardless, so the hand-bound
     * one vanished without a word.
     *
     */
    public function testAHandBindOverwritesABankedBulkValue()
    {
        $insert = $this->newFactory('pgsql')->newInsert();
        $insert->into('t')
               ->cols(['status' => 'row0'])
               ->addRow(['status' => 'row1']);

        $insert->getStatement();
        $insert->bindValue('status_0', 'by hand');

        $this->assertSame(
            ['status_0' => 'by hand', 'status_1' => 'row1'],
            $insert->getBindValues()
        );
    }

    /**
     *
     * resetBindValues() clears the values a query has bound, banked bulk ones
     * included, so the names they held are free again. Leaving the banked
     * sources behind would refuse a name nothing is bound to any more.
     *
     */
    public function testResetBindValuesFreesTheBankedBulkNames()
    {
        $insert = $this->newFactory('pgsql')->newInsert();
        $insert->into('t')
               ->cols(['status' => 'row0'])
               ->addRow(['status' => 'row1']);
        $insert->getStatement();

        $insert->resetBindValues();

        // no collision, because nothing is holding :status_0 now
        $insert->onConflict('id')
               ->doUpdateCol('status')
               ->doUpdateWhere('t.note = :status_0', ['status_0' => 'fresh']);

        $values = $insert->getBindValues();
        $this->assertSame('fresh', $values['status_0']);
        $this->assertArrayNotHasKey('status_1', $values);
    }

    /**
     *
     * The reset takes the values and leaves the rows. Columns are structure,
     * not bound values -- the non-bulk path keeps col_values the same way, so
     * the statement still spells every placeholder and simply has nothing
     * bound to them, which is what resetBindValues() means everywhere else.
     *
     */
    public function testResetBindValuesKeepsTheBulkRows()
    {
        $insert = $this->newFactory('pgsql')->newInsert();
        $insert->into('t')
               ->cols(['status' => 'row0'])
               ->addRow(['status' => 'row1']);
        $insert->getStatement();

        $insert->resetBindValues();

        $this->assertSame([], $insert->getBindValues());
        $statement = $insert->getStatement();
        $this->assertStringContainsString(':status_0', $statement);
        $this->assertStringContainsString(':status_1', $statement);
    }

    /**
     *
     * A row still being built holds its own column names, and one of them may
     * be spelled the same as a name an earlier row banked -- column `a_1`
     * against the `a_1` that column `a` banked in row 1. Asking for the bind
     * values before the statement is built catches that overlap in the open.
     *
     * The banked value wins, because it is the one the finished statement
     * binds: the live column has not been renamed yet and will bank itself as
     * `a_1_2`.
     *
     */
    public function testABankedNameBeatsALiveColumnSpelledTheSame()
    {
        $insert = $this->newFactory('pgsql')->newInsert();
        $insert->into('t')
               ->cols(['a' => 'r0', 'a_1' => 'x0'])
               ->addRow(['a' => 'r1', 'a_1' => 'x1'])
               ->addRow(['a' => 'r2', 'a_1' => 'x2']);

        // deliberately before getStatement(), while row 2 is still live
        $values = $insert->getBindValues();
        $this->assertSame('r1', $values['a_1']);

        // and once built, the live row banks itself out of the way
        $insert->getStatement();
        $values = $insert->getBindValues();
        $this->assertSame('r1', $values['a_1']);
        $this->assertSame('x2', $values['a_1_2']);
    }

    /**
     *
     * The generated names cannot collide with each other: the row number is
     * appended, so a column named `a` in row 1 banks `a_1` while a column
     * named `a_1` in row 0 banks `a_1_0`. Pinned so that a future change to
     * the naming scheme has to think about it.
     *
     */
    public function testTwoBulkColumnsWhoseNamesOverlapDoNotCollide()
    {
        $insert = $this->newFactory('pgsql')->newInsert();
        $insert->into('t')
               ->cols(['a' => 'a-row0', 'a_1' => 'a1-row0'])
               ->addRow(['a' => 'a-row1', 'a_1' => 'a1-row1']);

        $insert->getStatement();

        $this->assertSame(
            [
                'a_0' => 'a-row0',
                'a_1_0' => 'a1-row0',
                'a_1' => 'a-row1',
                'a_1_1' => 'a1-row1',
            ],
            $insert->getBindValues()
        );
    }

    /**
     *
     * A union added after a CTE must keep the CTE's claims. If a subsequent
     * clause or branch tries to rebind one to a different value, it must throw
     * a collision.
     *
     */
    public function testUnionAfterWithKeepsCteClaimsAndThrowsOnConflict()
    {
        $sub = $this->query_factory->newSelect();
        $sub->cols(['c1'])->from('t1')->where('id = :id', ['id' => 1]);

        $select = $this->query_factory->newSelect();
        $select->with('cte', $sub);

        // Render the CTE branch into a union branch
        $select->cols(['*'])->from('cte')->union();

        $this->expectException(Exception\LogicException::class);
        $select->cols(['*'])->from('t2')->where('id = :id', ['id' => 2]);
    }

    /**
     *
     * A CTE's claim belongs to the statement, not to a branch. So resetting
     * unions must not free a CTE's name, but resetWith() must.
     *
     */
    public function testCteClaimsSurviveResetUnionsButResetWithFreesThem()
    {
        $sub = $this->query_factory->newSelect();
        $sub->cols(['c1'])->from('t1')->where('id = :id', ['id' => 1]);

        $select = $this->query_factory->newSelect();
        $select->with('cte', $sub);

        // Render the CTE branch into a union branch
        $select->cols(['*'])->from('cte')->union();

        // Reset unions
        $select->resetUnions();

        // The CTE's claim on :id should still be active, so a conflicting rebind throws
        try {
            $select->where('id = :id', ['id' => 2]);
            $this->fail('Expected collision since CTE claim should survive resetUnions()');
        } catch (Exception\LogicException $e) {
            // expected
        }

        // Reset with
        $select->resetWith();

        // Now we should be able to bind it to a different value without collision
        $select->where('id = :id', ['id' => 2]);
        $this->assertSame(['id' => 2], $select->getBindValues());
    }

    /**
     *
     * A rendered branch may spell a name the CTE binds, when a clause of that
     * branch was sharing it. The CTE is the live claimant, so the name must
     * come out of the reset labelled 'with' rather than 'union': labelled the
     * other way, resetUnions() frees a name the CTE still binds, and the next
     * clause may then bind it to a value of its own with nothing to report
     * the clash. This is what fixes the order the claims are restored in.
     *
     */
    public function testCteKeepsAClaimARenderedBranchAlsoSpells()
    {
        $sub = $this->query_factory->newSelect();
        $sub->cols(['c1'])->from('t1')->where('id = :id', ['id' => 1]);

        $select = $this->query_factory->newSelect();
        $select->with('cte', $sub)
               ->cols(['*'])
               ->from('cte')
               ->where('id = :id', ['id' => 1])
               ->union()
               ->cols(['*'])
               ->from('t2');

        // the union is gone; the CTE is not, and it still binds :id
        $select->resetUnions();

        $this->expectException(Exception\LogicException::class);
        $select->where('id = :id', ['id' => 2]);
    }

    /**
     *
     * The union may hold a name a CTE only shares. The shared list does not
     * survive the render, so a CTE recorded there and nowhere else would come
     * out of the next union() holding nothing, and the branch after that
     * could rebind the name the CTE is still spelling.
     *
     */
    public function testCteKeepsAClaimItOnlySharesWithTheUnion()
    {
        $sub = $this->query_factory->newSelect();
        $sub->cols(['c1'])->from('t1')->where('id = :id', ['id' => 1]);

        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('id = :id', ['id' => 1])->union();

        // the union holds :id already, so the CTE comes in as the sharer
        $select->with('cte', $sub);

        $select->cols(['*'])->from('b')->union();
        $select->resetUnions();

        $this->expectException(Exception\LogicException::class);
        $select->where('id = :id', ['id' => 999]);
    }

    /**
     *
     * And the mirror: a name the CTE holds which a rendered branch also
     * spells belongs to both, so resetWith() hands it to the union rather
     * than freeing it. Freed, the branch would go on reading a placeholder
     * the next clause is at liberty to rebind.
     *
     */
    public function testResetWithHandsBackANameARenderedBranchSpells()
    {
        $sub = $this->query_factory->newSelect();
        $sub->cols(['c1'])->from('t1')->where('id = :id', ['id' => 1]);

        $select = $this->query_factory->newSelect();
        $select->with('cte', $sub)
               ->cols(['*'])
               ->from('cte')
               ->where('id = :id', ['id' => 1])
               ->union()
               ->cols(['*'])
               ->from('t2');

        // the CTE is gone, but the branch retained above still spells :id
        $select->resetWith();

        $this->expectException(Exception\LogicException::class);
        $select->where('id = :id', ['id' => 777]);
    }

    /**
     *
     * An outer clause reusing a CTE name with the same value is allowed,
     * but reusing it with a different value throws.
     *
     */
    public function testOuterClauseReusingCteNameSameValueAllowedDifferentValueThrows()
    {
        $sub = $this->query_factory->newSelect();
        $sub->cols(['c1'])->from('t1')->where('id = :id', ['id' => 1]);

        $select = $this->query_factory->newSelect();
        $select->with('cte', $sub);

        // Same value is allowed
        $select->where('id = :id', ['id' => 1]);

        // Different value throws
        $select2 = $this->query_factory->newSelect();
        $select2->with('cte', $sub);

        $this->expectException(Exception\LogicException::class);
        $select2->where('id = :id', ['id' => 2]);
    }

    /**
     *
     * union() reaches the sharing rule only ever holding the name itself:
     * addUnion() renders the branch being closed and re-reads the claims from
     * that SQL before the supplied branch's values are bound, so a clause's
     * claim has already passed to the union by then. The CTE rule added
     * beside this one therefore leaves union() where it was; this pins that,
     * since the two are read in one place and it would be easy to widen the
     * wrong one.
     *
     */
    public function testUnionKeepsItsOwnSharingRule()
    {
        $branch = $this->query_factory->newSelect();
        $branch->cols(['*'])->from('b')->where('id = :id', ['id' => 1]);

        $select = $this->query_factory->newSelect();
        $select->cols(['*'])->from('a')->where('id = :id', ['id' => 1]);
        $select->union($branch);

        $this->assertSame(['id' => 1], $select->getBindValues());

        // the union holds the name, so a branch wanting a different value for
        // it is still caught
        $this->expectException(Exception\LogicException::class);
        $select->where('id = :id', ['id' => 2]);
    }

    /**
     *
     * A hand-bound value claims nothing, so a CTE asking for the same name
     * and value takes the claim and records no second claimant. Recording
     * one would put a name in the shared list that no clause is holding,
     * and resetWith() would then hand the name to nobody.
     *
     */
    public function testCteTakesTheClaimOnAHandBoundName()
    {
        $sub = $this->query_factory->newSelect();
        $sub->cols(['c1'])->from('t1')->where('id = :id', ['id' => 5]);

        $select = $this->query_factory->newSelect();
        $select->bindValue('id', 5);
        $select->with('cte', $sub);

        // the CTE holds it now, so a clause wanting a different value throws
        try {
            $select->where('id = :id', ['id' => 6]);
            $this->fail('Expected the CTE to hold the placeholder name.');
        } catch (Exception\LogicException $e) {
            $this->assertStringContainsString("':id'", $e->getMessage());
            $this->assertStringContainsString('common table expression', $e->getMessage());
        }

        // and once the CTE is gone the name is free again, rather than being
        // handed to a sharer that was never there
        $select->resetWith();
        $select->where('id = :id', ['id' => 6]);
        $this->assertSame(['id' => 6], $select->getBindValues());
    }

    /**
     *
     * resetWith() hands a shared name over to the WHERE clause sharing it,
     * so that resetting the CTE does not release the name when the WHERE is
     * still using it.
     *
     */
    public function testResetWithHandsSharedNameToSharingClause()
    {
        $sub = $this->query_factory->newSelect();
        $sub->cols(['c1'])->from('t1')->where('id = :id', ['id' => 1]);

        $select = $this->query_factory->newSelect();
        $select->with('cte', $sub);

        // Share the name in WHERE
        $select->where('id = :id', ['id' => 1]);

        // Reset the CTE
        $select->resetWith();

        // The WHERE still uses the name, so a conflicting rebind in HAVING should throw
        $this->expectException(Exception\LogicException::class);
        $select->having('id = :id', ['id' => 2]);
    }

    /**
     *
     * Reusing a name when the WHERE is bound first and the CTE is bound second
     * is also allowed if values are identical.
     *
     */
    public function testCteReusingOuterClauseNameSameValueAllowed()
    {
        $sub = $this->query_factory->newSelect();
        $sub->cols(['c1'])->from('t1')->where('id = :id', ['id' => 1]);

        $select = $this->query_factory->newSelect();
        $select->where('id = :id', ['id' => 1]);

        // Same value is allowed
        $select->with('cte', $sub);

        // Different value throws
        $select2 = $this->query_factory->newSelect();
        $select2->where('id = :id', ['id' => 1]);

        $sub2 = $this->query_factory->newSelect();
        $sub2->cols(['c1'])->from('t1')->where('id = :id', ['id' => 2]);

        $this->expectException(Exception\LogicException::class);
        $select2->with('cte', $sub2);
    }
}
