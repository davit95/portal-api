<?php


namespace App\Services;

use App\User;
use App\ActivationCode;
use Carbon\Carbon;
use Storage;
use Illuminate\Support\Str;

class AuthService
{
    /**
     * @param $data
     * @return object
     */
    public function createUser(array $data, bool $isVerified = false) : ?object
    {
        $data['is_verified'] = $isVerified;
        return User::create($data);
    }

    /**
     * @param string $email
     * @return object
     */
    public function getUserByEmail(string $email) : ?object
    {
        return User::where('email', $email)->first();
    }

    /**
     * @param int $id
     * @return object
     */
    public function loginUserById(int $id) : ?object
    {
        return auth()->loginUsingId($id);
    }

    /**
     * @param object $user
     * @return string
     */
    public function createTokenForUser(object $user) : ?string
    {
        if (null !== $token = $user->createToken('authToken')) {
            return $token->accessToken;
        }
        return null;
    }

    /**
     * @return string
     */
    public function generateActivationCode() : string
    {
        return Str::random(40);
    }

    public function sendActivationLink()
    {
        return true;
    }

    /**
     * @param string $code
     * @param string $email
     * @return object
     */
    public function createActivationCode(string $code, string $email) : object
    {
        return ActivationCode::create([
            'email' => $email,
            'code' => $code,
        ]);
    }

    /**
     * @param string $code
     * @return object|null
     */
    public function getActivationCode(string $code) : ?object
    {
        return $this->getActivationCodeByGivenCode($code);
    }

    public function getActivationCodeByEmail(string $email): ?object
    {
        return ActivationCode::where('email', $email)->first();
    }

    /**
     * @param object $code
     * @return bool
     */
    public function deleteActivationCode(object $code) : bool
    {
        return $code->delete();
    }

    /**
     * @param string $code
     * @return object
     */
    private function getActivationCodeByGivenCode(string $code) : ?object
    {
        return ActivationCode::where('code', $code)->first();
    }

    /**
     * @param object $user
     * @return object
     */
    public function deactivateUser(object $user) : bool
    {
        return $user->update(['is_verified' => false]);
    }

    /**
     * @param object $user
     * @return bool
     */
    public function verifyUser(object $user) : bool
    {
        return $user->update(['is_verified' => true]);
    }

    /**
     * @param string $code
     * @return bool
     */
    public function checkEmailCodeIsActive(string $code) : bool
    {
        $activationCode = $this->getActivationCodeByGivenCode($code);
        if (!$activationCode) {
            return false;
        }
        $start = Carbon::parse($activationCode->created_at);
        $end = Carbon::now();
        $timeDifference = $end->diffInSeconds($start);
//        dd($activationCode, $timeDifference);
        if ($timeDifference > env('ACTIVATION_LINK_EXPIRED_TIME_BY_SECONDS')) {
            $activationCode->delete();
            return false;
        }
        return true;
    }

    /**
     * @param object $user
     * @return array|string[]
     */
    public function createAccessToken(object $user) : array
    {
        $accessToken = $this->createTokenForUser($user);
        if ($accessToken) {
            return [
                'status' => 'success',
                'message' => 'You have successfully logged in',
                'user' => $user,
                'access_token' => $accessToken
            ];
        }
        return [
            'status' => 'error',
            'message' => 'can not create access token'
        ];
    }

    /**
     * @param string $newEmail
     * @return bool
     */
    public function writeEmailInFile(string $oldEmail, string $newEmail = '') : bool
    {
        return Storage::disk('local')->append('emails.txt', $oldEmail . '-' . $newEmail);
    }
}
