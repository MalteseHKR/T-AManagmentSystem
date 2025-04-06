<?php

namespace App\Services;

use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Facades\DB;
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
    
    public function verifyKey($secretKey, $oneTimePassword)
    {
        return $this->google2fa->verifyKey($secretKey, $oneTimePassword);
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
        return $user ? $user->google2fa_secret : null;
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