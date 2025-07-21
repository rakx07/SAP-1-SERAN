@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">SAP Simulator</h2>

    <div class="row">
        {{-- LEFT: Simulator Control Panel --}}
        <div class="col-md-8">
            <div class="mb-3">
                <strong>AX:</strong> {{ session('AX', 0) }} &nbsp;&nbsp;
                <strong>BX:</strong> {{ session('BX', 0) }}
            </div>

            <div class="d-flex gap-3 mb-4">
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

        {{-- RIGHT: Animated Data Flow --}}
        {{-- RIGHT: SAP Layout Animated Flow --}}
<div class="col-md-4">
    <h4>Instruction Flow</h4>

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
            margin: 10px auto;
            font-family: monospace;
            font-size: 14px;
            background-color: #eee;
            box-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .sap-label {
            font-weight: bold;
        }

        .highlight {
            border-color: red;
            box-shadow: 0 0 10px red;
        }

        .sap-architecture {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            justify-content: space-around;
        }

        .sap-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .sap-box .value {
            font-size: 16px;
            margin-top: 4px;
            color: #000;
        }
    </style>

    <div class="sap-architecture">
        <div class="sap-group">
            <div class="sap-box" id="box-pc"><div class="sap-label">PC</div><div class="value">{{ session('execution_flow.PC') }}</div></div>
            <div class="sap-box" id="box-mar"><div class="sap-label">MAR</div><div class="value">{{ session('execution_flow.MAR1') ?? session('execution_flow.MAR2') }}</div></div>
            <div class="sap-box" id="box-ram"><div class="sap-label">ROM</div><div class="value">{{ session('execution_flow.ROM1') ?? session('execution_flow.ROM2') }}</div></div>
        </div>
        <div class="sap-group">
            <div class="sap-box" id="box-bus"><div class="sap-label">IR</div><div class="value">{{ session('execution_flow.IR') }}</div></div>
            <div class="sap-box" id="box-cu"><div class="sap-label">CU</div><div class="value">{{ session('execution_flow.CU') }}</div></div>
        </div>
        <div class="sap-group">
            <div class="sap-box" id="box-areg"><div class="sap-label">AX</div><div class="value">{{ session('execution_flow.AX') }}</div></div>
            <div class="sap-box" id="box-breg"><div class="sap-label">BX</div><div class="value">{{ session('execution_flow.BX') }}</div></div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const flowOrder = [
                'box-pc', 'box-mar', 'box-ram', 'box-bus',
                'box-cu', 'box-areg', 'box-breg'
            ];

            let index = 0;

            function animateNext() {
                if (index >= flowOrder.length) return;
                const box = document.getElementById(flowOrder[index]);
                if (box) {
                    box.classList.add('highlight');
                    setTimeout(() => {
                        box.classList.remove('highlight');
                        index++;
                        animateNext();
                    }, 1000); // 1 second delay
                }
            }

            animateNext();
        });
    </script>
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

@push('styles')
<style>
    .highlight-box { stroke-width: 5; stroke: red !important; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", () => {
    const flow = @json(session('execution_flow', []));
    if (!Object.keys(flow).length) return;

    const sequence = ['PC', 'MAR1', 'ROM1', 'IR', 'CU', 'MAR2', 'ROM2', 'AX', 'BX'];

    let i = 0;

    function animateStep() {
        if (i >= sequence.length) return;

        const key = sequence[i];
        const boxId = key.replace(/[12]/g, '');
        const value = flow[key] ?? '';

        const label = document.getElementById('flow' + boxId);
        const box = document.getElementById('box' + boxId);

        if (label && value !== '') {
            label.textContent = value;
            box.classList.add('highlight-box');

            setTimeout(() => {
                box.classList.remove('highlight-box');
                i++;
                animateStep();
            }, 1000); // 1 second delay
        } else {
            i++;
            animateStep();
        }
    }

    animateStep();
});
</script>
@endpush
