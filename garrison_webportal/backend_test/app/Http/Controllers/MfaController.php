<?php

namespace App\Http\Controllers;

use App\Services\Google2FAService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

use Illuminate\Routing\Controller;

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
        $userId = session('user_id');
        $isMfaEnabled = $this->google2faService->isMfaEnabled($userId);
        
        return view('auth.mfa.index', compact('isMfaEnabled'));
    }
    
    public function setup()
    {
        $userId = session('user_id');
        $email = session('user_email');
        
        if ($this->google2faService->isMfaEnabled($userId)) {
            return redirect()->route('mfa.index')->with('info', 'MFA is already enabled for your account.');
        }
        
        $secretKey = $this->google2faService->generateSecretKey();
        $qrCodeUrl = $this->google2faService->getQRCodeUrl($email, $secretKey);
        
        // Store temporary secret key in session
        session(['mfa_temp_secret' => $secretKey]);
        
        return view('auth.mfa.setup', compact('secretKey', 'qrCodeUrl'));
    }
    
    public function showSetupForm()
    {
        if (!\Illuminate\Support\Facades\Auth::check()) {
            return redirect('/login');
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        $google2fa = app(\PragmaRX\Google2FA\Google2FA::class);
        
        // Generate the secret key
        $secretKey = $google2fa->generateSecretKey();
        
        // Store the secret key in the session for later verification
        session(['2fa_secret' => $secretKey]);
        
        // Generate the QR code as an image
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name', 'Garrison T&A System'),
            $user->email,
            $secretKey
        );
        
        // Log QR code generation for debugging
        Log::debug('Generated QR code for MFA setup', [
            'user_id' => $user->user_id,
            'has_qr_data' => !empty($qrCodeUrl)
        ]);
        
        return view('auth.mfa.setup', compact('secretKey', 'qrCodeUrl'));
    }
    
    public function enable(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6|regex:/^[0-9]+$/'
        ]);
        
        $userId = session('user_id');
        $secretKey = session('mfa_temp_secret');
        
        if (!$secretKey) {
            return redirect()->route('mfa.setup')
                ->with('error', 'Session expired. Please try again.');
        }
        
        // Verify the code
        if ($this->google2faService->verifyKey($secretKey, $request->code)) {
            // Enable MFA for the user
            $recoveryCodes = $this->google2faService->enableMfaForUser($userId, $secretKey);
            
            // Clear the temporary secret from session
            session()->forget('mfa_temp_secret');
            
            return view('auth.mfa.recovery-codes', compact('recoveryCodes'));
        }
        
        return redirect()->back()
            ->withErrors(['code' => 'Invalid verification code. Please try again.']);
    }
    
    public function disable(Request $request)
    {
        $request->validate([
            'confirm' => 'required|in:confirm'
        ]);
        
        $userId = session('user_id');
        $this->google2faService->disableMfaForUser($userId);
        
        return redirect()->route('mfa.index')
            ->with('success', 'Two-factor authentication has been disabled.');
    }
    
    public function showRecoveryCodes()
    {
        $userId = session('user_id');
        $user = DB::table('login')->where('user_id', $userId)->first();
        
        if (!$user || !$user->recovery_codes) {
            return redirect()->route('mfa.index')
                ->with('error', 'No recovery codes available.');
        }
        
        $recoveryCodes = json_decode($user->recovery_codes, true);
        
        return view('auth.mfa.recovery-codes', compact('recoveryCodes'));
    }
    
    public function regenerateRecoveryCodes()
    {
        $userId = session('user_id');
        
        if (!$this->google2faService->isMfaEnabled($userId)) {
            return redirect()->route('mfa.index')
                ->with('error', 'MFA is not enabled for your account.');
        }
        
        $recoveryCodes = $this->google2faService->generateRecoveryCodes();
        
        DB::table('login')->where('user_id', $userId)->update([
            'recovery_codes' => json_encode($recoveryCodes)
        ]);
        
        return view('auth.mfa.recovery-codes', compact('recoveryCodes'));
    }
}