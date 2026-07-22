<?php

namespace App\Services\Identity\Data;

final class VerifiedPersonData
{
    public function __construct(
        public readonly string $firstName,
        public readonly ?string $middleName,
        public readonly string $lastName,
        public readonly string $fullName,
        public readonly ?string $dateOfBirth,
        public readonly ?string $gender,
        public readonly ?string $nationality,
        public readonly string $countryCode,
        public readonly ?string $photoUrl,
    ) {}

    public function toArray(): array
    {
        return [
            'first_name' => $this->firstName,
            'middle_name' => $this->middleName,
            'last_name' => $this->lastName,
            'full_name' => $this->fullName,
            'date_of_birth' => $this->dateOfBirth,
            'gender' => $this->gender,
            'nationality' => $this->nationality,
            'country_code' => $this->countryCode,
            'photo_url' => $this->photoUrl,
        ];
    }
}
