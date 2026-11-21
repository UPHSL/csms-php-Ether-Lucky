<?php

namespace App\Http\Controllers;

use App\Models\Resident;
use App\Repositories\ResidentRepository;
use App\Services\ResidentDeactivationService;
use App\Services\ResidentQueryService;
use App\Services\ResidentRegistrationService;
use App\Services\ResidentUpdateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Browser interface for Resident Management.
 *
 * This controller only translates between HTTP and the Resident application
 * services. Validation, persistence, searching, updating and deactivation
 * are all performed by the services it calls.
 */
class ResidentController extends Controller
{
    /**
     * User-facing wording for each Resident field the application services
     * report as invalid. The rules themselves live in ResidentValidator.
     */
    private const FIELD_MESSAGES = [
        'first_name' => 'Enter a first name.',
        'last_name' => 'Enter a last name.',
        'address' => 'Enter an address.',
        'contact_number' => 'Enter a contact number in the format 09XXXXXXXXX.',
        'email' => 'Enter a valid email address.',
        'status' => 'Resident status is invalid.',
    ];

    public function index(
        Request $request,
        ResidentQueryService $queryService
    ): View {
        $search = $this->stringInput($request, 'search');

        return view('residents.index', [
            'residents' => $queryService->searchResidents($search),
            'search' => trim($search ?? ''),
        ]);
    }

    public function create(): View
    {
        return view('residents.create');
    }

    public function store(
        Request $request,
        ResidentRegistrationService $registrationService
    ): RedirectResponse {
        $input = $this->residentInput($request);

        $result = $registrationService->registerResident(
            new Resident($input)
        );

        if (! $result->success) {
            return redirect()
                ->route('residents.create')
                ->withInput($input)
                ->withErrors($this->messagesFor($result->errors));
        }

        return redirect()
            ->route('residents.index')
            ->with('success', 'Resident registered successfully.');
    }

    public function edit(
        string $id,
        ResidentRepository $repository
    ): View {
        $resident = $repository->findById((int) $id);

        abort_if($resident === null, 404, 'Resident not found.');

        return view('residents.edit', [
            'resident' => $resident,
        ]);
    }

    public function update(
        Request $request,
        string $id,
        ResidentUpdateService $updateService
    ): RedirectResponse {
        $input = $this->residentInput($request);

        $result = $updateService->updateResident(
            (int) $id,
            $input
        );

        abort_if($result->notFound, 404, 'Resident not found.');

        if (! $result->success) {
            return redirect()
                ->route('residents.edit', ['id' => $id])
                ->withInput($input)
                ->withErrors($this->messagesFor($result->errors));
        }

        return redirect()
            ->route('residents.index')
            ->with('success', 'Resident information updated successfully.');
    }

    public function deactivate(
        string $id,
        ResidentDeactivationService $deactivationService
    ): RedirectResponse {
        $result = $deactivationService->deactivateResident((int) $id);

        abort_if($result->notFound, 404, 'Resident not found.');

        if ($result->alreadyInactive) {
            return redirect()
                ->route('residents.index')
                ->with('info', 'Resident is already Inactive.');
        }

        return redirect()
            ->route('residents.index')
            ->with('success', 'Resident deactivated successfully.');
    }

    /**
     * The editable Resident fields as submitted. ID and status are never
     * read from the request.
     *
     * @return array<string, string|null>
     */
    private function residentInput(Request $request): array
    {
        $input = [];

        foreach (ResidentUpdateService::EDITABLE_FIELDS as $field) {
            $input[$field] = $this->stringInput($request, $field);
        }

        return $input;
    }

    /**
     * A request value as a string, or null when it is absent or not text.
     */
    private function stringInput(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) ? $value : null;
    }

    /**
     * @param  list<string>  $invalidFields
     * @return array<string, string>
     */
    private function messagesFor(array $invalidFields): array
    {
        $messages = [];

        foreach ($invalidFields as $field) {
            $messages[$field] =
                self::FIELD_MESSAGES[$field] ?? 'Resident information is invalid.';
        }

        return $messages;
    }
}
