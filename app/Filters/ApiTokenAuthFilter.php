<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Authenticators\AccessTokens;

class ApiTokenAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest) {
            return null;
        }

        /** @var AccessTokens $authenticator */
        $authenticator = auth('tokens')->getAuthenticator();

        $result = $authenticator->attempt([
            'token' => $request->getHeaderLine(setting('AuthToken.authenticatorHeader')['tokens'] ?? 'Authorization'),
        ]);

        if (! $result->isOK()) {
            return service('response')
                ->setStatusCode(Response::HTTP_UNAUTHORIZED)
                ->setJSON([
                    'success' => false,
                    'message' => 'Unauthorized',
                    'errors'  => [
                        'token' => ['Invalid or missing access token.'],
                    ],
                ]);
        }

        if (setting('Auth.recordActiveDate')) {
            $authenticator->recordActiveDate();
        }

        $user = $authenticator->getUser();
        if ($user !== null && ! $user->isActivated()) {
            $authenticator->logout();

            return service('response')
                ->setStatusCode(Response::HTTP_FORBIDDEN)
                ->setJSON([
                    'success' => false,
                    'message' => 'Forbidden',
                    'errors'  => [
                        'account' => ['Your account is not active.'],
                    ],
                ]);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }
}
