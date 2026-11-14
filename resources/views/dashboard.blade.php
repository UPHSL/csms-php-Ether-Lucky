@extends('layouts.app')

@section('title', 'CSMS Dashboard')

@section('content')

    <h1>CSMS Dashboard</h1>

    @php
        $residentMetrics = [
            'total-residents' => ['Total Residents', $summary->totalResidents],
            'active-residents' => ['Active Residents', $summary->activeResidents],
            'inactive-residents' => ['Inactive Residents', $summary->inactiveResidents],
        ];

        $serviceRequestMetrics = [
            'total-service-requests' => ['Total Service Requests', $summary->totalServiceRequests],
            'open-service-requests' => ['Open Service Requests', $summary->openServiceRequests],
            'pending-service-requests' => ['Pending', $summary->pendingServiceRequests],
            'in-progress-service-requests' => ['In Progress', $summary->inProgressServiceRequests],
            'completed-service-requests' => ['Completed', $summary->completedServiceRequests],
            'cancelled-service-requests' => ['Cancelled', $summary->cancelledServiceRequests],
        ];
    @endphp

    <section class="panel" aria-labelledby="resident-summary">
        <div class="toolbar">
            <h2 id="resident-summary">Resident Summary</h2>
            <a class="button secondary small" href="{{ route('residents.index') }}">Go to Resident Management</a>
        </div>

        <div class="stats">
            @foreach ($residentMetrics as $key => [$name, $value])
                <div class="stat">
                    <span class="value" data-metric="{{ $key }}">{{ $value }}</span>
                    <span class="name">{{ $name }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <section class="panel" aria-labelledby="service-request-summary">
        <div class="toolbar">
            <h2 id="service-request-summary">Service Request Summary</h2>
            <a class="button secondary small" href="{{ route('service-requests.index') }}">Go to Service Request Management</a>
        </div>

        <div class="stats">
            @foreach ($serviceRequestMetrics as $key => [$name, $value])
                <div class="stat">
                    <span class="value" data-metric="{{ $key }}">{{ $value }}</span>
                    <span class="name">{{ $name }}</span>
                </div>
            @endforeach
        </div>

        <p class="muted">Open Service Requests are those that are Pending or In Progress.</p>
    </section>

    <section class="panel" aria-labelledby="recent-service-requests">
        <h2 id="recent-service-requests">Recent Service Requests</h2>

        @if ($summary->recentServiceRequests->isEmpty())
            <p class="empty">No Service Requests available.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Resident ID</th>
                            <th scope="col">Service Type</th>
                            <th scope="col">Date Requested</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($summary->recentServiceRequests as $serviceRequest)
                            <tr data-recent-service-request="{{ $serviceRequest->id }}">
                                <td>{{ $serviceRequest->id }}</td>
                                <td>{{ $serviceRequest->resident_id }}</td>
                                <td>{{ $serviceRequest->service_type }}</td>
                                <td>{{ $serviceRequest->date_requested }}</td>
                                <td>
                                    <span class="badge {{ \Illuminate\Support\Str::slug($serviceRequest->status) }}">{{ $serviceRequest->status }}</span>
                                </td>
                                <td>
                                    <a class="button secondary small"
                                       href="{{ route('service-requests.show', ['id' => $serviceRequest->id]) }}">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

@endsection
