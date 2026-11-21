@extends('layouts.app')

@section('title', 'Resident Management')

@section('content')

    <h1>Resident Management</h1>

    <div class="panel toolbar">
        <form method="GET" action="{{ route('residents.index') }}" role="search">
            <div>
                <label for="search">Search by first or last name</label>
                <input type="search" id="search" name="search" value="{{ $search }}">
            </div>

            <button type="submit">Search</button>

            @if ($search !== '')
                <a class="button secondary" href="{{ route('residents.index') }}">Clear</a>
            @endif
        </form>

        <a class="button" href="{{ route('residents.create') }}">Register Resident</a>
    </div>

    <div class="panel">
        @if ($residents->isEmpty())
            <p class="empty">No Residents found.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">First Name</th>
                            <th scope="col">Last Name</th>
                            <th scope="col">Address</th>
                            <th scope="col">Contact Number</th>
                            <th scope="col">Email</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($residents as $resident)
                            <tr>
                                <td>{{ $resident->id }}</td>
                                <td>{{ $resident->first_name }}</td>
                                <td>{{ $resident->last_name }}</td>
                                <td>{{ $resident->address }}</td>
                                <td>{{ $resident->contact_number }}</td>
                                <td>{{ $resident->email }}</td>
                                <td>
                                    <span class="badge {{ strtolower($resident->status) }}">{{ $resident->status }}</span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a class="button secondary small"
                                           href="{{ route('residents.edit', ['id' => $resident->id]) }}">Edit</a>

                                        @if ($resident->status === 'Active')
                                            <form method="POST"
                                                  action="{{ route('residents.deactivate', ['id' => $resident->id]) }}">
                                                @csrf
                                                <button type="submit" class="secondary small">Deactivate</button>
                                            </form>
                                        @endif
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
