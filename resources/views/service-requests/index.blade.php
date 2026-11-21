@extends('layouts.app')

@section('title', 'Service Request Management')

@section('content')

    <h1>Service Request Management</h1>

    @if ($filterError)
        <p class="message error" role="alert">{{ $filterError }}</p>
    @endif

    <div class="panel toolbar">
        <form method="GET" action="{{ route('service-requests.index') }}" role="search">
            <div>
                <label for="search">Search service type or description</label>
                <input type="search" id="search" name="search" value="{{ $search }}">
            </div>

            <div>
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All Statuses</option>
                    @foreach ($statuses as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit">Search</button>

            @if ($search !== '' || $status !== '')
                <a class="button secondary" href="{{ route('service-requests.index') }}">Clear</a>
            @endif
        </form>

        <a class="button" href="{{ route('service-requests.create') }}">Submit Service Request</a>
    </div>

    <div class="panel">
        @if ($serviceRequests->isEmpty())
            <p class="empty">No Service Requests found.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Resident ID</th>
                            <th scope="col">Service Type</th>
                            <th scope="col">Description</th>
                            <th scope="col">Date Requested</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($serviceRequests as $serviceRequest)
                            <tr>
                                <td>{{ $serviceRequest->id }}</td>
                                <td>{{ $serviceRequest->resident_id }}</td>
                                <td>{{ $serviceRequest->service_type }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($serviceRequest->description, 60) }}</td>
                                <td>{{ $serviceRequest->date_requested }}</td>
                                <td>
                                    <span class="badge {{ \Illuminate\Support\Str::slug($serviceRequest->status) }}">{{ $serviceRequest->status }}</span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a class="button secondary small"
                                           href="{{ route('service-requests.show', ['id' => $serviceRequest->id]) }}">View</a>

                                        @include('service-requests._workflow-actions', [
                                            'serviceRequest' => $serviceRequest,
                                            'buttonClass' => 'secondary small',
                                        ])
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

@endsection
