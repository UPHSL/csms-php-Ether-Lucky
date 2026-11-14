<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>@yield('title') - CSMS</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f5f6f8;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            line-height: 1.45;
        }

        .site-header {
            background: #1f2937;
            color: #fff;
        }

        .site-header .inner {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px 32px;
            padding-top: 14px;
            padding-bottom: 14px;
        }

        .brand {
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .site-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }

        .site-nav a {
            color: #e5e7eb;
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 6px;
        }

        .site-nav a:hover,
        .site-nav a.current {
            background: #374151;
            color: #fff;
        }

        .inner {
            width: min(1100px, 94%);
            margin: 0 auto;
        }

        main.inner {
            padding: 28px 0 48px;
        }

        h1 {
            margin: 0 0 20px;
            font-size: 1.7rem;
        }

        h2 {
            margin: 0 0 12px;
            font-size: 1.15rem;
        }

        .panel {
            background: #fff;
            border: 1px solid #d9dde3;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
        }

        .toolbar form {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 10px;
        }

        label {
            display: block;
            margin-bottom: 4px;
            font-weight: 700;
            font-size: 0.9rem;
        }

        input[type="text"],
        input[type="search"],
        input[type="email"],
        input[type="number"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 9px 10px;
            border: 1px solid #b6bcc6;
            border-radius: 6px;
            font: inherit;
            background: #fff;
        }

        .toolbar input,
        .toolbar select {
            width: auto;
            min-width: 220px;
        }

        .field {
            margin-bottom: 16px;
            max-width: 520px;
        }

        .field-error {
            margin: 4px 0 0;
            color: #b42318;
            font-size: 0.9rem;
        }

        .button,
        button {
            display: inline-block;
            padding: 9px 14px;
            border: 1px solid #1f2937;
            border-radius: 6px;
            background: #1f2937;
            color: #fff;
            font: inherit;
            text-decoration: none;
            cursor: pointer;
        }

        .button.secondary,
        button.secondary {
            background: #fff;
            color: #1f2937;
        }

        .button.small,
        button.small {
            padding: 5px 10px;
            font-size: 0.9rem;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .actions form {
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 10px 8px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }

        th {
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #4b5563;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            border: 1px solid #9ca3af;
            background: #f3f4f6;
            font-size: 0.85rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .badge.active,
        .badge.completed {
            border-color: #4ade80;
            background: #dcfce7;
            color: #14532d;
        }

        .badge.pending {
            border-color: #facc15;
            background: #fef9c3;
            color: #713f12;
        }

        .badge.in-progress {
            border-color: #60a5fa;
            background: #dbeafe;
            color: #1e3a8a;
        }

        .badge.inactive,
        .badge.cancelled {
            border-color: #9ca3af;
            background: #e5e7eb;
            color: #374151;
        }

        .message {
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid;
            margin-bottom: 20px;
        }

        .message.success {
            border-color: #4ade80;
            background: #dcfce7;
        }

        .message.info {
            border-color: #60a5fa;
            background: #dbeafe;
        }

        .message.error {
            border-color: #f87171;
            background: #fee2e2;
        }

        .message ul {
            margin: 6px 0 0;
            padding-left: 20px;
        }

        .empty {
            margin: 0;
            color: #4b5563;
        }

        .muted {
            color: #4b5563;
        }

        dl.details {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 10px 16px;
            margin: 0;
        }

        dl.details dt {
            font-weight: 700;
        }

        dl.details dd {
            margin: 0;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
        }

        .stat {
            background: #f7f8fa;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 14px;
        }

        .stat .value {
            display: block;
            font-size: 1.9rem;
            font-weight: 700;
        }

        .stat .name {
            font-size: 0.9rem;
            color: #374151;
        }
    </style>
</head>

<body>

<header class="site-header">
    <div class="inner">
        <span class="brand">CSMS</span>

        <nav class="site-nav" aria-label="Main navigation">
            <a href="{{ route('dashboard') }}"
               @class(['current' => request()->routeIs('dashboard')])>Dashboard</a>
            <a href="{{ route('residents.index') }}"
               @class(['current' => request()->routeIs('residents.*')])>Residents</a>
            <a href="{{ route('service-requests.index') }}"
               @class(['current' => request()->routeIs('service-requests.*')])>Service Requests</a>
        </nav>
    </div>
</header>

<main class="inner">

    @foreach (['success', 'info', 'error'] as $messageType)
        @if (session($messageType))
            <p class="message {{ $messageType }}" role="status">{{ session($messageType) }}</p>
        @endif
    @endforeach

    @yield('content')

</main>

</body>
</html>
