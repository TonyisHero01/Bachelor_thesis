<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;

class PaymentRecorder
{
    public function __construct(private EntityManagerInterface $em) {}

    public function recordPayment(Order $order, array $data): Payment
    {
        $status        = strtoupper($data['status'] ?? 'PENDING'); // SUCCESS|FAILED|PENDING
        $provider      = $data['provider'] ?? 'mock';
        $amount        = (string)($data['amount'] ?? (string)$order->getTotalPrice());
        $currencyCode  = $data['currencyCode'] ?? 'EUR';
        $fxRate        = $data['fxRate'] ?? '1.00000000';
        $amtInCurrency = (string)($data['amountInCurrency'] ?? $amount);
        $payload       = $data['payload'] ?? [];

        $p = new Payment();
        $p->setOrder($order);
        $p->setAmount($amount);
        $p->setCurrency($currencyCode);
        $p->setCurrencyCode($currencyCode);
        $p->setFxRate($fxRate);
        $p->setAmountInCurrency($amtInCurrency);
        $p->setProvider($provider);
        $p->setStatus($status);
        $p->setPayload($payload);
        $p->touch();

        $this->em->persist($p);

        switch ($status) {
            case 'SUCCESS':
                $order->setPaymentStatus('COMPLETED');
                break;
            case 'FAILED':
                $order->setPaymentStatus('FAILED');
                break;
            default:
                $order->setPaymentStatus('PENDING');
                break;
        }

        $this->em->persist($order);
        $this->em->flush();

        return $p;
    }
}