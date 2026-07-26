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

    public function testCollisionMessageNamesBothSources()
    {
        $update = $this->query_factory->newUpdate();
        $update->table('t1')->cols(array('id' => 1));

        $this->expectException(Exception\LogicException::class);
        $this->expectExceptionMessage("':id'");
        $this->expectExceptionMessage('cols()');
        $update->where('id = :id', array('id' => 2));
    }

    public function testSameValueFromTwoSourcesDoesNotCollide()
    {
        $update = $this->query_factory->newUpdate();
        $update
            ->table('t1')
            ->cols(array('id' => 1))
            ->where('id = :id', array('id' => 1));

        $this->assertSame(array('id' => 1), $update->getBindValues());
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
     * A second part of the query binding the *same* value is allowed through,
     * but it must not take ownership of the name. If it did, the original
     * claimant revising its own placeholder afterwards would look like a
     * collision with the part that only ever agreed with it.
     *
     */
    public function testAgreeingOnAValueDoesNotTransferOwnership()
    {
        $update = $this->query_factory->newUpdate();
        $update->table('orders')->cols(array('status' => 'first'));

        // same value from a different part: no collision, and cols() keeps
        // the name
        $update->where('status = :status', array('status' => 'first'));

        // so cols() may still revise its own placeholder
        $update->cols(array('status' => 'second'));
        $this->assertSame(array('status' => 'second'), $update->getBindValues());
    }

    public function testAgreeingOnAValueStillGuardsTheOriginalOwner()
    {
        $update = $this->query_factory->newUpdate();
        $update->table('orders')->cols(array('status' => 'first'));
        $update->where('status = :status', array('status' => 'first'));

        // the condition disagreeing later is still a real collision
        $this->expectException(Exception\LogicException::class);
        $update->where('status = :status', array('status' => 'third'));
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
}
