<?php

namespace App\Services;

use App\Models\Resident;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;

class ResidentValidator
{
    public function validate(Resident $resident): ValidatorContract
    {
        return Validator::make(
            [
                'first_name' => $resident->first_name,
                'last_name' => $resident->last_name,
                'address' => $resident->address,
                'contact_number' => $resident->contact_number,
                'email' => $resident->email,
                'status' => $resident->status,
            ],
            self::rules()
        );
    }

    public function isValid(Resident $resident): bool
    {
        return ! $this->validate($resident)->fails();
    }

    public static function rules(): array
    {
        return [
            'first_name' => [
                'required',
                'string',
                'not_regex:/^\s*$/',
            ],
            'last_name' => [
                'required',
                'string',
                'not_regex:/^\s*$/',
            ],
            'address' => [
                'required',
                'string',
                'not_regex:/^\s*$/',
            ],
            'contact_number' => [
                'required',
                'string',
                'regex:/^09[0-9]{9}$/',
            ],
            'email' => [
                'required',
                'string',
                'regex:/^[^@\s]+@[^@\s]+\.[^@\s]+$/',
            ],
            'status' => [
                'required',
                'string',
                'in:Active,Inactive',
            ],
        ];
    }
}
