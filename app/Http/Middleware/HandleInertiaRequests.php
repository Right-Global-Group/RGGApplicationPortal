<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => function () use ($request) {
                // Check if user is logged in via web guard
                $user = Auth::guard('web')->user();
                if ($user) {
                    return [
                        'user' => [
                            'id' => $user->id,
                            'first_name' => $user->first_name,
                            'last_name' => $user->last_name,
                            'email' => $user->email,
                            'isAdmin' => $user->isAdmin(),
                            'account' => null, // Users don't have account relation
                        ],
                    ];
                }

                // Check if account is logged in via account guard
                $account = Auth::guard('account')->user();
                if ($account) {
                    return [
                        'user' => [
                            'id' => $account->id,
                            'first_name' => $account->name,
                            'last_name' => '',
                            'email' => $account->email,
                            'isAdmin' => false, // Accounts are never admin
                            'account' => [
                                'id' => $account->id,
                                'name' => $account->name,
                            ],
                        ],
                    ];
                }

                return ['user' => null];
            },
            'flash' => function () use ($request) {
                return [
                    'success' => $request->session()->get('success'),
                    'error' => $request->session()->get('error'),
                ];
            },
        ]);
    }
}