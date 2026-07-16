<?php

namespace App\Http\Controllers;

use App\Events\ApplicationMessagePosted;
use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;

class ApplicationMessageController extends Controller
{
    /**
     * Post a message to the application's thread — staff or the merchant
     * who owns the application.
     */
    public function store(Application $application): RedirectResponse
    {
        $isStaff = ! auth()->guard('account')->check();

        if (! $isStaff) {
            // Merchants can only post on their own applications
            if ($application->account_id !== auth()->guard('account')->id()) {
                abort(403, 'You can only message on your own applications.');
            }

            $author = ['account_id' => auth()->guard('account')->id()];
        } else {
            $user = auth()->guard('web')->user();
            if (! $user->isAdmin() && $application->account->user_id !== $user->id) {
                abort(403, 'You can only message on applications you manage.');
            }

            $author = ['user_id' => $user->id];
        }

        // Merchants can never set is_internal — the rule only exists on the
        // staff path, so a merchant-supplied value is discarded and the model
        // default (false) applies.
        $validated = Request::validate([
            'body' => ['required', 'string', 'max:5000'],
            ...($isStaff ? ['is_internal' => ['sometimes', 'boolean']] : []),
        ]);

        $message = $application->messages()->create([
            ...$author,
            'body' => $validated['body'],
            'is_internal' => $isStaff && ($validated['is_internal'] ?? false),
            // Snapshot where the application was when this was said
            'current_step' => $application->status?->current_step,
        ]);

        event(new ApplicationMessagePosted($message));

        return Redirect::back()->with('success', 'Message posted.');
    }
}
