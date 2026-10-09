<?php

use PHPUnit\Framework\TestCase;

final class ToolboxTest extends TestCase
{
    protected function setUp(): void
    {
        global $DB;
        $DB = new KbrenamingTestDB();
    }

    public function testWaitTimeIsZeroWhenIntervalElapsed(): void
    {
        $this->assertSame(0.0, PluginKbrenamingToolbox::getWaitTime(100.0, 100.5, 0.1));
        $this->assertSame(0.0, PluginKbrenamingToolbox::getWaitTime(0.0, 100.0, 0.1));
    }

    public function testWaitTimeIsRemainderOfInterval(): void
    {
        $this->assertEqualsWithDelta(0.06, PluginKbrenamingToolbox::getWaitTime(100.0, 100.04, 0.1), 1e-9);
    }

    public function testWaitTimeIsCappedForAFutureLastRequest(): void
    {
        // Clock moved back or foreign value in shared memory: never wait more than one interval.
        $this->assertSame(0.1, PluginKbrenamingToolbox::getWaitTime(1.0e12, 100.0, 0.1));
    }

    public function testMoveSoftwareVersionsMovesEveryVersion(): void
    {
        global $DB;
        $this->assertTrue(PluginKbrenamingToolbox::moveSoftwareVersions([3, 4], 9));
        $this->assertSame([
            ['glpi_items_softwareversions', ['softwareversions_id' => 9], ['softwareversions_id' => 3]],
            ['glpi_items_softwareversions', ['softwareversions_id' => 9], ['softwareversions_id' => 4]],
        ], $DB->updates);
    }

    public function testMoveSoftwareVersionsReportsAFailedMove(): void
    {
        global $DB;
        $DB->fail_old_ids = [3];
        $this->assertFalse(PluginKbrenamingToolbox::moveSoftwareVersions([3, 4], 9));
        $this->assertCount(2, $DB->updates);
    }

    public function testMoveSoftwareVersionsRejectsAnInvalidTarget(): void
    {
        global $DB;
        $this->assertFalse(PluginKbrenamingToolbox::moveSoftwareVersions([3], 0));
        $this->assertSame([], $DB->updates);
    }

    public function testMoveOntoItselfIsANoOp(): void
    {
        global $DB;
        $this->assertTrue(PluginKbrenamingToolbox::moveSoftwareVersions([9], 9));
        $this->assertSame([], $DB->updates);
    }

    public function testMoveOfNoVersionSucceeds(): void
    {
        $this->assertTrue(PluginKbrenamingToolbox::moveSoftwareVersions([], 9));
    }

    public function testStrUnionKeepsCommonPrefix(): void
    {
        $this->assertSame(
            '2024-05 Cumulative Update for Windows 10 Version 2',
            PluginKbrenamingToolbox::str_union(
                '2024-05 Cumulative Update for Windows 10 Version 22H2 x64',
                '2024-05 Cumulative Update for Windows 10 Version 21H2 x86',
                0,
                16
            )
        );
    }

    public function testStrUnionFallsBackBelowMinimum(): void
    {
        $this->assertSame('abcdef', PluginKbrenamingToolbox::str_union('abcdef', 'abXYZ', 0, 5));
        $this->assertSame('abXYZ', PluginKbrenamingToolbox::str_union('abcdef', 'abXYZ', 1, 5));
        $this->assertSame('second', PluginKbrenamingToolbox::str_union('', 'second'));
    }
}
