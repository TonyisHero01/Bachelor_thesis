<?php
namespace App\Twig;

use App\Repository\CartRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    private CartRepository $cartRepository;
    private Security $security;

    public function __construct(CartRepository $cartRepository, Security $security)
    {
        $this->cartRepository = $cartRepository;
        $this->security = $security;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cart_count', [$this, 'getCartCount']),
        ];
    }

    public function getCartCount(): int
    {
        $user = $this->security->getUser();
        if (!$user) {
            return 0;
        }

        return (int) $this->cartRepository->getTotalQuantityByCustomer($user);
    }
}