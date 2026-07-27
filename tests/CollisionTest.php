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
