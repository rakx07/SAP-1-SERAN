<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Session;
use App\Models\ExecutionLog;

class OutController extends Controller
{
    /**
     * Simulate OUT instruction: display AX and BX in modal.
     */
    public static function execute($address, $AX, $BX)
    {
        // Save AX/BX at current PC for display in modal
        session([
            'out_display' => [
                'AX' => $AX,
                'BX' => $BX,
                'PC' => $address,
            ]
        ]);

        // Add execution log
        ExecutionLog::create([
            'pc_address' => $address,
            'active_controller' => 'OUT',
            'step' => "PC {$address}",
            'description' => 'OUT executed – AX and BX shown to user'
        ]);
    }
}
