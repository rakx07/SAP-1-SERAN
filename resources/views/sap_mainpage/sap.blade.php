@extends('layouts.app')

@section('content')
<div class="container position-relative">
    <h2 class="mb-4 text-center">SAP Simulator</h2>

    <div class="row">
        {{-- LEFT: Controls & Execution Trace --}}
        <div class="col-md-8">
            <div class="mb-3">
                <strong>AX:</strong> {{ session('AX', 0) }} &nbsp;&nbsp;
                <strong>BX:</strong> {{ session('BX', 0) }}
            </div>

            <div class="d-flex gap-3 mb-4 flex-wrap">
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

            {{-- Execution Log --}}
            <h4>Execution Trace</h4>
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>PC</th>
                        <th>Controller</th>
                        <th>AX</th>
                        <th>BX</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(App\Models\ExecutionLog::orderBy('pc_address')->get() as $log)
                        <tr>
                            <td>{{ $log->pc_address }}</td>
                            <td>{{ $log->active_controller }}</td>
                            <td>{{ session("AX_{$log->pc_address}", 0) }}</td>
                            <td>{{ session("BX_{$log->pc_address}", 0) }}</td>
                            <td>
                                @foreach(explode(' | ', $log->description) as $status)
                                    @php
                                        $parts = explode(':', $status);
                                        $controller = trim($parts[0]);
                                        $state = trim($parts[1]);
                                        $class = $state === 'active' ? 'bg-success text-white px-2 py-1 rounded' : 'bg-warning text-dark px-2 py-1 rounded';
                                    @endphp
                                    <span class="{{ $class }}">{{ $controller }}: {{ $state }}</span>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- RIGHT: SAP Layout with Animated Flow --}}
        <div class="col-md-4 position-relative">
            <h4 class="text-center">Instruction Flow</h4>

            <style>
                .sap-box {
                    width: 140px;
                    height: 60px;
                    border: 2px solid #333;
                    border-radius: 8px;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    margin: 20px auto;
                    font-family: monospace;
                    font-size: 14px;
                    background-color: #eee;
                    box-shadow: 2px 2px 4px rgba(0,0,0,0.2);
                    transition: all 0.3s ease;
                    position: relative;
                    z-index: 1;
                }

                .sap-label {
                    font-weight: bold;
                }

                .sap-box .value {
                    font-size: 16px;
                    margin-top: 4px;
                    color: #000;
                }

                .sap-architecture {
                    display: flex;
                    flex-wrap: wrap;
                    justify-content: center;
                }

                .sap-group {
                    display: flex;
                    flex-direction: column;
                    gap: 5px;
                    margin: 0 10px;
                }

                .sap-line {
                    stroke: #aaa;
                    stroke-width: 2;
                    fill: none;
                }

                .ctrl-signal {
                    display: inline-block;
                    padding: 5px 10px;
                    font-family: monospace;
                    background: #eee;
                    border: 2px solid #ccc;
                    border-radius: 4px;
                    font-size: 14px;
                    color: #444;
                    transition: 0.3s all ease;
                }

                .ctrl-signal.active {
                    background: lightgreen;
                    border-color: green;
                    font-weight: bold;
                }

                .control-lines {
                    margin-top: 20px;
                    text-align: center;
                }
            </style>

            <div class="sap-architecture">
                <div class="sap-group">
                    <div class="sap-box" id="box-pc">
                        <div class="sap-label">PC</div>
                        <div class="value">{{ session('execution_flow.PC') }}</div>
                    </div>
                    <div class="sap-box" id="box-mar">
                        <div class="sap-label">MAR</div>
                        <div class="value">{{ session('execution_flow.MAR1') ?? session('execution_flow.MAR2') }}</div>
                    </div>
                    <div class="sap-box" id="box-ram">
                        <div class="sap-label">RAM</div>
                        <div class="value">{{ session('execution_flow.ROM1') ?? session('execution_flow.ROM2') }}</div>
                    </div>
                </div>
                <div class="sap-group">
                    <div class="sap-box" id="box-bus">
                        <div class="sap-label">IR</div>
                        <div class="value">{{ session('execution_flow.IR') }}</div>
                    </div>
                    <div class="sap-box" id="box-cu">
                        <div class="sap-label">CU</div>
                        <div class="value">{{ session('execution_flow.CU') }}</div>
                    </div>
                </div>
                <div class="sap-group">
                    <div class="sap-box" id="box-areg">
                        <div class="sap-label">AX</div>
                        <div class="value">{{ session('execution_flow.AX') }}</div>
                    </div>
                    <div class="sap-box" id="box-breg">
                        <div class="sap-label">BX</div>
                        <div class="value">{{ session('execution_flow.BX') }}</div>
                    </div>
                </div>
            </div>

            {{-- SVG ARROWS --}}
            <svg width="100%" height="400" style="position:absolute; top:0; left:0; z-index:0;">
                <defs>
                    <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="0" refY="3.5" orient="auto">
                        <polygon points="0 0, 10 3.5, 0 7" fill="red"/>
                    </marker>
                </defs>
                <line id="arrow-pc-mar" x1="120" y1="90" x2="120" y2="150" class="sap-line" marker-end="url(#arrowhead)" />
                <line id="arrow-mar-ram" x1="120" y1="210" x2="120" y2="270" class="sap-line" marker-end="url(#arrowhead)" />
                <line id="arrow-ram-ir" x1="140" y1="290" x2="200" y2="180" class="sap-line" marker-end="url(#arrowhead)" />
                <line id="arrow-ir-cu" x1="240" y1="180" x2="240" y2="240" class="sap-line" marker-end="url(#arrowhead)" />
                <line id="arrow-cu-ax" x1="240" y1="260" x2="180" y2="340" class="sap-line" marker-end="url(#arrowhead)" />
                <line id="arrow-cu-bx" x1="240" y1="260" x2="300" y2="340" class="sap-line" marker-end="url(#arrowhead)" />
            </svg>

            {{-- CONTROL SIGNALS --}}
            <div class="control-lines">
                <h5>Control Signals</h5>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    @foreach(['Cp', 'Ep', 'Lm', 'CE', 'Li', 'Ei', 'La', 'Ea', 'Su', 'Eu', 'Lb', 'Lo'] as $ctrl)
                        <span class="ctrl-signal" id="ctrl-{{ strtolower($ctrl) }}">{{ $ctrl }}</span>
                    @endforeach
                </div>
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

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
<script>
    function toggleControlSignal(id, active = true) {
        const el = document.getElementById('ctrl-' + id.toLowerCase());
        if (!el) return;
        el.classList.toggle('active', active);
    }

    document.addEventListener("DOMContentLoaded", function () {
        const boxes = [
            '#box-pc',
            '#box-mar',
            '#box-ram',
            '#box-bus',
            '#box-cu',
            '#box-areg',
            '#box-breg'
        ];

        const arrows = [
            '#arrow-pc-mar',
            '#arrow-mar-ram',
            '#arrow-ram-ir',
            '#arrow-ir-cu',
            '#arrow-cu-ax',
            '#arrow-cu-bx'
        ];

        const controlMap = ['Cp', 'Ep', 'Lm', 'CE', 'La', 'Ea', 'Lb'];

        const tl = gsap.timeline({ delay: 0.5 });

        boxes.forEach((selector, i) => {
            const arrow = arrows[i] ?? null;
            const ctrl = controlMap[i] ?? null;

            if (arrow) {
                tl.to(arrow, {
                    stroke: 'red',
                    strokeWidth: 4,
                    duration: 0.3
                }, '+=0.1');
            }

            if (ctrl) {
                tl.call(() => toggleControlSignal(ctrl, true));
            }

            tl.to(selector, {
                duration: 0.5,
                boxShadow: '0 0 20px red',
                borderColor: 'red',
                scale: 1.1,
                ease: 'power1.inOut'
            });

            tl.to(selector, {
                duration: 0.4,
                boxShadow: 'none',
                borderColor: '#333',
                scale: 1.0,
                ease: 'power1.inOut'
            });

            if (ctrl) {
                tl.call(() => toggleControlSignal(ctrl, false));
            }

            if (arrow) {
                tl.to(arrow, {
                    stroke: '#aaa',
                    strokeWidth: 2,
                    duration: 0.2
                }, '-=0.2');
            }
        });
    });
</script>
@endpush
