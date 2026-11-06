<?php

namespace App\Modules\Saas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Saas\Models\LegalDocument;
use App\Modules\Saas\Services\LegalDocumentService;
use Illuminate\View\View;

class PublicLegalController extends Controller
{
    public function __construct(private readonly LegalDocumentService $legal) {}

    public function show(string $type): View
    {
        abort_unless(array_key_exists($type, LegalDocument::TYPES), 404);

        return view('public.legal', [
            'type' => $type,
            'typeLabel' => LegalDocument::TYPES[$type],
            'document' => $this->legal->publicDocument($type),
        ]);
    }
}
