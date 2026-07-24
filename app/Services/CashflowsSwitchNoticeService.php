<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CashflowsSwitchNoticeService
{
    /**
     * DocuSign template for the Cashflows -> Cardstream switch notice. Blank source PDF,
     * no template-defined recipients - both roles below are supplied entirely via the
     * API, same as this app already does for the main contract's Product Manager /
     * Account Merchant roles.
     */
    private const TEMPLATE_ID = 'd216bcec-fd9e-4ec9-a24e-94763b525acc';

    public function __construct(
        private DocuSignService $docuSignService
    ) {}

    /**
     * Create the envelope and return an embedded-signing URL for the admin who just
     * submitted the modal (routing order 1, review-only, no tabs). The account (routing
     * order 2) gets their own signing URL later via getSigningUrlForExistingEnvelope(),
     * once the webhook confirms the admin's step is done.
     */
    public function createEnvelope(Application $application, string $accountName, string $recipientName, User $initiatedBy): array
    {
        $account = $application->account;

        if (! $account || ! $account->email) {
            throw new \Exception('Account or account email is missing.');
        }

        if (! $initiatedBy->email) {
            throw new \Exception('The signed-in user has no email address.');
        }

        $accessToken = $this->docuSignService->getAccessToken();

        // Locked, pre-filled text - identical for both roles, same pattern the main
        // contract uses for its fee fields (locked tabs need to be duplicated onto every
        // recipient's tabs array, not just one).
        $sharedTextTabs = [
            [
                'documentId' => '1',
                'anchorString' => 'Please accept this as formal notice that we',
                'anchorXOffset' => '10',
                'anchorYOffset' => '-5',
                'anchorUnits' => 'pixels',
                'anchorIgnoreIfNotPresent' => 'false',
                'anchorMatchWholeWord' => 'true',
                'width' => '250',
                'height' => '15',
                'value' => $accountName,
                'locked' => true,
                'font' => 'Arial',
                'fontSize' => 'Size9',
                'tabLabel' => 'cashflows_account_name',
            ],
            [
                'documentId' => '1',
                'anchorString' => 'Kind regards,',
                'anchorXOffset' => '0',
                'anchorYOffset' => '20',
                'anchorUnits' => 'pixels',
                'anchorIgnoreIfNotPresent' => 'false',
                'anchorMatchWholeWord' => 'true',
                'width' => '250',
                'height' => '15',
                'value' => $recipientName,
                'locked' => true,
                'font' => 'Arial',
                'fontSize' => 'Size9',
                'tabLabel' => 'cashflows_recipient_name',
            ],
        ];

        // Sign/date position measured directly off the calibrated 595x842px render of
        // the uploaded template PDF (top-left origin, matching this app's existing
        // DocuSign xPosition/yPosition convention) - not an anchor, since there's no
        // reliable surrounding text to anchor a signature block to.
        $merchantTabs = [
            'textTabs' => $sharedTextTabs,
            'signHereTabs' => [
                [
                    'documentId' => '1',
                    'pageNumber' => '1',
                    'xPosition' => '90',
                    'yPosition' => '235',
                    'required' => true,
                    'tabLabel' => 'cashflows_notice_signature',
                ],
            ],
            'dateSignedTabs' => [
                [
                    'documentId' => '1',
                    'pageNumber' => '1',
                    'xPosition' => '360',
                    'yPosition' => '235',
                    'required' => true,
                    'tabLabel' => 'cashflows_notice_signature_date',
                ],
            ],
        ];

        $envelopeDefinition = [
            'emailSubject' => "Cashflows Switch Notice - {$application->name}",
            'templateId' => self::TEMPLATE_ID,
            'templateRoles' => [
                [
                    'email' => $initiatedBy->email,
                    'name' => $initiatedBy->name ?? $initiatedBy->email,
                    'roleName' => 'Cashflows Reviewer',
                    'routingOrder' => '1',
                    'clientUserId' => 'cashflows-user-' . $application->id,
                    'tabs' => ['textTabs' => $sharedTextTabs],
                ],
                [
                    'email' => $account->email,
                    'name' => $account->name ?? $application->trading_name ?? $account->email,
                    'roleName' => 'Account Merchant',
                    'routingOrder' => '2',
                    'clientUserId' => 'cashflows-merchant-' . $application->id,
                    'tabs' => $merchantTabs,
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
                'request' => $envelopeDefinition,
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
}
