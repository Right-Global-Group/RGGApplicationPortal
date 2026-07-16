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
        if (auth()->guard('account')->check()) {
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

        // Only `body` is accepted from input — merchants can never set
        // is_internal (it defaults to false on the model).
        $validated = Request::validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = $application->messages()->create([
            ...$author,
            'body' => $validated['body'],
            // Snapshot where the application was when this was said
            'current_step' => $application->status?->current_step,
        ]);

        event(new ApplicationMessagePosted($message));

        return Redirect::back()->with('success', 'Message posted.');
    }
}
