<?php

namespace App\Http\Controllers;

use App\Notifications\SendLoginLink;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\Request;
use App\Services\AuthService;
use App\Http\Requests\UserRequest;
use Illuminate\Support\Facades\Validator;


class AuthController extends Controller
{

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * @param UserRequest $userRequest
     * @param AuthService $authService
     * @return array|string[]
     */
    public function register(Request $request)
    {
        $code = $request->get('code');
        if (!$this->authService->checkEmailCodeIsActive($code)) {
            return [
                'status' => 'error',
                'message' => 'invalid activation code. Please regenerate it'
            ];
        }

        $activationCode = $this->authService->getActivationCode($code);
        if (!$activationCode) {
            return [
                'status' => 'error',
                'message' => 'Can not find given activation code. Please regenerate it'
            ];
        }
        $user = $this->authService->getUserByEmail($activationCode->email);
        if ($user) {
            $this->authService->deleteActivationCode($activationCode);
            $this->authService->verifyUser($user);
            $accessToken = $this->authService->createTokenForUser($user);
            if ($accessToken) {
                return [
                    'status' => 'success',
                    'message' => 'You hav successfully logged in',
                    'user' => auth()->user(),
                    'access_token' => $accessToken
                ];
            }
            return [
                'status' => 'error',
                'message' => 'can not create access token'
            ];
        } else {
            $user = $this->authService->createUser(['email' => $activationCode->email], true);
            $this->authService->writeEmailInFile($user->email);
            if ($user) {
                $this->authService->deleteActivationCode($activationCode);
                return $this->authService->createAccessToken($user);
            }
            return [
                'status' => 'error',
                'message' => 'can not create user'
            ];
        }
    }

    /**
     * @param Request $request
     * @param AuthService $authService
     * @return array|string[]
     */
    public function login(Request $request)
    {
        $validator = $this->validateEmail($request);
        if ($validator->fails()) {
            return [
                'status' => 'error',
                'message' => $validator->errors()
            ];
        }
        $email = $request->get('email');

        $user = $this->authService->getUserByEmail($email);

        if ($user && $user->is_verified) {
            $accessToken = $this->authService->createTokenForUser($user);
            if ($accessToken) {
                return [
                    'status' => 'success',
                    'message' => 'You hav successfully logged in',
                    'user' => auth()->user(),
                    'access_token' => $accessToken
                ];
            }
            return [
                'status' => 'error',
                'message' => 'can not create access token'
            ];
        }

        return [
            'status' => 'error',
            'message' => 'Could not found user with email: '. $email . ': or user is not verified'
        ];
    }

    /**
     * @return array|string[]
     */
    public function logout() : array
    {
        if (auth()->check()) {
            auth()->user()->authAcessToken()->delete();
            return [
                'status' => 'success',
                'message' => 'you have successfully logged out',
                'token' => null
            ];
        }
        return [
            'status' => 'failed',
            'message' => 'you are already logged out'
        ];
    }

    /**
     * @param Request $request
     * @return string[]
     */
    public function changeEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'email|required',
            'new_email' => 'email|required',
        ]);
        if ($validator->fails()) {
            return [
                'status' => 'error',
                'message' => $validator->errors()
            ];
        }
        $email = $request->get('email');
        $newEmail = $request->get('new_email');
        if ($email === $newEmail) {
            return [
                'status' => 'warning',
                'message' => 'old email and new email are the same'
            ];
        }
        $user = $this->authService->getUserByEmail($email);
        if ($user) {
            if ($this->authService->writeEmailInFile($email, $newEmail)) {
                return [
                    'status' => 'success',
                    'message' => 'mail changed in file storage/app/public/email.txt'
                ];
            }
            return [
                'status' => 'failed',
                'message' => 'Could not change email. Please try later'
            ];
        }
        return [
            'status' => 'error',
            'message' => 'You can not change this email. Please write right old email'
        ];

    }

    /**
     * @param Request $request
     * @return array|string[]
     */
    public function sendCode(Request $request)
    {
        $validator = $this->validateEmail($request);
        if ($validator->fails()) {
            return [
                'status' => 'error',
                'message' => $validator->errors()
            ];
        }
        $oldCode = $this->authService->getActivationCodeByEmail($request->get('email'));
        if ($oldCode) {
            $this->authService->deleteActivationCode($oldCode);
        }
        $code = $this->authService->generateActivationCode();

        $this->authService->createActivationCode($code, $request->get('email'));

        $activationCode = $this->authService->getActivationCode($code);
        $user = $this->authService->getUserByEmail($activationCode->email);
        if ($user) {
            $this->authService->deactivateUser($user);
        }

        $this->sendEmail($code);

        return [
            'status' => 'success',
            'message' => 'your code successfully sent. Please check your email',
            'code' => $code
        ];
    }

    /**
     * @param $request
     * @return array|bool
     */
    private function validateEmail($request)
    {
        return Validator::make($request->all(), [
            'email' => 'email|required'
        ]);
    }

    /**
     * @param string $code
     * @return array|string[]
     */
    private function sendEmail(string $code) : array
    {
        try {
            Notification::route('mail', env('MAIL_FROM_ADDRESS'))
                ->notify(new SendLoginLink($code));
            return [
                'status' => 'success',
                'message' => 'mail sent successfully. Please check your email after few seconds'
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Can not send email. Please try later'
            ];
        }
    }

    /**
     * @return array|string[]
     */
    public function getUser() : array
    {
        $user = auth()->user();
        if ($user) {
            return [
                'status' => 'success',
                'user' => $user,
            ];
        }
        return [
            'status' => 'error',
            'message' => 'Can not find user',
        ];
    }
}
