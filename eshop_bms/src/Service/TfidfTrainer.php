<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class TfidfTrainer
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ParameterBagInterface $params,
    ) {
    }

    /**
     * Runs the TF-IDF training Python script and logs its output.
     */
    public function retrain(): void
    {
        $projectRoot = (string) $this->params->get('kernel.project_dir');

        $pythonPath = $projectRoot . '/python_scripts/venv/bin/python';
        $scriptPath = $projectRoot . '/python_scripts/tf-idf.py';

        $command = sprintf(
            '%s %s __TRAIN__ 2>&1',
            escapeshellcmd($pythonPath),
            escapeshellarg($scriptPath)
        );

        $this->logger->info('Running TF-IDF training command', ['cmd' => $command]);

        $output = shell_exec($command);

        if ($output === null) {
            $this->logger->error('TF-IDF training failed: no output');
            return;
        }

        $this->logger->info("TF-IDF training output:\n" . $output);
    }
}