@extends('layouts.app')

@section('title', 'Edit Resident')

@section('content')

    <h1>Edit Resident</h1>

    <div class="panel">
        <dl class="details">
            <dt>Resident ID</dt>
            <dd>{{ $resident->id }}</dd>

            <dt>Status</dt>
            <dd><span class="badge {{ strtolower($resident->status) }}">{{ $resident->status }}</span></dd>
        </dl>
    </div>

    <div class="panel">
        <form method="POST" action="{{ route('residents.update', ['id' => $resident->id]) }}" novalidate>
            @csrf
            @method('PUT')

            @include('residents._form', ['resident' => $resident])

            <div class="actions">
                <button type="submit">Save Changes</button>
                <a class="button secondary" href="{{ route('residents.index') }}">Back to Resident Management</a>
            </div>
        </form>
    </div>

@endsection
