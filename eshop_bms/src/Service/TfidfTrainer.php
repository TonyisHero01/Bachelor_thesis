<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class TfidfTrainer
{
    private LoggerInterface $logger;
    private ParameterBagInterface $params;

    public function __construct(LoggerInterface $logger, ParameterBagInterface $params)
    {
        $this->logger = $logger;
        $this->params = $params;
    }

    public function retrain(): void
    {
        $projectRoot = $this->params->get('kernel.project_dir');
        $pythonPath = $projectRoot . '/python_scripts/venv/bin/python';
        $scriptPath = $projectRoot . '/python_scripts/tf-idf.py';
        $command = "$pythonPath $scriptPath __TRAIN__ 2>&1";

        $this->logger->info('🧠 Running TF-IDF training command', ['cmd' => $command]);

        $output = shell_exec($command);

        if ($output === null) {
            $this->logger->error("TF-IDF training failed: no output");
        } else {
            $this->logger->info("TF-IDF training output:\n" . $output);
        }
    }
}