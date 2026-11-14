@if ($errors->any())
    <div class="message error" role="alert">
        Resident information is invalid. Please correct the following:
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $fields = [
        'first_name' => ['label' => 'First Name', 'type' => 'text'],
        'last_name' => ['label' => 'Last Name', 'type' => 'text'],
        'address' => ['label' => 'Address', 'type' => 'text'],
        'contact_number' => ['label' => 'Contact Number', 'type' => 'text'],
        'email' => ['label' => 'Email', 'type' => 'text'],
    ];
@endphp

@foreach ($fields as $name => $field)
    <div class="field">
        <label for="{{ $name }}">{{ $field['label'] }}</label>

        <input type="{{ $field['type'] }}"
               id="{{ $name }}"
               name="{{ $name }}"
               value="{{ old($name, $resident?->getAttribute($name)) }}"
               @if ($name === 'contact_number') placeholder="09XXXXXXXXX" @endif>

        @error($name)
            <p class="field-error">{{ $message }}</p>
        @enderror
    </div>
@endforeach
