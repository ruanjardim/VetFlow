# Exam Results

## Purpose

Records the document received for an exam request already linked to a medical
record. The workflow preserves the source request, patient, clinic, author, and
lifecycle history without interpreting the clinical meaning of the content.

## Code Paths

- `app/Modules/MedicalRecords/Controllers/ExamResultController.php`
- `app/Modules/MedicalRecords/Models/MedicalRecordExamResult.php`
- `app/Modules/MedicalRecords/Services/ExamResultService.php`
- `resources/views/medical-records/exam-results`
- `database/migrations/2026_08_20_020000_create_medical_record_exam_results_table.php`

## Lifecycle

1. A user with `medical-records.manage` opens one of the exam requests in a
   tenant-visible medical record.
2. The result is saved as a draft while dates, laboratory identification,
   summary, details, reference notes, and internal notes are reviewed.
3. Finalization requires a summary or detailed content, records the responsible
   user and timestamp, and makes the content immutable.
4. A finalized result can be cancelled with a required reason. The original
   content remains visible with its cancellation history.

There is no delete route. A medical-record update also cannot remove an exam
request after any result has been attached to it.

## Tenant And Access Rules

- The result receives its clinic from the source medical record.
- The exam request is resolved through its tenant-visible medical record.
- Users cannot open or update results from another clinic.
- All routes use the existing `medical-records.manage` permission because the
  result is part of the protected clinical record.

## Clinical Boundary

The first version stores the laboratory or imaging document as informed text.
It does not calculate flags, diagnose, normalize analytes, infer reference
ranges, validate laboratory accreditation, or sign the result digitally.
Reference notes are snapshots copied from the source document and are not
VetFlow recommendations.

## Table

- `medical_record_exam_results`

Each exam request accepts at most one result lifecycle record.

## Tests

`tests/Feature/ExamResultFlowTest.php` covers draft persistence, finalization,
immutability, cancellation history, permission enforcement, tenant isolation,
and protection of the source exam request.
