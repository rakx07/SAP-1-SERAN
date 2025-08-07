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
    // Create default opcodes if table is empty
    if (Opcode::count() === 0) {
        $defaults = [
            ['name' => 'ADD', 'value' => '0101'],
            ['name' => 'SUB', 'value' => '0010'],
            ['name' => 'OUT', 'value' => '1100'],
            ['name' => 'HLT', 'value' => '1111'],
            ['name' => 'LDA', 'value' => '0000'],
        ];
        foreach ($defaults as $opcode) {
            Opcode::create($opcode);
        }
    }

    // Force session to default to START (-1) if not yet initialized
    if (!session()->has('micro_step')) {
        session([
            'micro_step' => -1,
            'execution_flow' => [],
            'active_boxes' => [],
            'active_arrows' => [],
            'control_signals' => $this->sap1ControlSignals(-1),
        ]);
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
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv']);
        Memory::truncate();
        Excel::import(new MemoryImport, $request->file('file'));

        session([
            'PC' => 0,
            'AX' => 0,
            'BX' => 0,
            'micro_step' => -1, // Set to -1 for No-op initial
            'done' => false,
            'history' => [],
        ]);
        ExecutionLog::truncate();

        return redirect()->route('sap.view')->with('success', 'Instructions uploaded.');
    }

    /**
     * Core: SAP micro-steps and control signals (with T-1, bar logic)
     */
    public function step(Request $request = null)
    {
        // Stop if done
        if (session('done', false)) {
            return redirect()->route('sap.view');
        }

        // Push current state onto history for "Previous Step"
        $history = session('history', []);
        $history[] = [
            'PC'     => session('PC', 0),
            'AX'     => session('AX', 0),
            'BX'     => session('BX', 0),
            'micro_step' => session('micro_step', -1),
            'execution_flow'  => session('execution_flow', []),
            'control_signals' => session('control_signals', []),
            'active_boxes'    => session('active_boxes', []),
            'active_arrows'   => session('active_arrows', []),
            'instr_name'      => session('instr_name'),
            'instr_opcode_bin'  => session('instr_opcode_bin'),
            'instr_operand_bin'=> session('instr_operand_bin'),
            'current_pc_address' => session('current_pc_address'),
            'done'            => session('done', false),
        ];
        session(['history' => $history]);

        // Current registers and micro-step
        $PC   = session('PC', 0);
        $AX   = session('AX', 0);
        $BX   = session('BX', 0);
        $step = session('micro_step', -1);

        $execFlow  = session('execution_flow', []);
        $pcAddr    = str_pad(decbin($PC), 4, '0', STR_PAD_LEFT);
        $instrName = session('instr_name', null);
        $operandBin = session('instr_operand_bin', null);
        $opcodeBin  = session('instr_opcode_bin', null);

        // SAP-1 Control Signal Matrix
        $signalNames = ['Cp','Ep','Lm','Er','Li','Ei','La','Ea','Su','Eu','Lb','Lo','Hlt'];
        $signals = $this->sap1ControlSignals($step, $instrName);

        // T-1: Initial No Operation (all gray/inactive)
        if ($step === -1) {
                $activeSignals = array_fill_keys($signalNames, null); // All signals inactive (gray)
                [$boxes, $arrows] = $this->computeFlowHighlights(-1, null);

                session([
                    'execution_flow'    => [],
                    'control_signals'   => $this->buildControlSignalsWithBar($activeSignals),
                    'active_boxes'      => $boxes,
                    'active_arrows'     => $arrows,
                    'micro_step'        => -1, // ← View will display as "START"
                    'current_pc_address'=> $pcAddr,
                ]);

                // AFTER rendering, move to T0
                session(['micro_step' => 0]);

                return redirect()->route('sap.view');
            }

        // T0: Fetch MAR ← PC (EpLm = 1)
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
                'INREG' => null,
                'ALU'   => null,
                'OUTREG'=> null,
                'BINARY'=> null,
                'AX'    => $AX,
                'BX'    => $BX,
            ];
            $activeSignals = $signals;
            [$boxes,$arrows] = $this->computeFlowHighlights(0, null);

            session([
                'execution_flow'    => $execFlow,
                'control_signals'   => $this->buildControlSignalsWithBar($activeSignals),
                'active_boxes'      => $boxes,
                'active_arrows'     => $arrows,
                'micro_step'        => 1,
                'current_pc_address'=> $pcAddr,
            ]);
            return redirect()->route('sap.view');
        }

        // T1: Fetch IR ← (R ← MAR) (ErLi = 1)
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

            // decode instruction name
            $decoded = 'UNKNOWN';
            foreach (Opcode::pluck('value','name') as $name => $value) {
                if ($value === $opcodeBin) {
                    $decoded = $name;
                    break;
                }
            }

            // update flow for T1
            $execFlow['ROM1']  = $row->instruction;
            $execFlow['IR']    = $binary;
            $execFlow['CU']    = $opcodeBin;
            $execFlow['BUS']   = $binary;
            $execFlow['INREG'] = $binary;
            $execFlow['AX']    = $AX;
            $execFlow['BX']    = $BX;

            $activeSignals = $signals;
            [$boxes,$arrows] = $this->computeFlowHighlights(1, null);

            session([
                'execution_flow'    => $execFlow,
                'control_signals'   => $this->buildControlSignalsWithBar($activeSignals),
                'active_boxes'      => $boxes,
                'active_arrows'     => $arrows,
                'micro_step'        => 2,
                'instr_opcode_bin'  => $opcodeBin,
                'instr_operand_bin' => $operandBin,
                'instr_name'        => $decoded,
            ]);
            return redirect()->route('sap.view');
        }

        // T2: (PC) ← (PC) + 1, Cp = 1
        if ($step === 2) {
            $PC++;
            session(['PC' => $PC]);
            $execFlow['PC']  = str_pad(decbin($PC), 4, '0', STR_PAD_LEFT);
            $execFlow['BUS'] = null;
            $execFlow['AX']  = $AX;
            $execFlow['BX']  = $BX;

            $activeSignals = $signals;
            [$boxes,$arrows] = $this->computeFlowHighlights(2, null);

            session([
                'execution_flow'   => $execFlow,
                'control_signals'  => $this->buildControlSignalsWithBar($activeSignals),
                'active_boxes'     => $boxes,
                'active_arrows'    => $arrows,
                'micro_step'       => 3,
            ]);
            return redirect()->route('sap.view');
        }

        // T3: Execute cycle for each instruction
        if ($step === 3) {
            $instrName  = session('instr_name', 'UNKNOWN');
            $operandBin = session('instr_operand_bin', '0000');
            $execFlow['AX'] = $AX;
            $execFlow['BX'] = $BX;

            $activeSignals = $signals;
            if (in_array($instrName, ['LDA','ADD','SUB'])) {
                $execFlow['BUS']  = str_pad($operandBin, 8, '0', STR_PAD_LEFT);
                $execFlow['MAR2'] = $operandBin;
            } elseif ($instrName === 'OUT') {
                OutController::execute($pcAddr, $AX, $BX);
                $bits = str_pad(decbin($AX), 8, '0', STR_PAD_LEFT);
                $execFlow['BUS']    = $bits;
                $execFlow['OUTREG'] = $bits;
                $execFlow['BINARY'] = $bits;
            } elseif ($instrName === 'HLT') {
                HltController::execute($pcAddr);
                $execFlow['BUS'] = null;
            } else {
                $execFlow['BUS'] = null;
            }

            [$boxes,$arrows] = $this->computeFlowHighlights(3, $instrName);
            session([
                'execution_flow'   => $execFlow,
                'control_signals'  => $this->buildControlSignalsWithBar($activeSignals),
                'active_boxes'     => $boxes,
                'active_arrows'    => $arrows,
                'micro_step'       => 4,
                'done'             => ($instrName === 'HLT')
            ]);
            return redirect()->route('sap.view');
        }

        // T4: Read memory into A or B (or no op for OUT/HLT)
        if ($step === 4) {
            $instrName  = session('instr_name', 'UNKNOWN');
            $operandBin = session('instr_operand_bin', '0000');
            $execFlow['AX'] = $AX;
            $execFlow['BX'] = $BX;
            $activeSignals = $signals;

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
            } else {
                $execFlow['BUS'] = null;
            }

            [$boxes,$arrows] = $this->computeFlowHighlights(4, $instrName);
            session([
                'AX'               => $AX,
                'BX'               => $BX,
                'execution_flow'   => $execFlow,
                'control_signals'  => $this->buildControlSignalsWithBar($activeSignals),
                'active_boxes'     => $boxes,
                'active_arrows'    => $arrows,
                'micro_step'       => 5,
            ]);
            return redirect()->route('sap.view');
        }

        // T5: ALU for ADD/SUB, no-op for others
        if ($step === 5) {
            $instrName = session('instr_name', 'UNKNOWN');
            $execFlow['AX'] = $AX;
            $execFlow['BX'] = $BX;
            $activeSignals = $signals;

            if ($instrName === 'ADD') {
                $AX = ($AX + $BX) % 256;
                $execFlow['AX']   = $AX;
                $aluBits          = str_pad(decbin($AX), 8, '0', STR_PAD_LEFT);
                $execFlow['ALU']  = $aluBits;
                $execFlow['BUS']  = $aluBits;
            } elseif ($instrName === 'SUB') {
                $AX = (($AX - $BX) + 256) % 256;
                $execFlow['AX']   = $AX;
                $aluBits          = str_pad(decbin($AX), 8, '0', STR_PAD_LEFT);
                $execFlow['ALU']  = $aluBits;
                $execFlow['BUS']  = $aluBits;
            } else {
                $execFlow['BUS'] = null;
            }

            session(['AX' => $AX, 'BX' => $BX]);
            $pcAddr = session('current_pc_address', $pcAddr);
            session([
                "AX_{$pcAddr}" => $AX,
                "BX_{$pcAddr}" => $BX,
            ]);

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

            [$boxes,$arrows] = $this->computeFlowHighlights(5, $instrName);
            session([
                'execution_flow'   => $execFlow,
                'control_signals'  => $this->buildControlSignalsWithBar($activeSignals),
                'active_boxes'     => $boxes,
                'active_arrows'    => $arrows,
                'micro_step'       => 0,
                'instr_opcode_bin' => null,
                'instr_operand_bin'=> null,
                'instr_name'       => null,
                'current_pc_address'=> null,
            ]);
            return redirect()->route('sap.view');
        }

        // fail-safe
        session(['done' => true]);
        return redirect()->route('sap.view');
    }
    /**
     * Build SAP-1 control signals with bar (inversion) logic per microstep.
     * Green (active): bar signals = 0, normal signals = 1.
     */
    private function buildControlSignalsWithBar(array $active): array
    {
        // SAP-1 bar/normal convention:
        // Bar signals: ['Lm', 'Er', 'Li', 'Ei', 'La', 'Lb', 'Lo']
        //   - These are ACTIVE (green) if value == 0
        // Non-bar: ['Cp', 'Ep', 'Ea', 'Su', 'Eu', 'Hlt']
        //   - These are ACTIVE (green) if value == 1
        $bar = ['Lm','Er','Li','Ei','La','Lb','Lo'];
        $norm= ['Cp','Ep','Ea','Su','Eu','Hlt'];

        $states = [];
        foreach ($bar as $b)  $states[$b] = isset($active[$b]) ? ($active[$b] === 0) : false;
        foreach ($norm as $n) $states[$n] = isset($active[$n]) ? ($active[$n] === 1) : false;
        return $states;
    }

    /**
     * SAP-1 microstep control signals table.
     * Returns correct values for each microstep, as per your diagrams.
     * Each microstep is a 13-bit control vector: [Cp Ep Lm Er Li Ei La Ea Su Eu Lb Lo Hlt]
     * - Bar signals: 0=active, 1=inactive
     * - Non-bar: 1=active, 0=inactive
     */
    private function sap1ControlSignals($microstep, $instrName = null)
{
    // Default fetch & NOP cycles (T-1 to T2)
    $common = [
        -1 => [ 'Cp'=>0, 'Ep'=>0, 'Lm'=>1, 'Er'=>1, 'Li'=>1, 'Ei'=>1, 'La'=>1, 'Ea'=>0, 'Su'=>0, 'Eu'=>0, 'Lb'=>1, 'Lo'=>1, 'Hlt'=>0 ], // START / No Op
         0 => [ 'Cp'=>0, 'Ep'=>1, 'Lm'=>0, 'Er'=>1, 'Li'=>1, 'Ei'=>1, 'La'=>1, 'Ea'=>0, 'Su'=>0, 'Eu'=>0, 'Lb'=>1, 'Lo'=>1, 'Hlt'=>0 ], // T0
         1 => [ 'Cp'=>0, 'Ep'=>0, 'Lm'=>1, 'Er'=>0, 'Li'=>0, 'Ei'=>1, 'La'=>1, 'Ea'=>0, 'Su'=>0, 'Eu'=>0, 'Lb'=>1, 'Lo'=>1, 'Hlt'=>0 ], // T1
         2 => [ 'Cp'=>1, 'Ep'=>1, 'Lm'=>1, 'Er'=>1, 'Li'=>1, 'Ei'=>1, 'La'=>1, 'Ea'=>0, 'Su'=>0, 'Eu'=>0, 'Lb'=>1, 'Lo'=>1, 'Hlt'=>0 ], // T2
    ];

    // T3 → T5 depends on instruction
    if ($microstep === 3) {
        return match($instrName) {
            'LDA', 'ADD', 'SUB' => [ 'Cp'=>0, 'Ep'=>0, 'Lm'=>0, 'Er'=>1, 'Li'=>1, 'Ei'=>0, 'La'=>1, 'Ea'=>0, 'Su'=>0, 'Eu'=>0, 'Lb'=>1, 'Lo'=>1, 'Hlt'=>0 ],
            'OUT'               => [ 'Cp'=>0, 'Ep'=>0, 'Lm'=>1, 'Er'=>1, 'Li'=>1, 'Ei'=>1, 'La'=>1, 'Ea'=>1, 'Su'=>0, 'Eu'=>0, 'Lb'=>1, 'Lo'=>0, 'Hlt'=>0 ],
            'HLT'               => [ 'Cp'=>0, 'Ep'=>0, 'Lm'=>1, 'Er'=>1, 'Li'=>1, 'Ei'=>1, 'La'=>1, 'Ea'=>0, 'Su'=>0, 'Eu'=>0, 'Lb'=>1, 'Lo'=>1, 'Hlt'=>1 ],
            default             => $common[-1],
        };
    }

    if ($microstep === 4) {
        return match($instrName) {
            'LDA'               => [ 'Cp'=>0, 'Ep'=>0, 'Lm'=>1, 'Er'=>0, 'Li'=>1, 'Ei'=>1, 'La'=>0, 'Ea'=>0, 'Su'=>0, 'Eu'=>0, 'Lb'=>1, 'Lo'=>1, 'Hlt'=>0 ],
            'ADD', 'SUB'        => [ 'Cp'=>0, 'Ep'=>0, 'Lm'=>1, 'Er'=>0, 'Li'=>1, 'Ei'=>1, 'La'=>1, 'Ea'=>0, 'Su'=>0, 'Eu'=>0, 'Lb'=>0, 'Lo'=>1, 'Hlt'=>0 ],
            default             => $common[-1],
        };
    }

    if ($microstep === 5) {
        return match($instrName) {
            'ADD'               => [ 'Cp'=>0, 'Ep'=>0, 'Lm'=>1, 'Er'=>1, 'Li'=>1, 'Ei'=>1, 'La'=>0, 'Ea'=>0, 'Su'=>0, 'Eu'=>1, 'Lb'=>1, 'Lo'=>1, 'Hlt'=>0 ],
            'SUB'               => [ 'Cp'=>0, 'Ep'=>0, 'Lm'=>1, 'Er'=>1, 'Li'=>1, 'Ei'=>1, 'La'=>0, 'Ea'=>0, 'Su'=>1, 'Eu'=>1, 'Lb'=>1, 'Lo'=>1, 'Hlt'=>0 ],
            default             => $common[-1],
        };
    }

    return $common[$microstep] ?? $common[-1]; // fallback
}


    /**
     * Restore the previous micro-step state from history.
     */
    public function backStep()
    {
        $history = session('history', []);
        if (!empty($history)) {
            $prev = array_pop($history);
            session([
                'PC'               => $prev['PC'],
                'AX'               => $prev['AX'],
                'BX'               => $prev['BX'],
                'micro_step'       => $prev['micro_step'],
                'execution_flow'   => $prev['execution_flow'],
                'control_signals'  => $prev['control_signals'],
                'active_boxes'     => $prev['active_boxes'],
                'active_arrows'    => $prev['active_arrows'],
                'instr_name'       => $prev['instr_name'],
                'instr_opcode_bin' => $prev['instr_opcode_bin'],
                'instr_operand_bin'=> $prev['instr_operand_bin'],
                'current_pc_address'=> $prev['current_pc_address'],
                'done'             => $prev['done'],
                'history'          => $history,
            ]);
            session()->forget('out_display');
        }
        return redirect()->route('sap.view');
    }

    /**
     * Run all instructions to completion.
     */
    public function runAll()
    {
        session([
            'PC'        => 0,
            'AX'        => 0,
            'BX'        => 0,
            'micro_step'=> 0,
            'done'      => false,
            'history'   => [],
        ]);
        ExecutionLog::truncate();

        $maxSteps = Memory::count() * 6 + 10;
        $count = 0;
        while (!session('done', false) && $count < $maxSteps) {
            $this->step(new Request());
            $count++;
        }
        return redirect()->route('sap.view');
    }

    /**
     * Reset the simulator state completely.
     */
    public function reset()
    {
        session()->forget([
            'AX','BX','PC','done','micro_step','instr_opcode_bin',
            'instr_operand_bin','instr_name','current_pc_address',
            'execution_flow','control_signals','active_boxes','active_arrows',
            'out_display','history'
        ]);
        foreach (ExecutionLog::pluck('pc_address') as $addr) {
            session()->forget("AX_{$addr}");
            session()->forget("BX_{$addr}");
        }
        ExecutionLog::truncate();
        return redirect()->route('sap.view')->with('success', 'Session reset.');
    }

    public function clearOutModal()
    {
        session()->forget('out_display');
        return redirect()->route('sap.view');
    }

    public function clearFlow()
    {
        session()->forget(['execution_flow','control_signals','active_boxes','active_arrows']);
        return redirect()->route('sap.view');
    }

    /**
 * Determine which boxes to pop (highlight) for each micro-step and instruction.
 */
private function computeFlowHighlights(int $step, ?string $instrName): array
{
    $boxes = [];
    $arrows = []; // You can customize for animated arrows if needed
    switch ($step) {
        case -1: // T-1: Initial No-op
            $boxes = []; // no highlights
            break;
        case 0: // T0
            $boxes = ['box-pc','box-bus','box-mar'];
            break;
        case 1: // T1
            $boxes = ['box-mar','box-ram','box-bus','box-inputreg'];
            break;
        case 2: // T2
            $boxes = ['box-pc'];
            break;
        case 3:
            if (in_array($instrName, ['LDA','ADD','SUB'])) {
                $boxes = ['box-inputreg','box-bus','box-mar'];
            } elseif ($instrName === 'OUT') {
                $boxes = ['box-areg','box-bus','box-outputreg','box-binary'];
            } elseif ($instrName === 'HLT') {
                $boxes = ['box-control'];
            }
            break;
        case 4:
            if ($instrName === 'LDA') {
                $boxes = ['box-ram','box-bus','box-areg'];
            } elseif ($instrName === 'ADD' || $instrName === 'SUB') {
                $boxes = ['box-ram','box-bus','box-breg'];
            }
            break;
        case 5:
            if ($instrName === 'ADD' || $instrName === 'SUB') {
                $boxes = ['box-alureg','box-bus','box-areg'];
            }
            break;
    }
    return [$boxes, $arrows];
}
}
