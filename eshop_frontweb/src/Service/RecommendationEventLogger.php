<?php

namespace App\Service;

use App\Entity\Customer;
use App\Entity\RecommendationEventLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\SecurityBundle\Security;

class RecommendationEventLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack $requestStack,
        private Security $security
    ) {
    }

    public function log(
        string $pageType,
        ?string $sourceSku,
        string $recommendedSku,
        string $algorithm,
        int $rankPosition,
        ?float $score,
        string $eventType
    ): void {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return;
        }

        $session = $request->getSession();
        $sessionId = $session->getId();

        $user = $this->security->getUser();
        $customer = $user instanceof Customer ? $user : null;

        $log = new RecommendationEventLog();
        $log->setSessionId($sessionId);
        $log->setCustomer($customer);
        $log->setPageType($pageType);
        $log->setSourceSku($sourceSku);
        $log->setRecommendedSku($recommendedSku);
        $log->setAlgorithm($algorithm);
        $log->setRankPosition($rankPosition);
        $log->setScore($score);
        $log->setEventType($eventType);

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    public function logManyImpressions(
        string $pageType,
        ?string $sourceSku,
        array $recommendations,
        string $algorithm
    ): void {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request) {
            return;
        }

        $session = $request->getSession();
        $sessionId = $session->getId();

        $user = $this->security->getUser();
        $customer = $user instanceof Customer ? $user : null;

        foreach ($recommendations as $index => $item) {
            $recommendedSku = null;
            $score = null;

            if (is_array($item)) {
                $recommendedSku = $item['sku'] ?? null;
                $score = isset($item['score']) ? (float) $item['score'] : null;
            } elseif (is_object($item) && method_exists($item, 'getSku')) {
                $recommendedSku = $item->getSku();
            }

            if (!$recommendedSku) {
                continue;
            }

            $log = new RecommendationEventLog();
            $log->setSessionId($sessionId);
            $log->setCustomer($customer);
            $log->setPageType($pageType);
            $log->setSourceSku($sourceSku);
            $log->setRecommendedSku($recommendedSku);
            $log->setAlgorithm($algorithm);
            $log->setRankPosition($index + 1);
            $log->setScore($score);
            $log->setEventType('impression');

            $this->entityManager->persist($log);
        }

        $this->entityManager->flush();
    }
}