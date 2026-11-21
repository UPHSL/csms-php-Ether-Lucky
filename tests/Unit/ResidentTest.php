<?php

namespace Tests\Unit;

use App\Models\Resident;
use PHPUnit\Framework\TestCase;

class ResidentTest extends TestCase
{
    public function test_resident_can_be_created_with_required_information(): void
    {
        $resident = new Resident([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'address' => 'Barangay Santo Tomas',
            'contact_number' => '09171234567',
            'email' => 'juan@example.com',
            'status' => 'Active',
        ]);

        $resident->id = 1;

        $this->assertSame(1, $resident->id);
        $this->assertSame('Juan', $resident->first_name);
        $this->assertSame('Dela Cruz', $resident->last_name);
        $this->assertSame('Barangay Santo Tomas', $resident->address);
        $this->assertSame('09171234567', $resident->contact_number);
        $this->assertSame('juan@example.com', $resident->email);
        $this->assertSame('Active', $resident->status);
    }

    public function test_resident_information_can_be_accessed_and_updated(): void
    {
        $resident = new Resident([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'address' => 'Barangay Santo Tomas',
            'contact_number' => '09181234567',
            'email' => 'maria@example.com',
        ]);

        $resident->contact_number = '09991234567';
        $resident->email = 'maria.santos@example.com';

        $this->assertSame('Maria', $resident->first_name);
        $this->assertSame('Santos', $resident->last_name);
        $this->assertSame('Barangay Santo Tomas', $resident->address);
        $this->assertSame('09991234567', $resident->contact_number);
        $this->assertSame('maria.santos@example.com', $resident->email);
    }

    public function test_resident_defaults_to_active_status(): void
    {
        $resident = new Resident([
            'first_name' => 'Pedro',
            'last_name' => 'Reyes',
            'address' => 'Barangay Santo Tomas',
            'contact_number' => '09191234567',
            'email' => 'pedro@example.com',
        ]);

        $this->assertSame('Active', $resident->status);
    }
}
