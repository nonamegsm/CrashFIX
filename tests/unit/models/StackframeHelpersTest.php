<?php

namespace tests\unit\models;

use app\models\Stackframe;
use Codeception\Test\Unit;

/**
 * Pure-function tests for Stackframe::getTitle().
 */
class StackframeHelpersTest extends Unit
{
    public function testTitleWithFullSymbolInfo(): void
    {
        $f = new Stackframe([
            'addr_pc'         => 0xDEADBEEF,
            'und_symbol_name' => 'MyClass::doSomething',
            'offs_in_symbol'  => 16,
            'src_file_name'   => 'foo.cpp',
            'src_line'        => 42,
        ]);
        $title = $f->getTitle();
        verify($title)->stringContainsString('MyClass::doSomething');
        verify($title)->stringContainsString('+0x10');
        verify($title)->stringContainsString('foo.cpp:42');
    }

    public function testTitleFallsBackToHexAddrWhenNoSymbol(): void
    {
        $f = new Stackframe([
            'addr_pc' => 0x1234,
        ]);
        $title = $f->getTitle();
        verify($title)->stringContainsString('+0x1234');
    }

    public function testTitlePrefersUndecoratedSymbolName(): void
    {
        $f = new Stackframe([
            'addr_pc'         => 0x1,
            'symbol_name'     => '?mangled@@YAHXZ',
            'und_symbol_name' => 'pretty::name',
        ]);
        verify($f->getTitle())->stringContainsString('pretty::name');
        verify($f->getTitle())->stringNotContainsString('mangled');
    }
}
