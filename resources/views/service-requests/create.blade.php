@extends('layouts.app')

@section('title', 'Submit Service Request')

@section('content')

    <h1>Submit Service Request</h1>

    <div class="panel">
        @if ($errors->any())
            <div class="message error" role="alert">
                The Service Request was not submitted. Please correct the following:
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('service-requests.store') }}" novalidate>
            @csrf

            <div class="field">
                <label for="resident_id">Resident ID</label>
                <input type="text" inputmode="numeric" id="resident_id" name="resident_id"
                       value="{{ old('resident_id') }}">
                @error('resident_id')
                    <p class="field-error">{{ $message }}</p>
                @enderror
                <p class="muted">
                    The Resident must be Active.
                    <a href="{{ route('residents.index') }}">View Residents</a> to find the ID.
                </p>
            </div>

            <div class="field">
                <label for="service_type">Service Type</label>
                <input type="text" id="service_type" name="service_type" value="{{ old('service_type') }}">
                @error('service_type')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="5">{{ old('description') }}</textarea>
                @error('description')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field">
                <label for="date_requested">Date Requested</label>
                <input type="date" id="date_requested" name="date_requested" value="{{ old('date_requested') }}">
                @error('date_requested')
                    <p class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="actions">
                <button type="submit">Submit Service Request</button>
                <a class="button secondary" href="{{ route('service-requests.index') }}">Back to Service Request Management</a>
            </div>
        </form>
    </div>

@endsection
