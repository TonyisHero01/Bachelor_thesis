<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CurrencyController extends AbstractController
{
    /**
     * Sets the active currency code in session for frontend currency switching.
     */
    #[Route(path: '/set-currency', name: 'set_currency', methods: ['POST'])]
    public function setCurrency(Request $request): Response
    {
        $code = strtoupper(trim((string) $request->request->get('currency', '')));

        if ($code === '') {
            return new JsonResponse(['ok' => false, 'msg' => 'Missing currency'], 400);
        }

        $request->getSession()->set('active_currency', $code);

        return new JsonResponse(['ok' => true, 'active' => $code]);
    }
}