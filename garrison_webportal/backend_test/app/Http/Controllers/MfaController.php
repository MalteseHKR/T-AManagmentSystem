<?php

namespace App\Http\Controllers;

use App\Services\Google2FAService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controller;
use BaconQrCode\Writer;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;

class MfaController extends Controller
{
    protected $google2faService;

    public function __construct(Google2FAService $google2faService)
    {
        $this->middleware('auth');
        $this->google2faService = $google2faService;
    }

    public function index()
    {
        $userId = Auth::id();
        $isMfaEnabled = $this->google2faService->isMfaEnabled($userId);

        return view('auth.mfa.index', compact('isMfaEnabled'));
    }

    public function setup()
    {
        $userId = Auth::id();
        $email = Auth::user()->email;

        if ($this->google2faService->isMfaEnabled($userId)) {
            return redirect()->route('mfa.index')->with('info', 'MFA is already enabled for your account.');
        }

        $secretKey = $this->google2faService->generateSecretKey();

        $otpauthUrl = 'otpauth://totp/' . config('app.name', 'Garrison') . ':' . $email .
            '?secret=' . $secretKey .
            '&issuer=' . config('app.name', 'Garrison') .
            '&algorithm=SHA1&digits=6&period=30';

        try {
            $renderer = new ImageRenderer(
                new RendererStyle(200),
                new SvgImageBackEnd()
            );
            $writer = new Writer($renderer);
            $svgOutput = $writer->writeString($otpauthUrl);
            $qrCodeDataUri = 'data:image/svg+xml;base64,' . base64_encode($svgOutput);
        } catch (\Exception $e) {
            $svgContent = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200">
    <rect width="200" height="200" fill="#f5f5f5" />
    <text x="20" y="80" font-family="Arial" font-size="12" fill="#000">
        QR code unavailable.
    </text>
</svg>
SVG;
            $qrCodeDataUri = 'data:image/svg+xml;base64,' . base64_encode($svgContent);
        }

        session(['mfa_secret' => $secretKey]);

        return view('auth.mfa.setup', [
            'qrCodeDataUri' => $qrCodeDataUri,
            'secretKey' => $secretKey,
            'otpauthUrl' => $otpauthUrl
        ]);
    }

    public function enable(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6|regex:/^[0-9]+$/'
        ]);

        $userId = Auth::id();
        $secretKey = session('mfa_secret');

        if (!$secretKey) {
            return redirect()->route('mfa.setup')->with('error', 'Session expired. Please try again.');
        }

        if ($this->google2faService->verifyKey($secretKey, $request->code)) {
            $recoveryCodes = $this->google2faService->enableMfaForUser($userId, $secretKey);
            session()->forget('mfa_secret');

            return view('auth.mfa.recovery-codes', compact('recoveryCodes'));
        }

        return redirect()->back()->withErrors(['code' => 'Invalid verification code. Please try again.']);
    }

    public function disable(Request $request)
    {
        $request->validate([
            'confirm' => 'required|in:confirm'
        ]);

        $userId = Auth::id();
        $this->google2faService->disableMfaForUser($userId);

        return redirect()->route('mfa.index')->with('success', 'Two-factor authentication has been disabled.');
    }

    public function showRecoveryCodes()
    {
        $userId = Auth::id();
        $user = DB::table('login')->where('user_login_id', $userId)->first();

        if (!$user || !$user->recovery_codes) {
            return redirect()->route('mfa.index')->with('error', 'No recovery codes available.');
        }

        $recoveryCodes = json_decode($user->recovery_codes, true);

        return view('auth.mfa.recovery-codes', compact('recoveryCodes'));
    }

    public function regenerateRecoveryCodes()
    {
        $userId = Auth::id();

        if (!$this->google2faService->isMfaEnabled($userId)) {
            return redirect()->route('mfa.index')->with('error', 'MFA is not enabled for your account.');
        }

        $recoveryCodes = $this->google2faService->generateRecoveryCodes();

        DB::table('login')->where('user_login_id', $userId)->update([
            'recovery_codes' => json_encode($recoveryCodes)
        ]);

        return view('auth.mfa.recovery-codes', compact('recoveryCodes'));
    }

    public function isEnabled($userId = null)
    {
        $userId = $userId ?? Auth::id();
        return $this->google2faService->isMfaEnabled($userId);
    }
}
