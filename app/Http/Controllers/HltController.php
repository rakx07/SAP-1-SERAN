<?php

namespace App\Http\Controllers;

class HltController extends Controller
{
    /**
     * Executes the HLT instruction: Marks simulation as complete.
     *
     * @param string $pc_address The current PC address
     */
    public static function execute($pc_address)
    {
        // Set AX and BX as-is for record keeping (optional)
        $AX = session('AX', 0);
        $BX = session('BX', 0);

        session([
            "AX_{$pc_address}" => $AX,
            "BX_{$pc_address}" => $BX,
            'done' => true // 💡 critical: stops RunAll loop and disables UI
        ]);
    }
}
