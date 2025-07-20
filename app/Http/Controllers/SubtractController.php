<?php

namespace App\Http\Controllers;

use App\Models\Memory;

class SubtractController extends Controller
{
    /**
     * Simulate SUB instruction
     */
    public static function execute($operand, &$AX, &$BX, $pc_address)
    {
        $operand = str_replace(' ', '', $operand);
        $memory = Memory::where('address', $operand)->first();
        $BX = $memory && $memory->value
            ? bindec(str_replace(' ', '', $memory->value))
            : 0;

        $AX = $AX - $BX;

        session([
            "AX_{$pc_address}" => $AX,
            "BX_{$pc_address}" => $BX,
            'AX' => $AX,
            'BX' => $BX
        ]);
    }
}
