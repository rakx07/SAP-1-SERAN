<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Memory;
use App\Models\Opcode;
use App\Models\ExecutionLog;
use App\Imports\MemoryImport;
use Maatwebsite\Excel\Facades\Excel;

use App\Http\Controllers\AddController;
use App\Http\Controllers\SubtractController;
use App\Http\Controllers\OutController;
use App\Http\Controllers\HltController;

class SAPController extends Controller
{
    public function view()
    {
        if (Opcode::count() === 0) {
            $defaultOpcodes = [
                ['name' => 'ADD', 'value' => '0101'],
                ['name' => 'SUB', 'value' => '0010'],
                ['name' => 'OUT', 'value' => '1100'],
                ['name' => 'HLT', 'value' => '1111'],
                ['name' => 'LDA', 'value' => '0000'],
            ];
            foreach ($defaultOpcodes as $opcode) {
                Opcode::create($opcode);
            }
        }

        $opcodes = Opcode::orderBy('name')->get();
        return view('sap_mainpage.sap', compact('opcodes'));
    }

    public function uploadForm()
    {
        return view('sap_mainpage.upload');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

        Memory::truncate();
        Excel::import(new MemoryImport, $request->file('file'));

        session(['PC' => 0, 'AX' => 0, 'BX' => 0, 'done' => false]);
        ExecutionLog::truncate();

        return redirect()->route('sap.view')->with('success', 'Instructions uploaded.');
    }

    public function step(Request $request = null)
{
    $PC = session('PC', 0);
    $AX = session('AX', 0);
    $BX = session('BX', 0);

    $address = str_pad(decbin($PC), 4, '0', STR_PAD_LEFT);
    $instruction = Memory::where('address', $address)->first();

    if (!$instruction || !$instruction->instruction) {
        session(['done' => true]);
        return redirect()->route('sap.view');
    }

    $binary = str_replace(' ', '', $instruction->instruction);
    if (strlen($binary) < 8) {
        session(['done' => true]);
        return redirect()->route('sap.view');
    }

    $opcodeBin = substr($binary, 0, 4);
    $operand = substr($binary, 4, 4);

    $opcodes = Opcode::pluck('value', 'name')->toArray();
    $active = '';

    $executionFlow = [
        'PC' => $address,
        'MAR1' => $address,
        'ROM1' => $instruction->instruction,
        'IR' => $binary,
        'CU' => $opcodeBin,
    ];

    if ($opcodeBin === $opcodes['LDA']) {
        $memory = Memory::where('address', $operand)->first();
        $value = $memory ? str_replace(' ', '', $memory->value ?? $memory->instruction) : '00000000';
        $AX = bindec($value);
        $executionFlow['MAR2'] = $operand;
        $executionFlow['ROM2'] = $value;
        $executionFlow['AX'] = $AX;
        $active = 'LDA';

    } elseif ($opcodeBin === $opcodes['ADD']) {
        $memory = Memory::where('address', $operand)->first();
        $BX = $memory ? bindec(str_replace(' ', '', $memory->value ?? $memory->instruction)) : 0;
        $AX = $AX + $BX;
        $executionFlow['MAR2'] = $operand;
        $executionFlow['ROM2'] = str_replace(' ', '', $memory->value ?? $memory->instruction);
        $executionFlow['AX'] = $AX;
        $executionFlow['BX'] = $BX;
        $active = 'ADD';

    } elseif ($opcodeBin === $opcodes['SUB']) {
        $memory = Memory::where('address', $operand)->first();
        $BX = $memory ? bindec(str_replace(' ', '', $memory->value ?? $memory->instruction)) : 0;
        $AX = $AX - $BX;
        $executionFlow['MAR2'] = $operand;
        $executionFlow['ROM2'] = str_replace(' ', '', $memory->value ?? $memory->instruction);
        $executionFlow['AX'] = $AX;
        $executionFlow['BX'] = $BX;
        $active = 'SUB';

    } elseif ($opcodeBin === $opcodes['OUT']) {
        $executionFlow['AX'] = $AX;
        $executionFlow['BX'] = $BX;
        $active = 'OUT';
        session([
            'out_display' => [
                'PC' => $address,
                'AX' => $AX,
                'BX' => $BX,
            ]
        ]);

    } elseif ($opcodeBin === $opcodes['HLT']) {
        $active = 'HLT';
        session(['done' => true]);
    }

    ExecutionLog::create([
        'pc_address' => $address,
        'active_controller' => $active,
        'step' => "PC {$address}",
        'description' => collect(['LDA', 'ADD', 'SUB', 'OUT', 'HLT'])
            ->map(fn($c) => "$c: " . ($c === $active ? 'active' : 'inactive'))
            ->implode(' | ')
    ]);

    session([
        'execution_flow' => $executionFlow,
        "AX_{$address}" => $AX,
        "BX_{$address}" => $BX,
        'AX' => $AX,
        'BX' => $BX,
        'PC' => $PC + 1
    ]);

    return redirect()->route('sap.view');
}


    public function runAll()
    {
        session(['PC' => 0, 'AX' => 0, 'BX' => 0, 'done' => false]);
        ExecutionLog::truncate();

        while (!session('done', false)) {
            $this->step(new Request());
        }

        return redirect()->route('sap.view');
    }

    public function reset()
    {
        session()->forget(['AX', 'BX', 'PC', 'done', 'out_display']);

        foreach (ExecutionLog::pluck('pc_address') as $address) {
            session()->forget("AX_{$address}");
            session()->forget("BX_{$address}");
        }

        ExecutionLog::truncate();

        return redirect()->route('sap.view')->with('success', 'Session reset.');
    }

    public function clearOutModal()
    {
        session()->forget('out_display');
        return redirect()->route('sap.view');
    }
}
