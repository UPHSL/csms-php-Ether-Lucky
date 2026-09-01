<?php

namespace Tests\Unit;

use App\Models\Resident;
use App\Services\ResidentValidator;
use Tests\TestCase;

class ResidentValidatorTest extends TestCase
{
    private ResidentValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new ResidentValidator;
    }

    private function makeValidResident(
        array $overrides = []
    ): Resident {
        return new Resident(array_merge([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'address' => 'Barangay Santo Tomas',
            'contact_number' => '09171234567',
            'email' => 'juan@example.com',
            'status' => 'Active',
        ], $overrides));
    }

    public function test_valid_resident_information_passes_validation(): void
    {
        $resident = $this->makeValidResident();

        $this->assertTrue(
            $this->validator->isValid($resident)
        );
    }

    public function test_missing_first_name_fails_validation(): void
    {
        $resident = $this->makeValidResident([
            'first_name' => '',
        ]);

        $validation =
            $this->validator->validate($resident);

        $this->assertTrue($validation->fails());
        $this->assertTrue(
            $validation->errors()->has('first_name')
        );
    }

    public function test_missing_last_name_fails_validation(): void
    {
        $resident = $this->makeValidResident([
            'last_name' => '',
        ]);

        $validation =
            $this->validator->validate($resident);

        $this->assertTrue($validation->fails());
        $this->assertTrue(
            $validation->errors()->has('last_name')
        );
    }

    public function test_missing_address_fails_validation(): void
    {
        $resident = $this->makeValidResident([
            'address' => '',
        ]);

        $validation =
            $this->validator->validate($resident);

        $this->assertTrue($validation->fails());
        $this->assertTrue(
            $validation->errors()->has('address')
        );
    }

    public function test_whitespace_only_required_information_fails_validation(): void
    {
        $resident = $this->makeValidResident([
            'first_name' => '   ',
        ]);

        $validation =
            $this->validator->validate($resident);

        $this->assertTrue($validation->fails());
        $this->assertTrue(
            $validation->errors()->has('first_name')
        );
    }

    public function test_invalid_contact_number_fails_validation(): void
    {
        $resident = $this->makeValidResident([
            'contact_number' => '0917ABC4567',
        ]);

        $validation =
            $this->validator->validate($resident);

        $this->assertTrue($validation->fails());
        $this->assertTrue(
            $validation->errors()
                ->has('contact_number')
        );
    }

    public function test_invalid_email_fails_validation(): void
    {
        $resident = $this->makeValidResident([
            'email' => 'juan.example.com',
        ]);

        $validation =
            $this->validator->validate($resident);

        $this->assertTrue($validation->fails());
        $this->assertTrue(
            $validation->errors()->has('email')
        );
    }

    public function test_supported_resident_statuses_pass_validation(): void
    {
        $activeResident =
            $this->makeValidResident([
                'status' => 'Active',
            ]);

        $inactiveResident =
            $this->makeValidResident([
                'status' => 'Inactive',
            ]);

        $this->assertTrue(
            $this->validator->isValid(
                $activeResident
            )
        );

        $this->assertTrue(
            $this->validator->isValid(
                $inactiveResident
            )
        );
    }

    public function test_unsupported_resident_status_fails_validation(): void
    {
        $resident = $this->makeValidResident([
            'status' => 'Unknown',
        ]);

        $validation =
            $this->validator->validate($resident);

        $this->assertTrue($validation->fails());
        $this->assertTrue(
            $validation->errors()->has('status')
        );
    }
}
