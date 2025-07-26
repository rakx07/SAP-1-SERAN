<?php

namespace App\Http\Controllers;

use App\Models\Memory;

class AddController extends Controller
{
    /**
     * Simulate ADD instruction.
     *
     * @param string $operand      The binary operand (memory address)
     * @param int    &$AX          Accumulator register (passed by reference)
     * @param int    &$BX          B register (passed by reference)
     * @param string $pc_address   Current PC in binary
     */
    public static function execute($operand, &$AX, &$BX, $pc_address)
    {
        $operand = str_replace(' ', '', $operand);
        $memory = Memory::where('address', $operand)->first();

        $BX = $memory
            ? bindec(str_replace(' ', '', $memory->value ?? $memory->instruction ?? '00000000'))
            : 0;

        $AX += $BX;
    }
}
