<?php

namespace App\Modules\Tutors\Services;

use App\Modules\Tutors\Data\TutorLookupData;
use App\Modules\Tutors\Models\Tutor;

class DuplicateTutorDetector
{
    public function findByCpf(?string $cpf): TutorLookupData
    {
        if (!$cpf) {
            return TutorLookupData::empty();
        }

        $normalizedCpf = preg_replace('/\D/', '', $cpf);

        if (strlen($normalizedCpf) !== 11) {
            return TutorLookupData::empty();
        }

        $tutor = Tutor::query()
            ->where('cpf', $normalizedCpf)
            ->orWhere('cpf', $cpf)
            ->first();

        if (!$tutor) {
            return TutorLookupData::empty();
        }

        return TutorLookupData::fromTutor($tutor);
    }
}