@foreach ($workflowActions[$serviceRequest->status] ?? [] as $action)
    <form method="POST" action="{{ route($action['route'], ['id' => $serviceRequest->id]) }}">
        @csrf
        <button type="submit" class="{{ $buttonClass }}">{{ $action['label'] }}</button>
    </form>
@endforeach
