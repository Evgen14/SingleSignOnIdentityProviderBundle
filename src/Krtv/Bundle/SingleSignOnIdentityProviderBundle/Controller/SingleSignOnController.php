<?php

namespace Krtv\Bundle\SingleSignOnIdentityProviderBundle\Controller;

use Krtv\Bundle\SingleSignOnIdentityProviderBundle\Manager\ServiceManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Class SingleSignOnController
 * @package Krtv\Bundle\SingleSignOnIdentityProviderBundle\Controller
 */
class SingleSignOnController extends Controller
{
    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws \Exception
     */
    public function ssoLoginAction(Request $request)
    {
        $targetPathParameter = $this->container->getParameter('krtv_single_sign_on_identity_provider.target_path_parameter');

        if ($request->get($targetPathParameter) == '') {
            throw new BadRequestHttpException('Target path not specified');
        }

        $uriSigner = $this->get('krtv_single_sign_on_identity_provider.uri_signer');
        if (false === $uriSigner->check($request->getSchemeAndHttpHost().$request->getRequestUri())) {
            throw new BadRequestHttpException('Malformed uri');
        }

        if (false === $this->get('security.context')->isGranted('ROLE_USER') && $request->get('_failure_path')) {
            return $this->get('security.http_utils')->createRedirectResponse($request, $request->get('_failure_path'));
        } elseif (false === $this->get('security.context')->isGranted('ROLE_USER')) {
            throw new AccessDeniedException();
        }

        $otpParameter = $this->container->getParameter('krtv_single_sign_on_identity_provider.otp_parameter');
        $otpOrmManager = $this->get('krtv_single_sign_on_identity_provider.security.authentication.otp_manager.orm');
        $otpEncoder = $this->get('krtv_single_sign_on_identity_provider.security.authentication.encoder');

        $user = $this->get('security.context')->getToken()->getUser();

        $value = $otpEncoder->generateOneTimePasswordValue($user->getUsername(), microtime(true) + 300);
        $otp = $otpOrmManager->create($value);

        $redirectUri = $request->get($targetPathParameter);
        $redirectUri .= sprintf('&%s=%s', $otpParameter, rawurlencode($otp));
        $redirectUri = $uriSigner->sign($redirectUri);

        return $this->get('security.http_utils')->createRedirectResponse($request, $uriSigner->sign($redirectUri));
    }
}
