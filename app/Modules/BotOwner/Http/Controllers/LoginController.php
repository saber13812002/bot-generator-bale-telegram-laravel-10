<?php

namespace App\Modules\BotOwner\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private readonly BotOwnerAuthServiceInterface $authService,
    ) {
    }

    public function show(Request $request): View
    {
        return view('bot-owner.login', [
            'redirect' => $request->query('redirect'),
        ]);
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $result = $this->authService->sendOtp(
            $request->input('phone'),
            $request->ip()
        );

        if (!$result['success']) {
            return back()->withInput()->with('error', $result['message']);
        }

        return back()
            ->withInput()
            ->with('success', $result['message'])
            ->with('otp_sent', true);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'string', 'min:3', 'max:8'],
        ]);

        $result = $this->authService->verifyOtp(
            $request->input('phone'),
            $request->input('otp')
        );

        if (!$result['success']) {
            return back()->withInput()->with('error', $result['message'])->with('otp_sent', true);
        }

        $redirect = $request->input('redirect', route('bot-owner.dashboard'));

        return redirect()->to($redirect)->with('success', $result['message']);
    }

    public function logout(): RedirectResponse
    {
        $this->authService->logoutSession();

        return redirect()->route('bot-owner.intro')->with('success', trans('bot-owner.logout_success'));
    }
}
