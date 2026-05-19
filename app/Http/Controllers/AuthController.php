<?php

namespace App\Http\Controllers;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use RuntimeException;
use Throwable;

class AuthController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_LOCKOUT_SECONDS = 7200;

    public function landing()
    {
        return view('landing');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $attemptsKey = $this->loginThrottleKey($request, 'attempts');
        $lockoutKey = $this->loginThrottleKey($request, 'lockout');

        if (RateLimiter::tooManyAttempts($lockoutKey, 1)) {
            return back()
                ->withErrors(['email' => $this->loginLockoutMessage($lockoutKey)])
                ->withInput();
        }

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($attemptsKey, self::LOGIN_LOCKOUT_SECONDS);

            if (RateLimiter::attempts($attemptsKey) >= self::MAX_LOGIN_ATTEMPTS) {
                RateLimiter::clear($attemptsKey);
                RateLimiter::hit($lockoutKey, self::LOGIN_LOCKOUT_SECONDS);

                return back()
                    ->withErrors(['email' => $this->loginLockoutMessage($lockoutKey)])
                    ->withInput();
            }

            return back()->withErrors(['email' => 'Invalid email or password.'])->withInput();
        }

        RateLimiter::clear($attemptsKey);
        RateLimiter::clear($lockoutKey);

        $request->session()->regenerate();

        $user = Auth::user();
        if ($user?->mfa_enabled) {
            $otp = (string) random_int(100000, 999999);
            Cache::put('login_mfa_' . $user->id, $otp, now()->addMinutes(10));

            try {
                $this->sendOtpMail($user->email, $otp);
            } catch (Throwable $e) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                Log::error('Failed to send login MFA code.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);

                return back()->withErrors(['email' => $this->otpDeliveryErrorMessage($e)])->withInput();
            }

            $request->session()->put('mfa_user_id', $user->id);
            Auth::logout();

            return redirect()->route('mfa.challenge');
        }

        return redirect()->route('dashboard');
    }

    public function showMfaChallenge(Request $request)
    {
        $userId = $request->session()->get('mfa_user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('login');
        }

        return view('auth.mfa', [
            'securityQuestion' => $user->mfa_security_answer_hash ? $user->mfa_security_question : null,
        ]);
    }

    public function verifyMfa(Request $request)
    {
        $request->validate([
            'method' => 'required|string|in:code,security_question',
            'otp' => 'nullable|digits:6',
            'security_answer' => 'nullable|string|max:191',
        ]);

        $userId = $request->session()->get('mfa_user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::findOrFail($userId);
        $verified = false;

        if ($request->method === 'code') {
            $storedOtp = Cache::get('login_mfa_' . $userId);
            $verified = $storedOtp && $storedOtp === $request->otp;
        }

        if ($request->method === 'security_question') {
            $answer = $this->normaliseSecurityAnswer((string) $request->security_answer);
            $verified = $user->mfa_security_answer_hash
                && Hash::check($answer, $user->mfa_security_answer_hash);
        }

        if (!$verified) {
            return back()->withErrors(['otp' => 'Invalid or expired verification details.']);
        }

        Cache::forget('login_mfa_' . $userId);
        $request->session()->forget('mfa_user_id');
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:80',
            'last_name' => 'required|string|max:80',
            'role' => 'required|string|in:Legal Professional,Law Student,Researcher,Business Owner,Other',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        if (! config('auth.require_registration_email_verification')) {
            $user = $this->createVerifiedUser($data);

            Auth::login($user);
            $request->session()->regenerate();

            return response()->json([
                'success' => true,
                'message' => 'Account created.',
                'redirect' => route('dashboard'),
            ]);
        }

        $otp = (string) random_int(100000, 999999);
        Cache::put('otp_' . $data['email'], $otp, now()->addMinutes(10));
        Cache::put('pending_user_' . $data['email'], $data, now()->addMinutes(15));

        try {
            $this->sendOtpMail($data['email'], $otp);
        } catch (Throwable $e) {
            Cache::forget('otp_' . $data['email']);
            Cache::forget('pending_user_' . $data['email']);

            Log::error('Failed to send registration OTP.', [
                'email' => $data['email'],
                'mailer' => config('mail.default'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $this->otpDeliveryErrorMessage($e),
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent.',
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        $storedOtp = Cache::get('otp_' . $request->email);
        if (!$storedOtp || $storedOtp !== $request->otp) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired OTP.'], 422);
        }

        $data = Cache::get('pending_user_' . $request->email);
        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Registration session expired.'], 422);
        }

        $user = $this->createVerifiedUser($data);

        Cache::forget('otp_' . $request->email);
        Cache::forget('pending_user_' . $request->email);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['success' => true, 'redirect' => route('dashboard')]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing');
    }

    protected function sendOtpMail(string $email, string $otp): void
    {
        if ($this->usesResendOtpMailer()) {
            $this->sendOtpViaResend($email, $otp);

            return;
        }

        Mail::mailer($this->otpMailer())->to($email)->send(new OtpMail($otp));
    }

    protected function otpDeliveryErrorMessage(Throwable $e): string
    {
        if ($e instanceof RuntimeException) {
            return $e->getMessage();
        }

        return 'We could not send the verification code right now. Please try again shortly.';
    }

    private function usesResendOtpMailer(): bool
    {
        return in_array(strtolower((string) config('auth.otp_mailer')), ['resend', 'resend_api'], true);
    }

    private function sendOtpViaResend(string $email, string $otp): void
    {
        $key = trim((string) config('services.resend.key'));

        if ($key === '') {
            throw new RuntimeException('Email delivery is not configured. Missing RESEND_API_KEY or RESEND_KEY.');
        }

        $fromAddress = trim((string) config('mail.from.address'));
        $fromName = trim((string) config('mail.from.name'));

        if ($fromAddress === '' || $fromAddress === 'hello@example.com') {
            throw new RuntimeException('Email delivery is not configured. Missing MAIL_FROM_ADDRESS.');
        }

        $from = $fromName === '' ? $fromAddress : "{$fromName} <{$fromAddress}>";
        $html = view('emails.otp', ['otp' => $otp])->render();

        try {
            $response = Http::timeout(20)
                ->withToken($key)
                ->acceptJson()
                ->asJson()
                ->post('https://api.resend.com/emails', [
                    'from' => $from,
                    'to' => [$email],
                    'subject' => 'Your JustConnect verification code',
                    'html' => $html,
                    'text' => "Your JustConnect verification code is {$otp}. It expires in 10 minutes.",
                ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Resend email API could not be reached. ' . $e->getMessage(), 0, $e);
        }

        if ($response->successful()) {
            return;
        }

        $message = $response->json('message') ?: $response->body();

        throw new RuntimeException(
            'Resend could not send the verification code. HTTP '
            . $response->status()
            . '. '
            . $this->summariseMailProviderError((string) $message)
        );
    }

    private function summariseMailProviderError(string $message): string
    {
        $message = trim((string) preg_replace('/\s+/', ' ', $message));

        if ($message === '') {
            return 'Check RESEND_KEY and MAIL_FROM_ADDRESS.';
        }

        return mb_substr($message, 0, 220);
    }

    private function otpMailer(): string
    {
        $defaultMailer = config('mail.default');
        $transport = config("mail.mailers.{$defaultMailer}.transport");

        if (! in_array($defaultMailer, ['array', 'log'], true) && ! in_array($transport, ['array', 'log'], true)) {
            return $defaultMailer;
        }

        if ($this->hasUsableSmtpConfig()) {
            return 'smtp';
        }

        throw new RuntimeException($this->mailConfigurationProblem());
    }

    private function hasUsableSmtpConfig(): bool
    {
        return $this->mailConfigurationProblem() === null;
    }

    private function mailConfigurationProblem(): ?string
    {
        $smtp = config('mail.mailers.smtp', []);
        $missing = [];
        $host = strtolower(trim((string) ($smtp['host'] ?? '')));

        if ($host === '' || in_array($host, ['127.0.0.1', 'localhost'], true)) {
            $missing[] = 'MAIL_HOST';
        }

        if (trim((string) ($smtp['port'] ?? '')) === '') {
            $missing[] = 'MAIL_PORT';
        }

        if (trim((string) ($smtp['username'] ?? '')) === '') {
            $missing[] = 'MAIL_USERNAME';
        }

        if (trim((string) ($smtp['password'] ?? '')) === '') {
            $missing[] = 'MAIL_PASSWORD';
        }

        if ($missing) {
            return 'Email delivery is not configured. Missing or invalid: ' . implode(', ', $missing) . '.';
        }

        return null;
    }

    private function createVerifiedUser(array $data): User
    {
        return User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'role' => $data['role'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
        ]);
    }

    private function normaliseSecurityAnswer(string $answer): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $answer)));
    }

    private function loginThrottleKey(Request $request, string $bucket): string
    {
        $email = strtolower(trim((string) $request->input('email')));

        return "login:{$bucket}:" . sha1($email . '|' . $request->ip());
    }

    private function loginLockoutMessage(string $lockoutKey): string
    {
        $seconds = max(1, RateLimiter::availableIn($lockoutKey));
        $minutes = (int) ceil($seconds / 60);

        if ($minutes < 60) {
            return "Too many failed login attempts. Please try again in {$minutes} minute" . ($minutes === 1 ? '.' : 's.');
        }

        $hours = (int) ceil($minutes / 60);

        return "Too many failed login attempts. Please try again in {$hours} hour" . ($hours === 1 ? '.' : 's.');
    }
}
