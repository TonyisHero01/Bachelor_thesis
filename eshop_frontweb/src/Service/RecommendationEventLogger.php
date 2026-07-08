<?php

namespace App\Service;

use App\Entity\Customer;
use App\Entity\RecommendationEventLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\SecurityBundle\Security;

class RecommendationEventLogger
{
    private const IMPRESSION_DEDUPLICATION_SECONDS = 600;

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

        $persistedCount = 0;
        $seenInThisBatch = [];

        foreach ($recommendations as $index => $item) {
            $recommendedSku = null;
            $score = null;

            if (is_array($item)) {
                $recommendedSku = $item['sku'] ?? $item['product_sku'] ?? null;
                $score = isset($item['score']) ? (float) $item['score'] : null;
            } elseif (is_object($item) && method_exists($item, 'getSku')) {
                $recommendedSku = $item->getSku();
            }

            $recommendedSku = trim((string) $recommendedSku);

            if ($recommendedSku === '') {
                continue;
            }

            $sourceSkuKey = trim((string) ($sourceSku ?? ''));

            $batchKey = implode('|', [
                $sessionId,
                $pageType,
                $sourceSkuKey,
                $recommendedSku,
                $algorithm,
            ]);

            if (isset($seenInThisBatch[$batchKey])) {
                continue;
            }

            $seenInThisBatch[$batchKey] = true;

            if ($this->hasRecentImpression(
                $sessionId,
                $pageType,
                $sourceSku,
                $recommendedSku,
                $algorithm
            )) {
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
            $persistedCount++;
        }

        if ($persistedCount > 0) {
            $this->entityManager->flush();
        }
    }

    private function hasRecentImpression(
        string $sessionId,
        string $pageType,
        ?string $sourceSku,
        string $recommendedSku,
        string $algorithm
    ): bool {
        $since = new \DateTimeImmutable(
            sprintf('-%d seconds', self::IMPRESSION_DEDUPLICATION_SECONDS)
        );

        $sourceSku = trim((string) ($sourceSku ?? ''));

        $qb = $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(l.id)')
            ->from(RecommendationEventLog::class, 'l')
            ->where('l.sessionId = :sessionId')
            ->andWhere('l.pageType = :pageType')
            ->andWhere('l.recommendedSku = :recommendedSku')
            ->andWhere('l.algorithm = :algorithm')
            ->andWhere('l.eventType = :eventType')
            ->andWhere('l.createdAt >= :since')
            ->setParameter('sessionId', $sessionId)
            ->setParameter('pageType', $pageType)
            ->setParameter('recommendedSku', $recommendedSku)
            ->setParameter('algorithm', $algorithm)
            ->setParameter('eventType', 'impression')
            ->setParameter('since', $since);

        if ($sourceSku === '') {
            $qb
                ->andWhere('(l.sourceSku IS NULL OR l.sourceSku = :emptySourceSku)')
                ->setParameter('emptySourceSku', '');
        } else {
            $qb
                ->andWhere('l.sourceSku = :sourceSku')
                ->setParameter('sourceSku', $sourceSku);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}