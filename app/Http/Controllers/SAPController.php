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
    /**
     * Display the main SAP page and seed default opcodes if necessary.
     */
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

    /**
     * Import memory contents from an Excel file and reset simulator state.
     */
    public function upload(Request $request)
    {
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv']);
        Memory::truncate();
        Excel::import(new MemoryImport, $request->file('file'));

        session([
            'PC' => 0,
            'AX' => 0,
            'BX' => 0,
            'micro_step' => 0,
            'done' => false,
        ]);
        ExecutionLog::truncate();

        return redirect()->route('sap.view')->with('success', 'Instructions uploaded.');
    }

    /**
     * Perform the next micro‑step (T0..T5) of the SAP.  Compute bus, ALU and output values,
     * track active boxes for display, and update control signals.
     */
    public function step(Request $request = null)
    {
        if (session('done', false)) {
            return redirect()->route('sap.view');
        }

        // Current registers and micro-step
        $PC   = session('PC', 0);
        $AX   = session('AX', 0);
        $BX   = session('BX', 0);
        $step = session('micro_step', 0);

        // All control signals (including Hlt)
        $signalNames   = ['Cp','Ep','Lm','Er','Li','Ei','La','Ea','Su','Eu','Lb','Lo','Hlt'];
        $activeSignals = array_fill_keys($signalNames, false);

        // Execution flow dictionary
        $execFlow = session('execution_flow', []);
        $pcAddr   = str_pad(decbin($PC), 4, '0', STR_PAD_LEFT);
        $instrName   = session('instr_name', null);
        $operandBin  = session('instr_operand_bin', null);
        $opcodeBin   = session('instr_opcode_bin', null);

        /*
         * T0: PC -> Bus -> MAR
         */
        if ($step === 0) {
            $busValue = str_pad($pcAddr, 8, '0', STR_PAD_LEFT);
            $execFlow = [
                'PC'    => $pcAddr,
                'MAR1'  => $pcAddr,
                'ROM1'  => null,
                'IR'    => null,
                'CU'    => null,
                'MAR2'  => null,
                'ROM2'  => null,
                'BUS'   => $busValue,
                'INREG' => null,    // Input Register (IR)
                'ALU'   => null,    // ALU output
                'OUTREG'=> null,    // Output Register
                'BINARY'=> null,    // Binary Display
                'AX'    => $AX,
                'BX'    => $BX,
            ];
            $activeSignals['Ep'] = true;
            $activeSignals['Lm'] = true;
            [$boxes,$arrows] = $this->computeFlowHighlights(0, null);

            session([
                'execution_flow'    => $execFlow,
                'control_signals'   => $this->buildControlSignals($activeSignals),
                'active_boxes'      => $boxes,
                'active_arrows'     => $arrows,
                'micro_step'        => 1,
                'current_pc_address'=> $pcAddr,
            ]);
            return redirect()->route('sap.view');
        }

        /*
         * T1: MAR -> RAM -> Bus -> Input Register (IR)
         */
        if ($step === 1) {
            $row = Memory::where('address', $pcAddr)->first();
            if (!$row || !$row->instruction) {
                session(['done' => true]);
                return redirect()->route('sap.view');
            }
            $binary = str_replace(' ', '', $row->instruction);
            if (strlen($binary) < 8) {
                session(['done' => true]);
                return redirect()->route('sap.view');
            }

            $opcodeBin  = substr($binary, 0, 4);
            $operandBin = substr($binary, 4, 4);

            // Decode instruction
            $decodedName = 'UNKNOWN';
            foreach (Opcode::pluck('value','name') as $name => $value) {
                if ($value === $opcodeBin) {
                    $decodedName = $name;
                    break;
                }
            }

            // Update flow values
            $execFlow['ROM1']  = $row->instruction;
            $execFlow['IR']    = $binary;
            $execFlow['CU']    = $opcodeBin;
            $execFlow['BUS']   = $binary;          // bus carries instruction bits
            $execFlow['INREG'] = $binary;          // Input Register (IR)
            $execFlow['AX']    = $AX;
            $execFlow['BX']    = $BX;

            $activeSignals['Er'] = true;
            $activeSignals['Li'] = true;
            [$boxes,$arrows] = $this->computeFlowHighlights(1, null);

            session([
                'execution_flow'     => $execFlow,
                'control_signals'    => $this->buildControlSignals($activeSignals),
                'active_boxes'       => $boxes,
                'active_arrows'      => $arrows,
                'micro_step'         => 2,
                'instr_opcode_bin'   => $opcodeBin,
                'instr_operand_bin'  => $operandBin,
                'instr_name'         => $decodedName,
            ]);
            return redirect()->route('sap.view');
        }

        /*
         * T2: increment PC
         */
        if ($step === 2) {
            $PC++;
            session(['PC' => $PC]);

            $execFlow['PC'] = str_pad(decbin($PC), 4, '0', STR_PAD_LEFT);
            $execFlow['BUS']= null;
            $execFlow['AX'] = $AX;
            $execFlow['BX'] = $BX;

            $activeSignals['Cp'] = true;
            [$boxes,$arrows] = $this->computeFlowHighlights(2, null);

            session([
                'execution_flow'   => $execFlow,
                'control_signals'  => $this->buildControlSignals($activeSignals),
                'active_boxes'     => $boxes,
                'active_arrows'    => $arrows,
                'micro_step'       => 3,
            ]);
            return redirect()->route('sap.view');
        }

        /*
         * T3: load operand or output A or halt
         */
        if ($step === 3) {
            $instrName  = session('instr_name', 'UNKNOWN');
            $operandBin = session('instr_operand_bin', '0000');

            $execFlow['AX'] = $AX;
            $execFlow['BX'] = $BX;

            if (in_array($instrName, ['LDA','ADD','SUB'])) {
                // Operand address goes onto bus
                $execFlow['BUS']  = str_pad($operandBin, 8, '0', STR_PAD_LEFT);
                $execFlow['MAR2'] = $operandBin;
                $activeSignals['Lm'] = true;
                $activeSignals['Ei'] = true;

                [$boxes,$arrows] = $this->computeFlowHighlights(3, $instrName);
                session([
                    'execution_flow'   => $execFlow,
                    'control_signals'  => $this->buildControlSignals($activeSignals),
                    'active_boxes'     => $boxes,
                    'active_arrows'    => $arrows,
                    'micro_step'       => 4,
                ]);
            } elseif ($instrName === 'OUT') {
                // A register to bus and output register
                OutController::execute($pcAddr, $AX, $BX);
                $outputBits = str_pad(decbin($AX), 8, '0', STR_PAD_LEFT);
                $execFlow['BUS']    = $outputBits;
                $execFlow['OUTREG'] = $outputBits;
                $execFlow['BINARY'] = $outputBits;
                $activeSignals['Ea'] = true;
                $activeSignals['Lo'] = true;

                [$boxes,$arrows] = $this->computeFlowHighlights(3, $instrName);
                session([
                    'execution_flow'   => $execFlow,
                    'control_signals'  => $this->buildControlSignals($activeSignals),
                    'active_boxes'     => $boxes,
                    'active_arrows'    => $arrows,
                    'micro_step'       => 4,
                ]);
            } elseif ($instrName === 'HLT') {
                HltController::execute($pcAddr);
                $execFlow['BUS'] = null;
                $activeSignals['Hlt'] = true;

                [$boxes,$arrows] = $this->computeFlowHighlights(3, $instrName);
                session([
                    'execution_flow'   => $execFlow,
                    'control_signals'  => $this->buildControlSignals($activeSignals),
                    'active_boxes'     => $boxes,
                    'active_arrows'    => $arrows,
                    'done'             => true,
                ]);
            } else {
                $execFlow['BUS'] = null;
                [$boxes,$arrows] = $this->computeFlowHighlights(3, $instrName);
                session([
                    'execution_flow'   => $execFlow,
                    'control_signals'  => $this->buildControlSignals($activeSignals),
                    'active_boxes'     => $boxes,
                    'active_arrows'    => $arrows,
                    'micro_step'       => 4,
                ]);
            }
            return redirect()->route('sap.view');
        }

        /*
         * T4: memory read or none
         */
        if ($step === 4) {
            $instrName  = session('instr_name', 'UNKNOWN');
            $operandBin = session('instr_operand_bin', '0000');

            $execFlow['AX'] = $AX;
            $execFlow['BX'] = $BX;

            if ($instrName === 'LDA') {
                $memory = Memory::where('address', $operandBin)->first();
                $bits = '00000000';
                if ($memory) {
                    $tmp = $memory->value ?: $memory->instruction;
                    if ($tmp) {
                        $bits = str_replace(' ', '', $tmp);
                    }
                }
                $AX = bindec($bits);
                $execFlow['ROM2'] = $bits;
                $execFlow['AX']   = $AX;
                $execFlow['BUS']  = $bits;
                $activeSignals['Er'] = true;
                $activeSignals['La'] = true;

                [$boxes,$arrows] = $this->computeFlowHighlights(4, $instrName);
                session([
                    'AX'               => $AX,
                    'BX'               => $BX,
                    'execution_flow'   => $execFlow,
                    'control_signals'  => $this->buildControlSignals($activeSignals),
                    'active_boxes'     => $boxes,
                    'active_arrows'    => $arrows,
                    'micro_step'       => 5,
                ]);
            } elseif (in_array($instrName, ['ADD','SUB'])) {
                $memory = Memory::where('address', $operandBin)->first();
                $bits = '00000000';
                if ($memory) {
                    $tmp = $memory->value ?: $memory->instruction;
                    if ($tmp) {
                        $bits = str_replace(' ', '', $tmp);
                    }
                }
                $BX = bindec($bits);
                $execFlow['ROM2'] = $bits;
                $execFlow['BX']   = $BX;
                $execFlow['BUS']  = $bits;
                $activeSignals['Er'] = true;
                $activeSignals['Lb'] = true;

                [$boxes,$arrows] = $this->computeFlowHighlights(4, $instrName);
                session([
                    'AX'               => $AX,
                    'BX'               => $BX,
                    'execution_flow'   => $execFlow,
                    'control_signals'  => $this->buildControlSignals($activeSignals),
                    'active_boxes'     => $boxes,
                    'active_arrows'    => $arrows,
                    'micro_step'       => 5,
                ]);
            } else {
                // OUT and HLT: no bus activity at T4
                $execFlow['BUS'] = null;
                [$boxes,$arrows] = $this->computeFlowHighlights(4, $instrName);
                session([
                    'execution_flow'   => $execFlow,
                    'control_signals'  => $this->buildControlSignals($activeSignals),
                    'active_boxes'     => $boxes,
                    'active_arrows'    => $arrows,
                    'micro_step'       => 5,
                ]);
            }
            return redirect()->route('sap.view');
        }

        /*
         * T5: ALU operations or no‑op
         */
        if ($step === 5) {
            $instrName = session('instr_name', 'UNKNOWN');
            $execFlow['AX'] = $AX;
            $execFlow['BX'] = $BX;

            if ($instrName === 'ADD') {
                $AX = ($AX + $BX) % 256;
                $execFlow['AX']  = $AX;
                $aluBits         = str_pad(decbin($AX), 8, '0', STR_PAD_LEFT);
                $execFlow['ALU'] = $aluBits;
                $execFlow['BUS'] = $aluBits;
                $activeSignals['La'] = true;
                $activeSignals['Eu'] = true;
            } elseif ($instrName === 'SUB') {
                $AX = (($AX - $BX) + 256) % 256;
                $execFlow['AX']  = $AX;
                $aluBits         = str_pad(decbin($AX), 8, '0', STR_PAD_LEFT);
                $execFlow['ALU'] = $aluBits;
                $execFlow['BUS'] = $aluBits;
                $activeSignals['La'] = true;
                $activeSignals['Su'] = true;
                $activeSignals['Eu'] = true;
            } else {
                // LDA/OUT/HLT: no ALU action
                $execFlow['BUS'] = null;
            }

            session(['AX' => $AX, 'BX' => $BX]);

            // Save AX/BX values per instruction for trace
            $pcAddr = session('current_pc_address', $pcAddr);
            session([
                "AX_{$pcAddr}" => $AX,
                "BX_{$pcAddr}" => $BX,
            ]);

            // Log instruction completion
            if (in_array($instrName, ['LDA','ADD','SUB','OUT','HLT'])) {
                ExecutionLog::create([
                    'pc_address'       => $pcAddr,
                    'active_controller'=> $instrName,
                    'step'             => "PC {$pcAddr}",
                    'description'      => collect(['LDA','ADD','SUB','OUT','HLT'])
                        ->map(fn($c) => "$c: " . ($c === $instrName ? 'active' : 'inactive'))
                        ->implode(' | ')
                ]);
            }

            // Compute highlight boxes for final step
            [$boxes,$arrows] = $this->computeFlowHighlights(5, $instrName);
            session([
                'execution_flow'    => $execFlow,
                'control_signals'   => $this->buildControlSignals($activeSignals),
                'active_boxes'      => $boxes,
                'active_arrows'     => $arrows,
                'micro_step'        => 0,
                'instr_opcode_bin'  => null,
                'instr_operand_bin' => null,
                'instr_name'        => null,
                'current_pc_address'=> null,
            ]);

            return redirect()->route('sap.view');
        }

        // Should not get here under normal circumstances
        session(['done' => true]);
        return redirect()->route('sap.view');
    }

    /**
     * Run all instructions to completion.
     */
    public function runAll()
    {
        session([
            'PC' => 0,
            'AX' => 0,
            'BX' => 0,
            'micro_step' => 0,
            'done' => false,
        ]);
        ExecutionLog::truncate();

        // Estimate maximum micro-steps to prevent infinite loops
        $maxSteps = Memory::count() * 6 + 10;
        $count = 0;
        while (!session('done', false) && $count < $maxSteps) {
            $this->step(new Request());
            $count++;
        }
        return redirect()->route('sap.view');
    }

    /**
     * Reset the entire simulator state.
     */
    public function reset()
    {
        session()->forget([
            'AX','BX','PC','done','micro_step',
            'instr_opcode_bin','instr_operand_bin','instr_name',
            'current_pc_address','execution_flow','control_signals',
            'active_boxes','active_arrows','out_display'
        ]);
        foreach (ExecutionLog::pluck('pc_address') as $addr) {
            session()->forget("AX_{$addr}");
            session()->forget("BX_{$addr}");
        }
        ExecutionLog::truncate();
        return redirect()->route('sap.view')->with('success', 'Session reset.');
    }

    /**
     * Close the OUT modal.
     */
    public function clearOutModal()
    {
        session()->forget('out_display');
        return redirect()->route('sap.view');
    }

    /**
     * Clear the flow display.
     */
    public function clearFlow()
    {
        session()->forget('execution_flow');
        session()->forget('control_signals');
        session()->forget('active_boxes');
        session()->forget('active_arrows');
        return redirect()->route('sap.view');
    }

    /**
     * Build a map of control signals (true/false) from the active set.
     */
    private function buildControlSignals(array $active): array
    {
        $names = ['Cp','Ep','Lm','Er','Li','Ei','La','Ea','Su','Eu','Lb','Lo','Hlt'];
        $out   = [];
        foreach ($names as $name) {
            $out[$name] = $active[$name] ?? false;
        }
        return $out;
    }

    /**
     * Decide which boxes should pop for each micro‑step.
     * Boxes: PC, MAR, RAM, InputReg, Control, Bus, AReg, ALU, BReg, OutputReg, BinaryDisplay.
     * Arrows are included for compatibility but no longer displayed.
     */
    private function computeFlowHighlights(int $step, ?string $instrName): array
    {
        $boxes  = [];
        $arrows = []; // kept for possible future use

        switch ($step) {
            case 0:
                // PC to Bus to MAR
                $boxes = ['box-pc','box-bus','box-mar'];
                break;
            case 1:
                // MAR -> RAM -> Bus -> Input Register
                $boxes = ['box-mar','box-ram','box-bus','box-inputreg'];
                break;
            case 2:
                // PC increments
                $boxes = ['box-pc'];
                break;
            case 3:
                if (in_array($instrName, ['LDA','ADD','SUB'])) {
                    // InputReg -> Bus -> MAR
                    $boxes = ['box-inputreg','box-bus','box-mar'];
                } elseif ($instrName === 'OUT') {
                    // AReg -> Bus -> OutputReg -> BinaryDisplay
                    $boxes = ['box-areg','box-bus','box-outputreg','box-binary'];
                } elseif ($instrName === 'HLT') {
                    // Control Unit
                    $boxes = ['box-control'];
                }
                break;
            case 4:
                if ($instrName === 'LDA') {
                    // RAM -> Bus -> A Register
                    $boxes = ['box-ram','box-bus','box-areg'];
                } elseif ($instrName === 'ADD' || $instrName === 'SUB') {
                    // RAM -> Bus -> B Register
                    $boxes = ['box-ram','box-bus','box-breg'];
                }
                break;
            case 5:
                if ($instrName === 'ADD' || $instrName === 'SUB') {
                    // ALU -> Bus -> A Register (results in A)
                    $boxes = ['box-alureg','box-bus','box-areg'];
                }
                // no action for LDA/OUT/HLT
                break;
        }
        return [$boxes,$arrows];
    }
}
