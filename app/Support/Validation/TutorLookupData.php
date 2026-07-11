<?php

namespace App\Support\Validation;

use App\Modules\Tutors\Models\Tutor;

class TutorLookupData
{
    public static function fromTutor(Tutor $tutor): array
    {
        return [
            'id' => $tutor->id,
            'name' => $tutor->name,
            'phone' => $tutor->phone,
            'email' => $tutor->email,

            'patients_count' => 0,
            'last_consultation' => null,
            'financial_pending' => false,
        ];
    }
}