@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="mb-4">SAP Simulator: Edit Opcode Values</h2>

    {{-- Form to Update Opcodes --}}
    <form method="POST" action="{{ route('sap.opcodes.update') }}">
        @csrf

        <div class="card shadow-sm border">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Edit SAP Opcode Values</h5>
            </div>

            <div class="card-body">
                <div class="row">
                    @foreach($opcodes as $opcode)
                        <div class="col-lg-4 col-md-6 col-sm-12 mb-4">
                            <label class="form-label fw-bold">{{ strtoupper($opcode->name) }}</label>
                            <input type="text"
                                   name="opcodes[{{ $opcode->id }}]"
                                   value="{{ $opcode->value }}"
                                   class="form-control text-center border-dark"
                                   maxlength="4"
                                   pattern="[0-1]{4}"
                                   title="Enter a 4-digit binary number (e.g., 1010)"
                                   required>
                            <small class="text-muted">4-bit binary (e.g., 1010)</small>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card-footer text-end">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save me-1"></i> Update Opcodes
                </button>
                <a href="{{ route('sap.view') }}" class="btn btn-secondary ms-2">
                    <i class="fas fa-arrow-left me-1"></i> Back to Simulator
                </a>
            </div>
        </div>
    </form>
</div>
@endsection
