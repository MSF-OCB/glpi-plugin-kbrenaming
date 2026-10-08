<?php

use PHPUnit\Framework\TestCase;

final class HookTest extends TestCase
{
    public function testKbNames(): void
    {
        $this->assertTrue(plugin_kbrenaming_is_kb_name('KB5034441'));
        $this->assertTrue(plugin_kbrenaming_is_kb_name(' kb123456 '));
        $this->assertFalse(plugin_kbrenaming_is_kb_name('KB12345'));
        $this->assertFalse(plugin_kbrenaming_is_kb_name('Update for KB5034441'));
        $this->assertFalse(plugin_kbrenaming_is_kb_name(''));
    }

    public function testFusionInventoryHookIgnoresNonKbSoftware(): void
    {
        $params = ['inventory' => ['SOFTWARES' => [['NAME' => 'Firefox', 'VERSION' => '128.0']]]];
        $this->assertSame($params, plugin_fusioninventory_addinventoryinfos_kbrenaming($params));
    }
}
