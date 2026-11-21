<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use App\Services\ServiceRequestQueryService;
use App\Services\ServiceRequestStatusService;
use App\Services\ServiceRequestSubmissionService;
use App\Services\UnsupportedStatusFilterException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Browser interface for Service Request Management.
 *
 * This controller only translates between HTTP and the Service Request
 * application services. Validation and Resident eligibility are decided by
 * ServiceRequestSubmissionService, workflow transitions by
 * ServiceRequestStatusService, and querying by ServiceRequestQueryService.
 */
class ServiceRequestController extends Controller
{
    /**
     * User-facing wording for each Service Request field the submission
     * service reports as invalid. The rules live in ServiceRequestValidator.
     */
    private const FIELD_MESSAGES = [
        'resident_id' => 'Enter a valid Resident ID.',
        'service_type' => 'Enter a service type.',
        'description' => 'Enter a description.',
        'date_requested' => 'Enter the date requested as a valid date.',
    ];

    /**
     * How each workflow target status is presented as a named action.
     * Which targets are available from a given status is read from
     * ServiceRequestStatusService, never decided here.
     */
    private const WORKFLOW_ACTIONS = [
        'In Progress' => [
            'label' => 'Start Processing',
            'route' => 'service-requests.start',
            'success' => 'Service Request is now In Progress.',
        ],
        'Completed' => [
            'label' => 'Complete',
            'route' => 'service-requests.complete',
            'success' => 'Service Request completed successfully.',
        ],
        'Cancelled' => [
            'label' => 'Cancel',
            'route' => 'service-requests.cancel',
            'success' => 'Service Request cancelled successfully.',
        ],
    ];

    public function index(
        Request $request,
        ServiceRequestQueryService $queryService
    ): View {
        $search = $this->stringInput($request, 'search');
        $status = $this->stringInput($request, 'status');
        $filterError = null;

        try {
            $serviceRequests = $queryService->searchServiceRequests(
                $search,
                $status
            );
        } catch (UnsupportedStatusFilterException) {
            $serviceRequests = collect();
            $filterError = 'The selected status filter is not a supported Service Request status.';
        }

        return view('service-requests.index', [
            'serviceRequests' => $serviceRequests,
            'search' => trim($search ?? ''),
            'status' => trim($status ?? ''),
            'statuses' => ServiceRequestQueryService::supportedStatuses(),
            'filterError' => $filterError,
            'workflowActions' => $this->workflowActionsByStatus(),
        ]);
    }

    public function create(): View
    {
        return view('service-requests.create');
    }

    public function store(
        Request $request,
        ServiceRequestSubmissionService $submissionService
    ): RedirectResponse {
        $input = [
            'resident_id' => $this->stringInput($request, 'resident_id'),
            'service_type' => $this->stringInput($request, 'service_type'),
            'description' => $this->stringInput($request, 'description'),
            'date_requested' => $this->stringInput($request, 'date_requested'),
        ];

        $result = $submissionService->submitServiceRequest(
            new ServiceRequest([
                ...$input,
                'resident_id' => $this->residentIdValue($input['resident_id']),
            ])
        );

        if ($result->success) {
            return redirect()
                ->route('service-requests.index')
                ->with('success', 'Service Request submitted successfully.');
        }

        if ($result->residentNotFound) {
            $errors = ['resident_id' => 'Resident not found.'];
        } elseif ($result->residentInactive) {
            $errors = ['resident_id' => 'Inactive Residents cannot submit new Service Requests.'];
        } else {
            $errors = [];

            foreach ($result->errors as $field) {
                $errors[$field] =
                    self::FIELD_MESSAGES[$field] ?? 'Service Request information is invalid.';
            }
        }

        return redirect()
            ->route('service-requests.create')
            ->withInput($input)
            ->withErrors($errors);
    }

    public function show(
        string $id,
        ServiceRequestQueryService $queryService
    ): View {
        $serviceRequest = $queryService->viewServiceRequest((int) $id);

        abort_if($serviceRequest === null, 404, 'Service Request not found.');

        return view('service-requests.show', [
            'serviceRequest' => $serviceRequest,
            'workflowActions' => $this->workflowActionsByStatus(),
        ]);
    }

    public function start(
        string $id,
        ServiceRequestStatusService $statusService
    ): RedirectResponse {
        return $this->changeStatus($statusService, $id, 'In Progress');
    }

    public function complete(
        string $id,
        ServiceRequestStatusService $statusService
    ): RedirectResponse {
        return $this->changeStatus($statusService, $id, 'Completed');
    }

    public function cancel(
        string $id,
        ServiceRequestStatusService $statusService
    ): RedirectResponse {
        return $this->changeStatus($statusService, $id, 'Cancelled');
    }

    /**
     * Ask the status service for one transition and report its decision.
     */
    private function changeStatus(
        ServiceRequestStatusService $statusService,
        string $id,
        string $requestedStatus
    ): RedirectResponse {
        $result = $statusService->changeStatus((int) $id, $requestedStatus);

        abort_if($result->notFound, 404, 'Service Request not found.');

        $redirect = redirect()->route('service-requests.show', ['id' => $id]);

        if (! $result->success) {
            return $redirect->with(
                'error',
                'Invalid Service Request transition: a '
                    .$result->serviceRequest->status
                    .' Service Request cannot be changed to '
                    .$requestedStatus.'.'
            );
        }

        return $redirect->with(
            'success',
            self::WORKFLOW_ACTIONS[$requestedStatus]['success']
        );
    }

    /**
     * The named actions to offer for each current status, derived from the
     * transitions ServiceRequestStatusService allows.
     *
     * @return array<string, list<array{label: string, route: string}>>
     */
    private function workflowActionsByStatus(): array
    {
        $actions = [];

        foreach (ServiceRequestStatusService::ALLOWED_TRANSITIONS as $status => $targets) {
            $actions[$status] = [];

            foreach ($targets as $target) {
                $actions[$status][] = [
                    'label' => self::WORKFLOW_ACTIONS[$target]['label'],
                    'route' => self::WORKFLOW_ACTIONS[$target]['route'],
                ];
            }
        }

        return $actions;
    }

    /**
     * The submitted Resident ID as an integer when it is written as plain
     * digits; otherwise the raw text, which the submission service rejects.
     */
    private function residentIdValue(?string $value): int|string|null
    {
        if ($value !== null && preg_match('/^[0-9]{1,18}$/', $value) === 1) {
            return (int) $value;
        }

        return $value;
    }

    /**
     * A request value as a string, or null when it is absent or not text.
     */
    private function stringInput(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) ? $value : null;
    }
}
