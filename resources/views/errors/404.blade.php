@extends('layouts.app')

@section('title', 'Not Found')

@section('content')

    <h1>Not Found</h1>

    <div class="panel">
        <p class="empty">
            {{ $exception->getMessage() ?: 'The page or record you requested does not exist.' }}
        </p>
    </div>

@endsection
