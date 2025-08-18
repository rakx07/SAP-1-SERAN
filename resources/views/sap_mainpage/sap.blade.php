@extends('layouts.app')

@section('content')
@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif
<div class="container-fluid">
    {{-- <h2 class="mb-4 text-center">SAP Simulator</h2> --}}

    <div class="row">
        {{-- LEFT COLUMN: controls, status, trace --}}
        <div class="col-lg-8 col-md-7 col-sm-12 mb-4">
            <div class="mb-3">
                <strong>AX (dec):</strong> {{ session('AX', 0) }}
                &nbsp;&nbsp;
                <strong>BX (dec):</strong> {{ session('BX', 0) }}
            </div>

            <div class="d-flex flex-wrap gap-3 mb-4">
                <form method="POST" action="{{ route('sap.step') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary" {{ session('done') ? 'disabled' : '' }}>
                        Next Step
                    </button>
                </form>

                <form method="POST" action="{{ route('sap.last.step') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary"
                        {{ empty(session('history', [])) ? 'disabled' : '' }}>
                        Previous Step
                    </button>
                </form>

                <form method="POST" action="{{ route('sap.reset') }}">
                    @csrf
                    <button type="submit" class="btn btn-warning">Reset Session</button>
                </form>

                <form method="POST" action="{{ route('sap.runAll') }}">
                    @csrf
                    <button type="submit" class="btn btn-success" {{ session('done') ? 'disabled' : '' }}>
                        Run All
                    </button>
                </form>

                {{-- STOP BUTTON (added) --}}
                <form id="stopRunAllForm">
                    <button type="button" class="btn btn-danger" id="stopRunAllBtn" disabled>
                        Stop
                    </button>
                </form>

                <form method="POST" action="{{ route('sap.flow.clear') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-dark">Clear Flow</button>
                </form>

                <form method="GET" action="{{ route('sap.upload.form') }}">
                    <button type="submit" class="btn btn-secondary">Upload Excel</button>
                </form>
            </div>

            @if(session('done'))
                <div class="alert alert-success">
                    Simulation complete. You may reset the session to run again.
                </div>
            @endif
            {{-- BOX 3: Memory Contents --}}
            <div class="container-fluid mt-4">
                <h4 class="mb-3 text-center">Memory Contents</h4>
                <div class="table-responsive" style="height: 155px; overflow-y: auto;">
                    <table class="table table-bordered table-hover table-sm">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-center">Address</th>
                                <th class="text-center">Instruction</th>
                                <th class="text-center">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(App\Models\Memory::orderBy('address')->get() as $mem)
                                <tr>
                                    <td class="text-center">{{ $mem->address }}</td>
                                    <td class="text-center">{{ $mem->instruction }}</td>
                                    <td class="text-center">
                                    <button class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editMemoryModal"
                                            data-id="{{ $mem->id }}"
                                            data-address="{{ $mem->address }}"
                                            data-instruction="{{ $mem->instruction }}"
                                            data-value="{{ $mem->value }}">
                                        Edit
                                    </button>
                                </td>   

                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Execution Trace --}}
            <h4>Execution Trace</h4>
            <div class="table-responsive" style="max-height:45vh; overflow-y:auto;">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center">PC</th>
                            <th>Controller</th>
                            <th class="text-center">AX (dec)</th>
                            <th class="text-center">BX (dec)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(App\Models\ExecutionLog::orderBy('pc_address')->get() as $log)
                            <tr>
                                <td class="text-center">{{ $log->pc_address }}</td>
                                <td>{{ $log->active_controller }}</td>
                                <td class="text-center">{{ session("AX_{$log->pc_address}", 0) }}</td>
                                <td class="text-center">{{ session("BX_{$log->pc_address}", 0) }}</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach(['LDA','ADD','SUB','OUT','HLT'] as $ctrl)
                                            @php
                                                $isActive = ($log->active_controller === $ctrl);
                                                $badgeClass = $isActive ? 'bg-success text-white' : 'bg-warning text-dark';
                                            @endphp
                                            <span class="badge {{ $badgeClass }} px-2 py-1">
                                                {{ $ctrl }}: {{ $isActive ? 'active' : 'inactive' }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- RIGHT COLUMN: architecture layout --}}
        <div class="col-lg-4 col-md-5 col-sm-12 mb-4">
            {{-- Micro-step badge --}}
            @php
                // Use display_step if available, else fallback to micro_step
                $step = (int) session('display_step', session('micro_step', -1));
                $microLabel = ($step < 0) ? 'START' : 'T' . $step;
                $isStart = ($step < 0);
            @endphp

            <h4 class="text-center">
                Instruction Flow
                @if($microLabel !== '')
                    <span class="badge bg-info ms-2">Micro-step: {{ $microLabel }}</span>
                @endif
            </h4>


            <style>
                .sap-architecture {
                    display: grid;
                    grid-gap: 10px;
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                }
                .sap-box {
                    border: 2px solid #333;
                    border-radius: 8px;
                    padding: 6px;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    font-family: monospace;
                    background-color: #eee;
                    box-shadow: 2px 2px 4px rgba(0,0,0,0.2);
                    transition: all 0.3s ease;
                }
                .sap-box.active {
                    box-shadow: 0 0 20px red;
                    border-color: red;
                    transform: scale(1.05);
                }
                .sap-label {
                    font-weight: bold;
                }
                .sap-box .value {
                    font-size: 16px;
                    margin-top: 4px;
                }
                .ctrl-signal {
                    display: inline-block;
                    padding: 4px 8px;
                    font-family: monospace;
                    background: #eee;
                    border: 2px solid #ccc;
                    border-radius: 4px;
                    font-size: 13px;
                    color: #444;
                    transition: 0.3s all ease;
                    margin: 2px;
                }
                .ctrl-signal.active {
                    background: lightgreen;
                    border-color: green;
                    font-weight: bold;
                }
                .ctrl-bar {
                    text-decoration: overline;
                }
            </style>

            @php
                // === sticky-aware helpers ===
                $flow   = session('execution_flow', []);
                $sticky = session('sticky_flow', []);
                $active = session('active_boxes', []);

                $show = function($key, $default = '----') use ($flow, $sticky) {
                    $v = $flow[$key] ?? null;
                    if ($v !== null && $v !== '' && $v !== '----' && $v !== '--------') return $v;
                    return array_key_exists($key, $sticky) ? $sticky[$key] : $default;
                };

                $show8 = function($key) use ($show) {
                    return $show($key, '--------');
                };

                // AX/BX binary using sticky fallback
                $axVal = $show('AX', null);
                $bxVal = $show('BX', null);
                $binAX = is_numeric($axVal) ? str_pad(decbin((int)$axVal), 8, '0', STR_PAD_LEFT)
                                            : (is_string($axVal) ? $axVal : 'XXXXXXXX');
                $binBX = is_numeric($bxVal) ? str_pad(decbin((int)$bxVal), 8, '0', STR_PAD_LEFT)
                                            : (is_string($bxVal) ? $bxVal : 'XXXXXXXX');

                // Optional: for bar signals
                $signals = ['Cp','Ep','Lm','Er','Li','Ei','La','Ea','Su','Eu','Lb','Lo','Hlt'];
                $labels  = [
                    'Cp','Ep','Lm̅','Er̅','Li̅','Ei̅','La̅','Ea','Su','Eu','Lb̅','Lo̅','Hlt'
                ];
                $states  = session('control_signals', []);

                // =====================
                // Control Vector 2 (new)
                // =====================
                $cv2 = null;
                if ($step === 0) {
                    $cv2 = '0101 1110 0011';
                } elseif ($step === 1) {
                    $cv2 = '0010 0110 0011';
                } elseif ($step === 2) {
                    $cv2 = '1011 1110 0011';
                } elseif ($step === 3) {
                    $cv2 = '0001 1010 0011';
                } elseif ($step === 4) {
                    $cv2 = '0010 1100 0011';
                } elseif ($step === 5) {
                    // Determine opcode (prefer live flow, else sticky)
                    $irLive   = $flow['INREG'] ?? ($flow['IR'] ?? null);
                    $irSticky = $sticky['INREG'] ?? ($sticky['IR'] ?? null);
                    $ir = is_string($irLive) && strlen($irLive) >= 4 ? $irLive : $irSticky;
                    $opcode = is_string($ir) && strlen($ir) >= 4 ? substr($ir, 0, 4) : null;
                    if ($opcode === '0000') { // LDA
                        $cv2 = '00 11 1110 0011';
                    } elseif ($opcode === '0101' || $opcode === '0010') { // ADD or SUB
                        $cv2 = '0011 1100 0111';
                    } else {
                        $cv2 = '-------- ---- ----';
                    }
                }
            @endphp

            <div class="sap-architecture">
                <div class="sap-box {{ in_array('box-pc', $active) ? 'active' : '' }}" id="box-pc">
                    <div class="sap-label">Program Counter</div>
                    <div class="value">{{ $show('PC','----') }}</div>
                </div>
                <div class="sap-box {{ in_array('box-mar', $active) ? 'active' : '' }}" id="box-mar">
                    <div class="sap-label">MAR</div>
                    <div class="value">{{ $show('MAR2', $show('MAR1','----')) }}</div>
                </div>
                <div class="sap-box {{ in_array('box-ram', $active) ? 'active' : '' }}" id="box-ram">
                    <div class="sap-label">RAM</div>
                    <div class="value">{{ $show('ROM2', $show('ROM1','--------')) }}</div>
                </div>
                <div class="sap-box {{ in_array('box-inputreg', $active) ? 'active' : '' }}" id="box-inputreg">
                    <div class="sap-label">Instruction Register</div>
                    <div class="value">{{ $show('INREG', $show('IR','--------')) }}</div>
                </div>
                <div class="sap-box {{ in_array('box-control', $active) ? 'active' : '' }}" id="box-control">
                    <div class="sap-label">Control Unit</div>
                    <div class="value">{{ $show('CU','----') }}</div>
                </div>
                <div class="sap-box {{ in_array('box-bus', $active) ? 'active' : '' }}" id="box-bus">
                    <div class="sap-label">Bus</div>
                    <div class="value">{{ $show('BUS','--------') }}</div>
                </div>
                <div class="sap-box {{ in_array('box-areg', $active) ? 'active' : '' }}" id="box-areg">
                    <div class="sap-label">A Register</div>
                    <div class="value">{{ $binAX }}</div>
                </div>
                <div class="sap-box {{ in_array('box-alureg', $active) ? 'active' : '' }}" id="box-alureg">
                    <div class="sap-label">ALU</div>
                    <div class="value">{{ $show('ALU','--------') }}</div>
                </div>
                <div class="sap-box {{ in_array('box-breg', $active) ? 'active' : '' }}" id="box-breg">
                    <div class="sap-label">B Register</div>
                    <div class="value">{{ $binBX }}</div>
                </div>
                <div class="sap-box {{ in_array('box-outputreg', $active) ? 'active' : '' }}" id="box-outputreg">
                    <div class="sap-label">Output Register</div>
                    <div class="value">{{ $show8('OUTREG') }}</div>
                </div>
                <div class="sap-box {{ in_array('box-binary', $active) ? 'active' : '' }}" id="box-binary">
                    <div class="sap-label">Binary Display</div>
                    <div class="value">{{ $show8('BINARY') }}</div>
                </div>
            </div>

            {{-- Control Signals --}}
            <div class="mt-3 text-center">
                <h5>Control Signals</h5>
                @foreach($signals as $i => $sig)
                    @php $on = $states[$sig] ?? false; @endphp
                    <span class="ctrl-signal {{ $on ? 'active' : '' }}">
                        {{ $labels[$i] }}
                    </span>
                @endforeach
            </div>
            
            {{-- Optional: Show the current binary vector for debugging --}}
            <!-- <div class="mt-2" style="font-family: monospace;">
                <strong>Control Vector:</strong>
                {{ implode('', array_map(fn($s) => (int)($states[$s] ?? 0), $signals)) }}
            </div> -->

            {{-- NEW: Control Vector 2 (per T0–T5 mapping) --}}
            <div class="mt-1" style="font-family: monospace;">
                <strong>Control Vector Updated:</strong>
                {{ $cv2 ?? '-------- ---- ----' }}
            </div>
        </div>
    </div>
</div>

{{-- OUT modal --}}
@if(session('out_display'))
<div class="modal fade show" id="outModal" tabindex="-1" aria-modal="true" style="display: block; background: rgba(0,0,0,0.5);">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">OUT Instruction Output</h5></div>
            <div class="modal-body">
                <p><strong>Program Counter:</strong> {{ session('out_display.PC') }}</p>
                <p><strong>AX:</strong> {{ session('out_display.AX') }}</p>
                <p><strong>BX:</strong> {{ session('out_display.BX') }}</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="{{ route('sap.clear.out') }}"> @csrf
                    <button type="submit" class="btn btn-primary">Close</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Edit Memory Modal -->
<div class="modal fade" id="editMemoryModal" tabindex="-1" aria-labelledby="editMemoryModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" id="editMemoryForm">
        @csrf
        @method('PUT')
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editMemoryModalLabel">Edit Memory Instruction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="memoryId">
                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" id="memoryAddress" class="form-control"
                           pattern="[0-9]{4}" title="Enter 4-digit address (e.g., 0001)" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Instruction (8-bit binary)</label>
                    <input type="text" name="instruction" id="memoryInstruction" class="form-control"
                           pattern="[01]{8}" title="Enter 8-bit binary (e.g., 01011010)" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('editMemoryModal');
    modal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const address = button.getAttribute('data-address');
        const instruction = button.getAttribute('data-instruction');

        // Populate modal fields
        document.getElementById('memoryId').value = id;
        document.getElementById('memoryAddress').value = address;
        document.getElementById('memoryInstruction').value = instruction;

        // Set form action
        document.getElementById('editMemoryForm').action = `/memory/${id}`;
    });
});
</script>

<!-- Run All behaves like auto-clicking Next Step with 500ms delay -->
<script>
(function () {
    const DELAY_MS = 500; // change speed here
    const CSRF = '{{ csrf_token() }}';
    const STEP_URL = "{{ route('sap.step') }}";
    const RUN_ALL_ACTION = "{{ route('sap.runAll') }}";
    const isDone = {{ session('done') ? 'true' : 'false' }};
    const autoRun = localStorage.getItem('sap_autoRun') === '1';

    async function postStepOnce() {
        return fetch(STEP_URL, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
    }

    function toggleStopButton(enable) {
        const btn = document.getElementById('stopRunAllBtn');
        if (btn) btn.disabled = !enable;
    }

    function scheduleNext() {
        if (localStorage.getItem('sap_autoRun') !== '1') {
            // auto-run stopped
            toggleStopButton(false);
            return;
        }
        if ({{ session('done') ? 'true' : 'false' }}) {
            localStorage.removeItem('sap_autoRun');
            toggleStopButton(false);
            return;
        }
        setTimeout(async () => {
            await postStepOnce();
            location.reload();
        }, DELAY_MS);
    }

    // Intercept the existing Run All form and turn it into auto-stepper
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (form.matches(`form[action="${RUN_ALL_ACTION}"]`)) {
            e.preventDefault();
            if (isDone) return;
            localStorage.setItem('sap_autoRun', '1');
            toggleStopButton(true);
            postStepOnce().then(() => location.reload());
        }
    });

    // Stop button click: clear the auto-run flag (no further steps will be scheduled)
    const stopBtn = document.getElementById('stopRunAllBtn');
    if (stopBtn) {
        stopBtn.addEventListener('click', function () {
            localStorage.removeItem('sap_autoRun');
            toggleStopButton(false);
        });
    }

    // Continue auto-run after each reload until done
    if (autoRun) {
        toggleStopButton(true);
        scheduleNext();
    } else {
        toggleStopButton(false);
    }
})();
</script>
@endsection
