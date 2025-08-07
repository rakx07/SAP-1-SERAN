<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Opcode;

class OpcodeController extends Controller
{
    /**
     * Display the opcode editing form.
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
        // Step 1: Validate format first
        $data = $request->validate([
            'opcodes' => 'required|array',
            'opcodes.*' => ['required', 'string', 'regex:/^[01]{4}$/'],
        ]);

        // Step 2: Check for duplicate binary values
        $values = array_values($data['opcodes']);
        $duplicates = array_unique(array_diff_assoc($values, array_unique($values)));

        if (!empty($duplicates)) {
            $dupesStr = implode(', ', $duplicates);
            return redirect()->route('sap.opcodes.form')
                             ->withErrors([
                                 'opcodes' => "⚠️ Duplicated opcode values found: {$dupesStr}."
                             ])
                             ->withInput();
        }

        // Step 3: Save updates if valid
        foreach ($data['opcodes'] as $id => $value) {
            $opcode = Opcode::find($id);
            if ($opcode) {
                $opcode->value = $value;
                $opcode->save();
            }
        }

        return redirect()->route('sap.opcodes.form')
                         ->with('success', 'Opcodes updated successfully.');
    }

    /**
     * Handle addition of a new opcode.
     */
    public function add(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|unique:opcodes,name',
            'value' => 'required|regex:/^[01]{4}$/'
        ]);

        Opcode::create($data);

        return redirect()->route('sap.opcodes.form')
                         ->with('success', 'New opcode added.');
    }
}
