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
        Log::info('Google2FAService initialized.');
    }

    public function generateSecretKey()
    {
        $secretKey = $this->google2fa->generateSecretKey();
        Log::info('Generated new MFA secret key.', ['secret_key' => $secretKey]);
        return $secretKey;
    }

    public function getQRCodeUrl($email, $secretKey)
    {
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name', 'Garrison T&A System'),
            $email,
            $secretKey
        );
        Log::info('Generated QR Code URL for MFA.', ['email' => $email, 'qr_code_url' => $qrCodeUrl]);
        return $qrCodeUrl;
    }

    public function verifyKey($secret, $code, $window = 2)
    {
        try {
            if (empty($secret)) {
                Log::error('Cannot verify empty secret key.');
                return false;
            }

            $google2fa = new Google2FA();
            $isValid = $google2fa->verifyKey($secret, $code, $window);
            Log::info('MFA code verification attempted.', [
                'secret_length' => strlen($secret),
                'code_length' => strlen($code),
                'is_valid' => $isValid
            ]);

            return $isValid;
        } catch (\Exception $e) {
            Log::error('Google2FA verification error.', [
                'error_message' => $e->getMessage(),
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
        Log::info('Generated recovery codes for MFA.', ['recovery_codes' => $recoveryCodes]);
        return $recoveryCodes;
    }

    public function enableMfaForUser($userId, $secretKey)
    {
        $recoveryCodes = $this->generateRecoveryCodes();

        DB::table('login')->where('user_login_id', $userId)->update([
            'mfa_enabled' => true,
            'google2fa_secret' => $secretKey,
            'recovery_codes' => json_encode($recoveryCodes)
        ]);

        Log::info('MFA enabled for user.', [
            'user_id' => $userId,
            'secret_key' => $secretKey,
            'recovery_codes' => $recoveryCodes
        ]);

        return $recoveryCodes;
    }

    public function disableMfaForUser($userId)
    {
        DB::table('login')->where('user_login_id', $userId)->update([
            'mfa_enabled' => false,
            'google2fa_secret' => null,
            'recovery_codes' => null
        ]);

        Log::info('MFA disabled for user.', ['user_id' => $userId]);
    }

    public function isMfaEnabled($userId)
    {
        $user = DB::table('login')->where('user_login_id', $userId)->first();

        Log::debug('MFA Status Check.', [
            'user_id' => $userId,
            'user_record' => $user,
            'mfa_enabled' => $user ? $user->mfa_enabled : null
        ]);

        return (bool) ($user && $user->mfa_enabled);
    }

    public function getSecretKey($userId)
    {
        $user = DB::table('login')->where('user_login_id', $userId)->first();

        if (!$user || empty($user->google2fa_secret)) {
            Log::error('MFA Secret not found or empty for user.', ['user_id' => $userId]);
            return null;
        }

        $secret = trim($user->google2fa_secret);

        Log::debug('MFA Secret retrieved.', [
            'user_id' => $userId,
            'secret_length' => strlen($secret),
            'secret' => $secret
        ]);

        if (strlen($secret) < 16) {
            Log::warning('Secret key too short, attempting to re-encode.', ['user_id' => $userId]);
            $secret = str_pad($secret, 16, 'A', STR_PAD_RIGHT);
        }

        return $secret;
    }

    public function verifyRecoveryCode($userId, $recoveryCode)
    {
        $user = DB::table('login')->where('user_login_id', $userId)->first();

        if (!$user || !$user->recovery_codes) {
            Log::warning('No recovery codes found for user.', ['user_id' => $userId]);
            return false;
        }

        $recoveryCodes = json_decode($user->recovery_codes, true);

        if (($key = array_search($recoveryCode, $recoveryCodes)) !== false) {
            unset($recoveryCodes[$key]);

            DB::table('login')->where('user_login_id', $userId)->update([
                'recovery_codes' => json_encode(array_values($recoveryCodes))
            ]);

            Log::info('Recovery code verified and removed.', [
                'user_id' => $userId,
                'used_code' => $recoveryCode,
                'remaining_codes' => $recoveryCodes
            ]);

            return true;
        }

        Log::warning('Invalid recovery code attempted.', [
            'user_id' => $userId,
            'attempted_code' => $recoveryCode
        ]);

        return false;
    }

    /**
     * Verify a code for a user
     * Will check both regular 2FA codes and recovery codes
     */
    public function verifyCode($userId, $code)
    {
        $user = DB::table('login')->where('user_login_id', $userId)->first();
        
        if (!$user || !$user->google2fa_secret) {
            return false;
        }
        
        // If code is 6 digits, treat as regular 2FA code
        if (preg_match('/^\d{6}$/', $code)) {
            return $this->google2fa->verifyKey($user->google2fa_secret, $code);
        }
        
        // Otherwise, treat as recovery code
        if ($user->recovery_codes) {
            $recoveryCodes = json_decode($user->recovery_codes, true);
            
            // Check if the code exists in recovery codes
            if (in_array($code, $recoveryCodes)) {
                // Remove the used recovery code
                $recoveryCodes = array_filter($recoveryCodes, function($rc) use ($code) {
                    return $rc !== $code;
                });
                
                // Update recovery codes in database
                DB::table('login')->where('user_login_id', $userId)->update([
                    'recovery_codes' => json_encode(array_values($recoveryCodes))
                ]);
                
                // Log recovery code usage
                Log::info("User {$userId} used a recovery code to authenticate");
                
                return true;
            }
        }
        
        return false;
    }
}