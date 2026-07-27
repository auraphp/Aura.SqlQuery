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
        $update->table('t1')->cols(array('id' => 1));

        $this->expectException(Exception\LogicException::class);
        $update->where('id = :id', array('id' => 2));
    }

    public function testDoUpdateColCollidesWithCols()
    {
        $insert = $this->newFactory('pgsql')->newInsert();
        $insert
            ->into('t1')
            ->cols(array('status__on_conflict' => 'from cols'))
            ->onConflict('id');

        $this->expectException(Exception\LogicException::class);
        $insert->doUpdateCol('status', 'from do update');
    }

    public function testOnDuplicateKeyUpdateColCollidesWithCols()
    {
        $insert = $this->newFactory('mysql')->newInsert();
        $insert
            ->into('t1')
            ->cols(array('status__on_duplicate_key' => 'from cols'));

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
        $update->table('t1')->cols(array('id' => 1));

        try {
            $update->where('id = :id', array('id' => 2));
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
        $update->table('t1')->cols(array('id' => 1));

        $this->expectException(Exception\LogicException::class);
        $update->where('id = :id', array('id' => 1));
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
        $select->cols(array('*'))->from('t1')->where('a = :id', array('id' => 1));

        $this->expectException(Exception\LogicException::class);
        $select->where('b = :id', array('id' => 2));
    }

    public function testWhereAndHavingCannotShareAPlaceholder()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(array('a'))->from('t1')->where('a = :x', array('x' => 1));

        $this->expectException(Exception\LogicException::class);
        $select->having('b = :x', array('x' => 2));
    }

    public function testJoinAndWhereCannotShareAPlaceholder()
    {
        $select = $this->query_factory->newSelect();
        $select
            ->cols(array('a'))
            ->from('t1')
            ->join('LEFT', 't2', 't2.id = t1.id AND t2.status = :x', array('x' => 1));

        $this->expectException(Exception\LogicException::class);
        $select->where('b = :x', array('x' => 2));
    }

    public function testTwoJoinConditionsCannotShareAPlaceholder()
    {
        $select = $this->query_factory->newSelect();
        $select
            ->cols(array('a'))
            ->from('t1')
            ->join('LEFT', 't2', 't2.status = :x', array('x' => 1));

        $this->expectException(Exception\LogicException::class);
        $select->join('LEFT', 't3', 't3.status = :x', array('x' => 2));
    }

    public function testJoinSubSelectConditionCannotShareAPlaceholder()
    {
        $subSelect = $this->query_factory->newSelect();
        $subSelect->cols(array('id'))->from('users');

        $select = $this->query_factory->newSelect();
        $select
            ->cols(array('a'))
            ->from('t1')
            ->where('b = :x', array('x' => 1));

        $this->expectException(Exception\LogicException::class);
        $select->joinSubSelect('LEFT', $subSelect, 'sub', 'sub.status = :x', array('x' => 2));
    }

    public function testRebindingByHandIsAllowed()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(array('*'))->from('t1')->where('id = :id', array('id' => 1));

        // changing the value before execution is legitimate
        $select->bindValue('id', 2);
        $this->assertSame(array('id' => 2), $select->getBindValues());

        // and so is reusing the query object with a fresh set of values
        $select->bindValues(array('id' => 3));
        $this->assertSame(array('id' => 3), $select->getBindValues());
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
        $update->table('orders')->cols(array('status' => 'shipped'));
        $update->bindValue('status', 'delivered');

        $this->expectException(Exception\LogicException::class);
        $update->where('status = :status', array('status' => 'pending'));
    }

    public function testHandBindStillOverwritesTheValue()
    {
        $update = $this->query_factory->newUpdate();
        $update->table('orders')->cols(array('status' => 'shipped'));
        $update->bindValue('status', 'delivered');

        $this->assertSame(array('status' => 'delivered'), $update->getBindValues());
    }

    public function testHandBoundValueMayBeClaimedByCols()
    {
        $update = $this->query_factory->newUpdate();
        $update->table('t1')->bindValue('id', 1);

        // a null source never blocks a later bind
        $update->cols(array('id' => 2));
        $this->assertSame(array('id' => 2), $update->getBindValues());
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
        $update->table('orders')->cols(array('status' => 'first'));
        $update->cols(array('status' => 'second'));

        $this->assertSame(array('status' => 'second'), $update->getBindValues());
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
        $update->table('orders')->cols(array('status' => 'first'));

        try {
            $update->where('status = :status', array('status' => 'first'));
            $this->fail('Expected the shared placeholder to be rejected.');
        } catch (Exception\LogicException $e) {
            // the condition never took the name, so cols() still owns it and
            // may revise its own value
            $update->cols(array('status' => 'second'));
            $this->assertSame(
                array('status' => 'second'),
                $update->getBindValues()
            );
        }
    }

    public function testBulkInsertDoesNotCollideWithItself()
    {
        $insert = $this->query_factory->newInsert();
        $insert
            ->into('t1')
            ->cols(array('c1' => 'v1-0'))
            ->addRow(array('c1' => 'v1-1'))
            ->addRow(array('c1' => 'v1-2'));

        $expect = array('c1_0' => 'v1-0', 'c1_1' => 'v1-1', 'c1_2' => 'v1-2');
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
            ->cols(array('status__on_conflict' => 'banked into the row'))
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
        $update->table('t1')->cols(array('id' => 1));

        // force the desync the guard exists for
        $property = new \ReflectionProperty($update, 'bind_values');
        $property->setAccessible(true);
        $property->setValue($update, array());

        $update->where('id = :id', array('id' => 2));
        $this->assertSame(array('id' => 2), $update->getBindValues());
    }

    public function testResetBindValuesClearsTheSources()
    {
        $update = $this->query_factory->newUpdate();
        $update->table('t1')->cols(array('id' => 1));
        $update->resetBindValues();

        // with the source forgotten there is nothing left to collide with
        $update->where('id = :id', array('id' => 2));
        $this->assertSame(array('id' => 2), $update->getBindValues());
    }

    public function testClosureBindsDoNotBypassCollision()
    {
        $update = $this->query_factory->newUpdate();
        $update->table('orders')->cols(array('status' => 'shipped'));

        $this->expectException(Exception\LogicException::class);
        $update->where(function($query) {}, array('status' => 'pending'));
    }

    public function testSubselectBindsDoNotBypassCollision()
    {
        $subSelect = $this->query_factory->newSelect();
        $subSelect->cols(array('id'))->from('users')->where('status = :status', array('status' => 'active'));

        $select = $this->query_factory->newSelect();
        $select->cols(array('*'))->from('orders')
               ->where('status = :status', array('status' => 'pending'));

        $this->expectException(Exception\LogicException::class);
        $select->where('user_id IN (:sub)', array('sub' => $subSelect));
    }

    public function testFromSubSelectBindsDoNotBypassCollision()
    {
        $subSelect = $this->query_factory->newSelect();
        $subSelect->cols(array('id'))->from('users')->where('status = :status', array('status' => 'active'));

        $select = $this->query_factory->newSelect();
        $select->cols(array('*'))
               ->fromSubSelect($subSelect, 'sub');

        $this->expectException(Exception\LogicException::class);
        $select->where('status = :status', array('status' => 'pending'));
    }

    public function testSameSourceDifferentValueCollides()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(array('*'))->from('orders')
               ->where('status = :status', array('status' => 'pending'));

        $this->expectException(Exception\LogicException::class);
        $select->where('status = :status', array('status' => 'active'));
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
        $select->cols(array('*'))->from('orders')
               ->where('status = :status', array('status' => 'pending'));

        $select->resetWhere();

        // no collision, because the WHERE clause no longer claims the name
        $select->where('status = :status', array('status' => 'active'));
        $this->assertSame(array('status' => 'active'), $select->getBindValues());
    }

    public function testResetHavingFreesItsPlaceholderNames()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(array('*'))->from('orders')
               ->having('status = :status', array('status' => 'pending'));

        $select->resetHaving();

        $select->having('status = :status', array('status' => 'active'));
        $this->assertSame(array('status' => 'active'), $select->getBindValues());
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
        $select->cols(array('*'))->from('a')->where('id = :id', array('id' => 1))
               ->union()
               ->cols(array('*'))->from('b');

        $this->expectException(Exception\LogicException::class);
        $select->where('id = :id', array('id' => 2));
    }

    public function testUnionAllBranchesCannotShareAPlaceholder()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(array('*'))->from('a')->where('id = :id', array('id' => 1))
               ->unionAll()
               ->cols(array('*'))->from('b');

        $this->expectException(Exception\LogicException::class);
        $select->where('id = :id', array('id' => 2));
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
        $select->cols(array('*'))->from('a')->where('id = :id', array('id' => 1))
               ->union()
               ->cols(array('*'))->from('b');

        try {
            $select->where('id = :id', array('id' => 2));
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
        $select->cols(array('*'))->from('a')->where('id = :id', array('id' => 1))
               ->union()
               ->cols(array('*'))->from('b')
               ->resetWhere();

        $this->expectException(Exception\LogicException::class);
        $select->where('id = :id', array('id' => 2));
    }

    public function testResetHavingCannotFreeARenderedBranchesPlaceholder()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(array('*'))->from('a')->having('id = :id', array('id' => 1))
               ->union()
               ->cols(array('*'))->from('b')
               ->resetHaving();

        $this->expectException(Exception\LogicException::class);
        $select->having('id = :id', array('id' => 2));
    }

    public function testResetTablesCannotFreeARenderedBranchesPlaceholder()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(array('*'))->from('a')
               ->join('LEFT', 'j', 'j.id = a.id AND j.status = :id', array('id' => 1))
               ->union()
               ->cols(array('*'))->from('b')
               ->resetTables();

        $this->expectException(Exception\LogicException::class);
        $select->from('c')->join('LEFT', 'k', 'k.status = :id', array('id' => 2));
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
        $select->cols(array('*'))->from('a')->where('tenant = :t', array('t' => 5))
               ->union()
               ->cols(array('*'))->from('b')->where('tenant = :t', array('t' => 5));

        $statement = $select->getStatement();
        $this->assertSame(2, substr_count($statement, ':t'));
        $this->assertSame(array('t' => 5), $select->getBindValues());
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
        $select->cols(array('*'))->from('a')->where('tenant = :t', array('t' => 5))
               ->union()
               ->cols(array('*'))->from('b')->where('tenant = :t', array('t' => 5))
               ->resetWhere();

        $this->expectException(Exception\LogicException::class);
        $select->where('tenant = :t', array('t' => 6));
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
        $select->cols(array('*'))->from('a')->where('id = :id', array('id' => 1))
               ->union()
               ->cols(array('*'))->from('b');

        $select->resetUnions();

        $select->where('id = :id', array('id' => 2));
        $this->assertSame(array('id' => 2), $select->getBindValues());
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
        $select->cols(array('*'))->from('a')->where('id = :id');
        $select->bindValue('id', 1);
        $select->union()->cols(array('*'))->from('b');

        $this->expectException(Exception\LogicException::class);
        $select->where('id = :id', array('id' => 2));
    }

    public function testHandBoundPlaceholderOfARenderedBranchMayBeSharedOnTheSameValue()
    {
        $select = $this->query_factory->newSelect();
        $select->cols(array('*'))->from('a')->where('tenant = :t');
        $select->bindValue('t', 5);
        $select->union()->cols(array('*'))->from('b')->where('tenant = :t', array('t' => 5));

        $this->assertSame(array('t' => 5), $select->getBindValues());
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
        $select->cols(array('*'))->from('a')->where('id = :id');
        $select->bindValue('id', 1);
        $select->union()->cols(array('*'))->from('b')->where('id = :id', array('id' => 1));
        $select->resetUnions();

        // the sharing WHERE outlives the union it was sharing with
        $this->assertStringContainsString('id = :id', $select->getStatement());

        $this->expectException(Exception\LogicException::class);
        $select->having('x = :id', array('id' => 2));
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
        $select->cols(array('*'))->from('a')->where('id = :id');
        $select->bindValue('id', 1);
        $select->union()->cols(array('*'))->from('b');
        $select->resetUnions();

        $select->having('x = :id', array('id' => 2));
        $this->assertSame(array('id' => 2), $select->getBindValues());
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
        $select->cols(array('*'))->from('a')->where('id = :id', array('id' => 1))
               ->union()
               ->cols(array('*'))->from('b');

        $select->bindValue('id', 9);
        $this->assertSame(array('id' => 9), $select->getBindValues());
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
        $select->cols(array('*'))->from('a')->where('tenant = :t', array('t' => 5))
               ->union()
               ->cols(array('*'))->from('b')->where('tenant = :t', array('t' => 5))
               ->resetUnions();

        $this->expectException(Exception\LogicException::class);
        $select->where('other = :t', array('t' => 6));
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
        $select->cols(array('*'))->from('a')->where('tenant = :t', array('t' => 5))
               ->union()
               ->cols(array('*'))->from('b')->where('tenant = :t', array('t' => 5));

        $this->expectException(Exception\LogicException::class);
        $select->having('tenant = :t', array('t' => 5));
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
        $select->cols(array('*'))->from('a')->where('tenant = :t', array('t' => 5))
               ->union()
               ->cols(array('*'))->from('b')->where('tenant = :t', array('t' => 5))
               ->resetUnions()
               ->resetWhere();

        $select->where('other = :t', array('t' => 6));
        $this->assertSame(array('t' => 6), $select->getBindValues());
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
        $select->cols(array('*'))->from('a')->where('tenant = :t', array('t' => 5))
               ->union()
               ->cols(array('*'))->from('b')->where('tenant = :t', array('t' => 5))
               ->resetWhere()
               ->resetUnions();

        $select->where('other = :t', array('t' => 6));
        $this->assertSame(array('t' => 6), $select->getBindValues());
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
        $select->cols(array('*'))->from('a')->where('tenant = :t', array('t' => 5))
               ->union()
               ->cols(array('*'))->from('b')->where('tenant = :t', array('t' => 5))
               ->union()
               ->cols(array('*'))->from('c')
               ->resetUnions();

        $select->where('other = :t', array('t' => 6));
        $this->assertSame(array('t' => 6), $select->getBindValues());
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
        $sub->cols(array('*'))->from('s');
        $sub->bindValue('t', 5);

        $select = $this->query_factory->newSelect();
        $select->cols(array('*'))->from('a')->where('tenant = :t', array('t' => 5))
               ->union()
               ->cols(array('*'))->fromSubSelect($sub, 'x')
               ->union()
               ->cols(array('*'))->from('c')
               ->resetUnions();

        $select->where('other = :t', array('t' => 6));
        $this->assertSame(array('t' => 6), $select->getBindValues());
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
        $select->cols(array('*'))->from('orders')
               ->where('status = :first', array('first' => 'pending'))
               ->union()
               ->cols(array('*'))->from('orders')
               ->where('status = :second', array('second' => 'shipped'));

        $statement = $select->getStatement();
        $bind_values = $select->getBindValues();

        $this->assertStringContainsString(':first', $statement);
        $this->assertStringContainsString(':second', $statement);
        $this->assertSame(
            array('first' => 'pending', 'second' => 'shipped'),
            $bind_values
        );
    }
}
