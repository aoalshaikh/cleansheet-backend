<?php

namespace App\Services\Auth;

use App\Models\Otp;
use App\Models\User;
use App\Services\Communication\SmsService;
use Carbon\Carbon;
use Illuminate\Support\Str;

class OtpService
{
    protected SmsService $smsService;
    protected int $codeLength = 6;
    protected int $expiryMinutes = 10;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    public function generate(User $user, string $type, string $identifier): ?Otp
    {
        $code = $this->generateCode();
        $message = $this->getOtpMessage($code);

        if ($type === 'phone' && !$this->smsService->send($identifier, $message)) {
            return null;
        }

        return $user->otps()->create([
            'code' => $code,
            'type' => $type,
            'identifier' => $identifier,
            'expires_at' => Carbon::now()->addMinutes($this->expiryMinutes),
        ]);
    }

    public function verify(User $user, string $type, string $identifier, string $code): bool
    {
        $otp = $user->otps()
            ->where('type', $type)
            ->where('identifier', $identifier)
            ->where('code', $code)
            ->whereNull('verified_at')
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$otp) {
            return false;
        }

        $otp->update(['verified_at' => Carbon::now()]);
        return true;
    }

    public function cleanup(): void
    {
        Otp::where('expires_at', '<=', Carbon::now())->delete();
    }

    protected function generateCode(): string
    {
        return str_pad((string) random_int(0, pow(10, $this->codeLength) - 1), $this->codeLength, '0', STR_PAD_LEFT);
    }

    protected function getOtpMessage(string $code): string
    {
        return "Your verification code is: {$code}. Valid for {$this->expiryMinutes} minutes.";
    }
}
