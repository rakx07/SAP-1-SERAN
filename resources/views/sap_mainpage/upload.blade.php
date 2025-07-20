@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Upload SAP Instructions (Excel)</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('sap.upload') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="mb-3">
            <label for="file" class="form-label">Choose Excel File</label>
            <input type="file" name="file" id="file" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary">Upload Instructions</button>
        <a href="{{ route('sap.view') }}" class="btn btn-secondary">Back to Simulator</a>
    </form>
</div>
@endsection
