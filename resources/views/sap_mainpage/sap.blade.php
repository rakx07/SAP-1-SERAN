@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <h2 class="mb-4 text-center">SAP Simulator</h2>

    <div class="row">
        {{-- LEFT COLUMN: controls, status and trace --}}
        <div class="col-lg-8 col-md-7 col-sm-12 mb-4">
            <div class="mb-3">
                <strong>AX (decimal):</strong> {{ session('AX', 0) }}
                &nbsp;&nbsp;
                <strong>BX (decimal):</strong> {{ session('BX', 0) }}
            </div>

            <div class="d-flex flex-wrap gap-3 mb-4">
                <form method="POST" action="{{ route('sap.step') }}"> @csrf
                    <button type="submit" class="btn btn-primary" {{ session('done') ? 'disabled' : '' }}>Next Step</button>
                </form>
                <form method="POST" action="{{ route('sap.reset') }}"> @csrf
                    <button type="submit" class="btn btn-warning">Reset Session</button>
                </form>
                <form method="POST" action="{{ route('sap.runAll') }}"> @csrf
                    <button type="submit" class="btn btn-success" {{ session('done') ? 'disabled' : '' }}>Run All</button>
                </form>
                <form method="POST" action="{{ route('sap.flow.clear') }}"> @csrf
                    <button type="submit" class="btn btn-outline-dark">Clear Flow</button>
                </form>
                <form method="GET" action="{{ route('sap.upload.form') }}">
                    <button type="submit" class="btn btn-secondary">Upload Excel</button>
                </form>
            </div>

            @if(session('done'))
                <div class="alert alert-success">Simulation complete. You may reset the session to run again.</div>
            @endif

            {{-- Execution Trace Table in a scrollable container --}}
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
                                            $isActive = $log->active_controller === $ctrl;
                                            $badgeClass = $isActive
                                                ? 'bg-success text-white'
                                                : 'bg-warning text-dark';
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

        {{-- RIGHT COLUMN: architecture display with responsive grid --}}
        <div class="col-lg-4 col-md-5 col-sm-12 mb-4">
            <h4 class="text-center">Instruction Flow</h4>

            <style>
                /* responsive grid layout for all boxes */
                .sap-architecture {
                    display: grid;
                    grid-gap: 10px;
                    /* auto-fit as many columns as will fit in the container, minimum 150px each */
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
                    color: #000;
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
            </style>

            @php
                $execFlow   = session('execution_flow', []);
                $activeBoxes= session('active_boxes', []);
                $binAX = isset($execFlow['AX']) && $execFlow['AX'] !== null
                    ? str_pad(decbin(intval($execFlow['AX'])), 8, '0', STR_PAD_LEFT)
                    : 'XXXXXXXX';
                $binBX = isset($execFlow['BX']) && $execFlow['BX'] !== null
                    ? str_pad(decbin(intval($execFlow['BX'])), 8, '0', STR_PAD_LEFT)
                    : 'XXXXXXXX';
            @endphp

            <div class="sap-architecture">
                <div class="sap-box {{ in_array('box-pc', $activeBoxes) ? 'active' : '' }}" id="box-pc">
                    <div class="sap-label">Program Counter</div>
                    <div class="value">{{ $execFlow['PC'] ?? '----' }}</div>
                </div>
                <div class="sap-box {{ in_array('box-mar', $activeBoxes) ? 'active' : '' }}" id="box-mar">
                    <div class="sap-label">MAR</div>
                    <div class="value">{{ $execFlow['MAR2'] ?? ($execFlow['MAR1'] ?? '----') }}</div>
                </div>
                <div class="sap-box {{ in_array('box-ram', $activeBoxes) ? 'active' : '' }}" id="box-ram">
                    <div class="sap-label">RAM</div>
                    <div class="value">{{ $execFlow['ROM2'] ?? ($execFlow['ROM1'] ?? '--------') }}</div>
                </div>
                <div class="sap-box {{ in_array('box-inputreg', $activeBoxes) ? 'active' : '' }}" id="box-inputreg">
                    <div class="sap-label">Input Register</div>
                    <div class="value">{{ $execFlow['INREG'] ?? ($execFlow['IR'] ?? '--------') }}</div>
                </div>
                <div class="sap-box {{ in_array('box-control', $activeBoxes) ? 'active' : '' }}" id="box-control">
                    <div class="sap-label">Control Unit</div>
                    <div class="value">{{ $execFlow['CU'] ?? '----' }}</div>
                </div>
                <div class="sap-box {{ in_array('box-bus', $activeBoxes) ? 'active' : '' }}" id="box-bus">
                    <div class="sap-label">Bus</div>
                    <div class="value">{{ $execFlow['BUS'] ?? '--------' }}</div>
                </div>
                <div class="sap-box {{ in_array('box-areg', $activeBoxes) ? 'active' : '' }}" id="box-areg">
                    <div class="sap-label">A Register</div>
                    <div class="value">{{ $binAX }}</div>
                </div>
                <div class="sap-box {{ in_array('box-alureg', $activeBoxes) ? 'active' : '' }}" id="box-alureg">
                    <div class="sap-label">ALU</div>
                    <div class="value">{{ $execFlow['ALU'] ?? '--------' }}</div>
                </div>
                <div class="sap-box {{ in_array('box-breg', $activeBoxes) ? 'active' : '' }}" id="box-breg">
                    <div class="sap-label">B Register</div>
                    <div class="value">{{ $binBX }}</div>
                </div>
                <div class="sap-box {{ in_array('box-outputreg', $activeBoxes) ? 'active' : '' }}" id="box-outputreg">
                    <div class="sap-label">Output Register</div>
                    <div class="value">{{ $execFlow['OUTREG'] ?? '--------' }}</div>
                </div>
                <div class="sap-box {{ in_array('box-binary', $activeBoxes) ? 'active' : '' }}" id="box-binary">
                    <div class="sap-label">Binary Display</div>
                    <div class="value">{{ $execFlow['BINARY'] ?? '--------' }}</div>
                </div>
            </div>

            {{-- Control signals --}}
            <div class="mt-3 text-center">
                <h5>Control Signals</h5>
                @php
                    $signals = ['Cp','Ep','Lm','Er','Li','Ei','La','Ea','Su','Eu','Lb','Lo','Hlt'];
                    $states  = session('control_signals', []);
                @endphp
                @foreach($signals as $sig)
                    @php $isActive = $states[$sig] ?? false; @endphp
                    <span class="ctrl-signal {{ $isActive ? 'active' : '' }}" id="ctrl-{{ strtolower($sig) }}">{{ $sig }}</span>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

{{-- OUT Modal --}}
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
