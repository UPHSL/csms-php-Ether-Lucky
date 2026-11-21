@extends('layouts.app')

@section('title', 'Service Request Details')

@section('content')

    <h1>Service Request Details</h1>

    <div class="panel">
        <dl class="details">
            <dt>ID</dt>
            <dd>{{ $serviceRequest->id }}</dd>

            <dt>Resident ID</dt>
            <dd>{{ $serviceRequest->resident_id }}</dd>

            <dt>Service Type</dt>
            <dd>{{ $serviceRequest->service_type }}</dd>

            <dt>Description</dt>
            <dd>{{ $serviceRequest->description }}</dd>

            <dt>Date Requested</dt>
            <dd>{{ $serviceRequest->date_requested }}</dd>

            <dt>Status</dt>
            <dd><span class="badge {{ \Illuminate\Support\Str::slug($serviceRequest->status) }}">{{ $serviceRequest->status }}</span></dd>
        </dl>
    </div>

    <div class="panel">
        <h2>Workflow</h2>

        @if (empty($workflowActions[$serviceRequest->status] ?? []))
            <p class="empty">
                This Service Request is {{ $serviceRequest->status }}. No further workflow actions are available.
            </p>
        @else
            <div class="actions">
                @include('service-requests._workflow-actions', [
                    'serviceRequest' => $serviceRequest,
                    'buttonClass' => '',
                ])
            </div>
        @endif
    </div>

    <a class="button secondary" href="{{ route('service-requests.index') }}">Back to Service Request Management</a>

@endsection
