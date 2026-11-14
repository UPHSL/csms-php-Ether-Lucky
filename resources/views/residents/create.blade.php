@extends('layouts.app')

@section('title', 'Register Resident')

@section('content')

    <h1>Register Resident</h1>

    <div class="panel">
        <form method="POST" action="{{ route('residents.store') }}" novalidate>
            @csrf

            @include('residents._form', ['resident' => null])

            <div class="actions">
                <button type="submit">Register Resident</button>
                <a class="button secondary" href="{{ route('residents.index') }}">Back to Resident Management</a>
            </div>
        </form>
    </div>

@endsection
