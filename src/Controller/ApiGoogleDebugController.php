<?php

namespace App\Controller;

use App\Service\GoogleTokenVerifierService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ApiGoogleDebugController extends AbstractController
{
    public function __construct(
        private GoogleTokenVerifierService $tokenVerifier,
    ) {
    }

    #[Route('/api/google-debug', name: 'api_google_debug', methods: ['POST'])]
    public function debug(Request $request): Response
    {
        try {
            $data = $request->toArray();
            $token = $data['token'] ?? null;

            if (!$token) {
                return new JsonResponse([
                    'error' => 'No token provided',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Check token format
            $parts = explode('.', $token);
            $tokenInfo = [
                'token_length' => strlen($token),
                'has_3_parts' => count($parts) === 3,
                'first_50_chars' => substr($token, 0, 50),
            ];

            // Try to decode header
            if (count($parts) >= 1) {
                try {
                    $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
                    $tokenInfo['header'] = $header;
                } catch (\Exception $e) {
                    $tokenInfo['header_error'] = $e->getMessage();
                }
            }

            // Try to decode payload
            if (count($parts) >= 2) {
                try {
                    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
                    $tokenInfo['payload'] = $payload;
                    $tokenInfo['payload_keys'] = array_keys($payload);
                } catch (\Exception $e) {
                    $tokenInfo['payload_error'] = $e->getMessage();
                }
            }

            // Try to verify with Google
            try {
                $verified = $this->tokenVerifier->verifyToken($token);
                $tokenInfo['verification_result'] = $verified ? 'SUCCESS' : 'FAILED';
                if ($verified) {
                    $tokenInfo['verified_data'] = $verified;
                }
            } catch (\Exception $e) {
                $tokenInfo['verification_error'] = $e->getMessage();
            }

            return new JsonResponse($tokenInfo, Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
