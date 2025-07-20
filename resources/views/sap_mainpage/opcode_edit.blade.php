<form method="POST" action="{{ route('sap.opcodes.update') }}">
    @csrf
    <div class="row">
        @foreach($opcodes as $opcode)
            <div class="col-md-4 mb-2">
                <label class="form-label">{{ strtoupper($opcode->name) }}</label>
                <input type="text" name="opcodes[{{ $opcode->id }}]"
                       value="{{ $opcode->value }}"
                       class="form-control"
                       maxlength="4"
                       pattern="[0-1]{4}"
                       required>
            </div>
        @endforeach
    </div>
    <div class="text-end mt-3">
        <button type="submit" class="btn btn-primary">Update Opcodes</button>
    </div>
</form>
