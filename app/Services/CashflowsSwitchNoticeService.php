<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CashflowsSwitchNoticeService
{
    /**
     * Document-based envelope, not template-based: DocuSign's tabs can populate text
     * dynamically but have no equivalent for images, so a per-account logo can only get
     * into the signed document by being drawn into the PDF before DocuSign ever sees it.
     * Same pattern DocuSignService::sendGatewayPartnerContract() already uses - a locally
     * rendered PDF handed over as documentBase64, with a plain recipients.signers array
     * instead of templateId + templateRoles. No template ID needed for this flow at all.
     */
    public function __construct(
        private DocuSignService $docuSignService
    ) {}

    /**
     * Render the notice (with the account's name and logo baked directly into the page),
     * create the envelope, and return an embedded-signing URL for the admin who just
     * submitted the modal (routing order 1, review-only, no tabs). The account (routing
     * order 2) gets their own signing URL later via getSigningUrlForExistingEnvelope(),
     * once the webhook confirms the admin's step is done.
     */
    public function createEnvelope(Application $application, string $accountName, string $recipientName, ?UploadedFile $logo, User $initiatedBy): array
    {
        $account = $application->account;

        if (! $account || ! $account->email) {
            throw new \Exception('Account or account email is missing.');
        }

        if (! $initiatedBy->email) {
            throw new \Exception('The signed-in user has no email address.');
        }

        $accessToken = $this->docuSignService->getAccessToken();
        $logoDataUri = $this->resolveLogoDataUri($application, $logo);

        $html = view('pdfs.cashflows-switch-notice', [
            'account_name' => $accountName,
            'recipient_name' => $recipientName,
            'logo_data_uri' => $logoDataUri,
        ])->render();

        $pdfBase64 = base64_encode(DomPdf::loadHTML($html)->output());

        // Position measured directly off the calibrated 595x842px render of this exact
        // template with a logo present (top-left origin, matching this app's existing
        // DocuSign xPosition/yPosition convention). The logo pushes the "Kind regards,"
        // line down significantly compared to a logo-less layout, so this sits well below
        // both it and the sign-off name rather than overlapping them.
        $merchantTabs = [
            'signHereTabs' => [
                [
                    'documentId' => '1',
                    'pageNumber' => '1',
                    'xPosition' => '90',
                    'yPosition' => '350',
                    'required' => true,
                    'tabLabel' => 'cashflows_notice_signature',
                ],
            ],
            'dateSignedTabs' => [
                [
                    'documentId' => '1',
                    'pageNumber' => '1',
                    'xPosition' => '360',
                    'yPosition' => '350',
                    'required' => true,
                    'tabLabel' => 'cashflows_notice_signature_date',
                ],
            ],
        ];

        $envelopeDefinition = [
            'emailSubject' => "Cashflows Switch Notice - {$application->name}",
            'documents' => [
                [
                    'documentBase64' => $pdfBase64,
                    'name' => 'Cashflows Switch Notice',
                    'fileExtension' => 'pdf',
                    'documentId' => '1',
                ],
            ],
            'recipients' => [
                'signers' => [
                    [
                        'email' => $initiatedBy->email,
                        'name' => $initiatedBy->name ?? $initiatedBy->email,
                        'recipientId' => '1',
                        'routingOrder' => '1',
                        'clientUserId' => 'cashflows-user-' . $application->id,
                    ],
                    [
                        'email' => $account->email,
                        'name' => $account->name ?? $application->trading_name ?? $account->email,
                        'recipientId' => '2',
                        'routingOrder' => '2',
                        'clientUserId' => 'cashflows-merchant-' . $application->id,
                        'tabs' => $merchantTabs,
                    ],
                ],
            ],
            'status' => 'sent',
        ];

        Log::info('Creating Cashflows switch notice envelope', [
            'application_id' => $application->id,
            'initiated_by' => $initiatedBy->email,
            'account_email' => $account->email,
        ]);

        $response = Http::withToken($accessToken)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post(config('services.docusign.base_url')."/v2.1/accounts/".config('services.docusign.account_id')."/envelopes", $envelopeDefinition);

        if ($response->failed()) {
            Log::error('DocuSign Create Cashflows Notice Envelope Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'application_id' => $application->id,
            ]);
            throw new \Exception('Failed to create Cashflows switch notice envelope: '.$response->body());
        }

        $envelopeId = $response->json('envelopeId');

        $viewUrl = $this->docuSignService->getRecipientView(
            $accessToken,
            $envelopeId,
            $initiatedBy->email,
            $initiatedBy->name ?? $initiatedBy->email,
            'cashflows-user-' . $application->id,
            route('applications.cashflows-docusign-callback', ['application' => $application->id])
        );

        ApplicationDocument::create([
            'application_id' => $application->id,
            'document_type' => 'cashflows_notice_contract',
            'document_category' => ApplicationDocument::CATEGORY_CASHFLOWS_SWITCH_NOTICE,
            'external_id' => $envelopeId,
            'external_system' => 'docusign',
            'status' => 'sent',
            'sent_at' => now(),
            'uploaded_by' => $initiatedBy->id,
            'uploaded_by_type' => 'user',
        ]);

        $application->status->update([
            'cashflows_docusign_envelope_id' => $envelopeId,
            'cashflows_docusign_status' => 'sent',
        ]);

        return [
            'envelope_id' => $envelopeId,
            'signing_url' => $viewUrl,
        ];
    }

    /**
     * Get a fresh embedded-signing URL for whichever guard is currently asking, against
     * an envelope that already exists. Deterministic clientUserId/email mapping (unlike
     * the main contract's version of this) since this envelope's roles are always fixed
     * and never imported.
     */
    public function getSigningUrlForExistingEnvelope(Application $application): array
    {
        $envelopeId = $application->status->cashflows_docusign_envelope_id;

        if (! $envelopeId) {
            throw new \Exception('No Cashflows switch notice envelope exists yet for this application.');
        }

        $accessToken = $this->docuSignService->getAccessToken();
        $isAccount = auth()->guard('account')->check();

        if ($isAccount) {
            $account = $application->account;
            $recipientEmail = $account->email;
            $recipientName = $account->name ?? $application->trading_name ?? $account->email;
            $clientUserId = 'cashflows-merchant-' . $application->id;
        } else {
            $user = auth()->guard('web')->user();
            $recipientEmail = $user->email;
            $recipientName = $user->name ?? $user->email;
            $clientUserId = 'cashflows-user-' . $application->id;
        }

        $viewUrl = $this->docuSignService->getRecipientView(
            $accessToken,
            $envelopeId,
            $recipientEmail,
            $recipientName,
            $clientUserId,
            route('applications.cashflows-docusign-callback', ['application' => $application->id])
        );

        return [
            'envelope_id' => $envelopeId,
            'signing_url' => $viewUrl,
        ];
    }

    /**
     * DomPDF needs the image inline (a data URI) rather than a URL it would have to fetch
     * itself, so the account's own uploaded logo - or a one-off override from the modal -
     * gets embedded directly rather than linked.
     */
    private function resolveLogoDataUri(Application $application, ?UploadedFile $logo): ?string
    {
        if ($logo) {
            return 'data:'.$logo->getMimeType().';base64,'.base64_encode($logo->get());
        }

        $photoPath = $application->account->photo_path ?? null;

        if ($photoPath && Storage::disk('public')->exists($photoPath)) {
            $mimeType = Storage::disk('public')->mimeType($photoPath);
            $contents = Storage::disk('public')->get($photoPath);

            return "data:{$mimeType};base64,".base64_encode($contents);
        }

        return null;
    }
}
