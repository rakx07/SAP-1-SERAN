<?php

namespace App\Http\Controllers;

use App\Models\Memory;

class AddController extends Controller
{
    /**
     * Simulate ADD instruction
     */
    public static function execute($operand, &$AX, &$BX, $pc_address)
    {
        $operand = str_replace(' ', '', $operand); // clean binary
        $memory = Memory::where('address', $operand)->first();
        $BX = $memory && $memory->value
            ? bindec(str_replace(' ', '', $memory->value))
            : 0;

        $AX = $AX + $BX;

        // Save AX/BX per PC
        session([
            "AX_{$pc_address}" => $AX,
            "BX_{$pc_address}" => $BX,
            'AX' => $AX,
            'BX' => $BX
        ]);
    }
}
