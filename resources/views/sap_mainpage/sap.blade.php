@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">SAP Simulator</h2>

    <div class="row">
        {{-- LEFT: SAP Simulator --}}
        <div class="col-md-8">
            {{-- Show AX and BX --}}
            <div class="mb-3">
                <strong>AX:</strong> {{ session('AX', 0) }} &nbsp;&nbsp;
                <strong>BX:</strong> {{ session('BX', 0) }}
            </div>

            {{-- Control Buttons --}}
            <div class="d-flex gap-3 mb-4">
                <form method="POST" action="{{ route('sap.step') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary" {{ session('done') ? 'disabled' : '' }}>
                        Next Step
                    </button>
                </form>
                <form method="POST" action="{{ route('sap.reset') }}">
                    @csrf
                    <button type="submit" class="btn btn-warning">
                        Reset Session
                    </button>
                </form>
                <form method="POST" action="{{ route('sap.runAll') }}">
                    @csrf
                    <button type="submit" class="btn btn-success" {{ session('done') ? 'disabled' : '' }}>
                        Run All
                    </button>
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

            {{-- Animated Instruction Flow --}}
            @if(session('execution_flow'))
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-dark text-white">Instruction Flow Animation</div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-3">
                            @foreach(session('execution_flow') as $step => $value)
                                <div class="border rounded p-2 bg-light text-center animate-step">
                                    <strong>{{ strtoupper($step) }}</strong><br>
                                    <span class="text-muted">{{ $value }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

        </div>

        {{-- RIGHT: Memory Table --}}
        <div class="col-md-4">
            <h4>Memory Contents</h4>
            <table class="table table-bordered table-sm">
                <thead class="table-secondary">
                    <tr>
                        <th>Address</th>
                        <th>Instruction</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(App\Models\Memory::orderBy('address')->get() as $mem)
                        <tr>
                            <td>{{ $mem->address }}</td>
                            <td>{{ $mem->instruction }}</td>
                            <td>{{ $mem->value ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

{{-- OUT MODAL --}}
@if(session('out_display'))
    <div class="modal fade show" id="outModal" tabindex="-1" aria-modal="true" style="display: block; background: rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">OUT Instruction Output</h5>
                </div>
                <div class="modal-body">
                    <p><strong>Program Counter:</strong> {{ session('out_display.PC') }}</p>
                    <p><strong>AX:</strong> {{ session('out_display.AX') }}</p>
                    <p><strong>BX:</strong> {{ session('out_display.BX') }}</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="{{ route('sap.clear.out') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Close</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif

@push('styles')
<style>
    .animate-step {
        animation: popIn 0.5s ease forwards;
        opacity: 0;
    }
    @keyframes popIn {
        from { transform: scale(0.8); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }
</style>
@endpush
