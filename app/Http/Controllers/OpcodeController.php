<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Opcode;

class OpcodeController extends Controller
{
    /**
     * Optional: Display separate opcode editing form (not used if embedded in SAP view).
     */
    public function form()
    {
        $opcodes = Opcode::orderBy('name')->get();
        return view('sap_mainpage.opcode_edit', compact('opcodes'));
    }

    /**
     * Handle updating of existing opcode records.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'opcodes' => 'required|array',
        ]);

        foreach ($validated['opcodes'] as $id => $fields) {
            Opcode::find($id)?->update([
                'name'  => $fields['name'],
                'value' => $fields['value']
            ]);
        }

        return redirect()->route('sap.view')->with('success', 'Opcodes updated successfully.');
    }

    /**
     * Handle addition of a new opcode.
     */
    public function add(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|unique:opcodes,name',
            'value' => 'required|regex:/^[0-1]{4}$/'
        ]);

        Opcode::create($data);

        return redirect()->route('sap.view')->with('success', 'New opcode added.');
    }
}
