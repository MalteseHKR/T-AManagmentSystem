<?php

namespace App\Services;

use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Google2FAService
{
    protected $google2fa;
    
    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }
    
    public function generateSecretKey()
    {
        return $this->google2fa->generateSecretKey();
    }
    
    public function getQRCodeUrl($email, $secretKey)
    {
        return $this->google2fa->getQRCodeUrl(
            config('app.name', 'Garrison T&A System'),
            $email,
            $secretKey
        );
    }
    
    public function verifyKey($secret, $code, $window = 2)
    {
        try {
            if (empty($secret)) {
                Log::error("Cannot verify empty secret key");
                return false;
            }
            
            $google2fa = new \PragmaRX\Google2FA\Google2FA();
            
            // Use a wider window (2 means ±2 30-second windows, or ±1 minute)
            // This helps with time synchronization issues
            return $google2fa->verifyKey($secret, $code, $window);
        } catch (\Exception $e) {
            Log::error("Google2FA verification error: " . $e->getMessage(), [
                'code_length' => strlen($code),
                'secret_length' => strlen($secret)
            ]);
            return false;
        }
    }
    
    public function generateRecoveryCodes()
    {
        $recoveryCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = Str::random(10);
        }
        return $recoveryCodes;
    }
    
    public function enableMfaForUser($userId, $secretKey)
    {
        $recoveryCodes = $this->generateRecoveryCodes();
        
        DB::table('login')->where('user_id', $userId)->update([
            'mfa_enabled' => true,
            'google2fa_secret' => $secretKey,
            'recovery_codes' => json_encode($recoveryCodes)
        ]);
        
        return $recoveryCodes;
    }
    
    public function disableMfaForUser($userId)
    {
        DB::table('login')->where('user_id', $userId)->update([
            'mfa_enabled' => false,
            'google2fa_secret' => null,
            'recovery_codes' => null
        ]);
    }
    
    public function isMfaEnabled($userId)
    {
        $user = DB::table('login')->where('user_id', $userId)->first();
        return $user && $user->mfa_enabled;
    }
    
    public function getSecretKey($userId)
    {
        $user = DB::table('login')->where('user_id', $userId)->first();
        
        if (!$user || empty($user->google2fa_secret)) {
            Log::error("MFA Secret not found or empty for user", ['user_id' => $userId]);
            return null;
        }
        
        // Ensure the secret is properly formatted - remove any whitespace
        $secret = trim($user->google2fa_secret);
        
        // Debug log the length and value
        Log::debug("MFA Secret retrieved", [
            'user_id' => $userId,
            'secret_length' => strlen($secret),
            'secret' => $secret
        ]);
        
        // If needed, re-encode the secret to ensure proper base32 format
        if (strlen($secret) < 16) {
            Log::warning("Secret key too short, attempting to re-encode", ['user_id' => $userId]);
            // This is a fallback, ideally you should regenerate the secret properly
            $secret = str_pad($secret, 16, 'A', STR_PAD_RIGHT);
        }
        
        return $secret;
    }
    
    public function verifyRecoveryCode($userId, $recoveryCode)
    {
        $user = DB::table('login')->where('user_id', $userId)->first();
        
        if (!$user || !$user->recovery_codes) {
            return false;
        }
        
        $recoveryCodes = json_decode($user->recovery_codes, true);
        
        if (($key = array_search($recoveryCode, $recoveryCodes)) !== false) {
            // Remove used recovery code
            unset($recoveryCodes[$key]);
            
            // Update recovery codes in database
            DB::table('login')->where('user_id', $userId)->update([
                'recovery_codes' => json_encode(array_values($recoveryCodes))
            ]);
            
            return true;
        }
        
        return false;
    }
}