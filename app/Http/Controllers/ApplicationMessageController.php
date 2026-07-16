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
     * Post a message to the application's thread (staff only for now —
     * merchant replies land in a later slice).
     */
    public function store(Application $application): RedirectResponse
    {
        if (auth()->guard('account')->check()) {
            abort(403, 'Accounts cannot post messages yet.');
        }

        $user = auth()->guard('web')->user();
        if (! $user->isAdmin() && $application->account->user_id !== $user->id) {
            abort(403, 'You can only message on applications you manage.');
        }

        $validated = Request::validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = $application->messages()->create([
            'user_id' => $user->id,
            'body' => $validated['body'],
            // Snapshot where the application was when this was said
            'current_step' => $application->status?->current_step,
        ]);

        event(new ApplicationMessagePosted($message));

        return Redirect::back()->with('success', 'Message posted.');
    }
}
