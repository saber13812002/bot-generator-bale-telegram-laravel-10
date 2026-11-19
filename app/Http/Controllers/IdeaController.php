<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use App\Models\IdeaMessage;
use App\Modules\BaleOtp\Support\PhoneNormalizer;
use App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class IdeaController extends Controller
{
    public function __construct(
        private readonly BotOwnerAuthServiceInterface $authService,
    ) {}

    public function create(Request $request): View
    {
        $endpointId = $request->query('bot');
        $owner = $this->authService->currentOwner();

        return view('idea.create', compact('endpointId', 'owner'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'endpoint_id' => 'nullable|string|max:100',
            'title' => 'required|string|max:255',
            'description' => 'required|string|min:10',
            'submitter_name' => 'nullable|string|max:100',
            'submitter_contact' => 'nullable|string|max:200',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:200',
        ]);

        $owner = $this->authService->currentOwner();
        $phone = $validated['phone'] ?? ($owner?->phone ?? null);

        $idea = Idea::create([
            'tracking_code' => Idea::generateTrackingCode(),
            'endpoint_id' => $validated['endpoint_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'submitter_name' => $validated['submitter_name'] ?? ($owner?->name ?? null),
            'submitter_contact' => $validated['submitter_contact'] ?? null,
            'phone' => $phone ? PhoneNormalizer::normalize($phone) : null,
            'email' => $validated['email'] ?? null,
            'notify_by_bot' => $owner !== null,
            'status' => 'pending',
        ]);

        Log::info('💡 New idea submitted', [
            'tracking_code' => $idea->tracking_code,
            'title' => $idea->title,
        ]);

        return redirect()->route('idea.show', $idea->tracking_code)
            ->with('success', '✅ ایده شما با موفقیت ثبت شد! کد رهگیری: ' . $idea->tracking_code);
    }

    public function show(string $trackingCode): View
    {
        $idea = Idea::byTrackingCode($trackingCode)->firstOrFail();
        $messages = $idea->messages()->orderByDesc('created_at')->get();
        $owner = $this->authService->currentOwner();

        return view('idea.show', compact('idea', 'messages', 'owner'));
    }

    public function message(Request $request, string $trackingCode): RedirectResponse
    {
        $idea = Idea::byTrackingCode($trackingCode)->firstOrFail();

        $validated = $request->validate([
            'message' => 'required|string|min:1|max:5000',
        ]);

        IdeaMessage::create([
            'idea_id' => $idea->id,
            'sender_type' => 'user',
            'sender_name' => $validated['submitter_name'] ?? 'کاربر',
            'message' => $validated['message'],
        ]);

        Log::info('💬 New message on idea', ['tracking_code' => $trackingCode]);

        return redirect()->route('idea.show', $trackingCode)
            ->with('success', '✅ پیام شما ثبت شد.');
    }

    // ===== OTP Auth =====
    public function login(): View
    {
        return view('idea.login');
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $request->validate(['phone' => 'required|string|max:20']);

        $result = $this->authService->sendOtp($request->input('phone'), $request->ip());

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        )->with('otp_sent', $result['success']);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'phone' => 'required|string|max:20',
            'otp' => 'required|string|min:3|max:8',
        ]);

        $result = $this->authService->verifyOtp($request->input('phone'), $request->input('otp'));

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return redirect()->route('idea.dashboard')
            ->with('success', '✅ ورود موفق. به کارتابل ایده‌های خود خوش آمدید.');
    }

    public function dashboard(): View
    {
        $owner = $this->authService->currentOwner();

        if (!$owner) {
            return view('idea.login');
        }

        $ideas = Idea::byPhone($owner->phone)
            ->recent()
            ->get();

        return view('idea.dashboard', compact('ideas', 'owner'));
    }

    public function logout(): RedirectResponse
    {
        $this->authService->logoutSession();
        return redirect()->route('idea.create')->with('success', 'خروج موفق.');
    }

    // ===== Email Verify =====
    public function verifyEmail(Request $request, string $trackingCode): RedirectResponse
    {
        $idea = Idea::byTrackingCode($trackingCode)->firstOrFail();

        if ($idea->isEmailVerified()) {
            return redirect()->route('idea.show', $trackingCode)
                ->with('info', '✅ ایمیل شما قبلاً تأیید شده است.');
        }

        $idea->update(['email_verified_at' => now()]);

        Log::info('📧 Email verified for idea', ['tracking_code' => $trackingCode]);

        return redirect()->route('idea.show', $trackingCode)
            ->with('success', '✅ ایمیل شما با موفقیت تأیید شد.');
    }
}
