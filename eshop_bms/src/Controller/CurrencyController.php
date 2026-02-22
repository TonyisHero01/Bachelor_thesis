<?php

namespace App\Controller;

use App\Repository\CurrencyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CurrencyController extends AbstractController
{
    /**
     * Persists the selected currency into the session and redirects back to the previous page.
     */
    #[Route('/set-currency', name: 'set_currency', methods: ['POST'])]
    public function setCurrency(Request $request, CurrencyRepository $currencyRepository): Response
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('set_currency', $token)) {
            return $this->redirect($this->getSafeReturnUrl($request));
        }

        $currency = (string) $request->request->get('currency', '');
        if ($currency === '') {
            return $this->redirect($this->getSafeReturnUrl($request));
        }

        $supported = $currencyRepository->findOneBy(['name' => $currency]);
        if ($supported === null) {
            return $this->redirect($this->getSafeReturnUrl($request));
        }

        $request->getSession()->set('currency', $currency);

        return $this->redirect($this->getSafeReturnUrl($request));
    }

    /**
     * Returns a safe URL to redirect back to, falling back to the home route.
     */
    private function getSafeReturnUrl(Request $request): string
    {
        $fallback = $this->generateUrl('home');

        $referer = (string) $request->headers->get('referer', '');
        if ($referer === '') {
            return $fallback;
        }

        $path = \parse_url($referer, PHP_URL_PATH);
        if (!\is_string($path) || $path === '' || !\str_starts_with($path, '/')) {
            return $fallback;
        }

        $query = \parse_url($referer, PHP_URL_QUERY);
        if (\is_string($query) && $query !== '') {
            return $path . '?' . $query;
        }

        return $path;
    }
}