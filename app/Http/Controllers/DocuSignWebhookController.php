<?php

namespace App\Http\Controllers;

use App\Services\DocuSignService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DocuSignWebhookController extends Controller
{
    public function __construct(private DocuSignService $docuSignService)
    {
    }

    public function handle(Request $request): Response
    {
        $this->docuSignService->handleWebhook($request->all());
        
        return response()->noContent();
    }
}