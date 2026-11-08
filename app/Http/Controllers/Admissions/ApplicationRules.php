<?php

namespace App\Http\Controllers\Admissions;

use App\Models\StudentDocument;
use Illuminate\Http\Request;

/** Validation shared by the walk-in desk form and the public online application. */
class ApplicationRules
{
    public static function fields(mixed $classRule): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'in:male,female,other'],
            'date_of_birth' => ['required', 'date', 'before:today', 'after:1950-01-01'],
            'place_of_birth' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'previous_school' => ['nullable', 'string', 'max:200'],
            'address' => ['nullable', 'string', 'max:255'],
            'preferred_class_id' => ['nullable', $classRule],
            'guardian_name' => ['required', 'string', 'max:150'],
            'guardian_relationship' => ['nullable', 'string', 'max:50'],
            'guardian_phone' => ['required', 'string', 'max:50'],
            'guardian_email' => ['nullable', 'email:rfc', 'max:150'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public static function documents(): array
    {
        return [
            'documents' => ['nullable', 'array', 'max:5'],
            'documents.*.type' => ['required_with:documents.*.file', 'in:'.implode(',', StudentDocument::TYPES)],
            'documents.*.file' => ['required_with:documents.*.type', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.config('schoolos.students.document_max_kb')],
        ];
    }

    /** @return array<int,array{type:string,file:\Illuminate\Http\UploadedFile}> */
    public static function uploads(Request $request): array
    {
        $out = [];
        foreach ((array) $request->file('documents', []) as $i => $row) {
            if (isset($row['file'])) {
                $out[] = ['type' => (string) $request->input("documents.$i.type", 'other'), 'file' => $row['file']];
            }
        }

        return $out;
    }
}
