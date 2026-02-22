<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ApiToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:generate-api-token',
    description: 'Generate an API token for external partners',
)]
class GenerateApiTokenCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    /**
     * Configures the command arguments.
     */
    protected function configure(): void
    {
        $this
            ->addArgument('label', InputArgument::REQUIRED, 'Token label (for your reference only)')
            ->addArgument('scopes', InputArgument::IS_ARRAY, 'Scopes, e.g. categories.write or "all"');
    }

    /**
     * Executes the command and persists a new API token.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $label = (string) $input->getArgument('label');

        /** @var string[] $scopesArg */
        $scopesArg = array_map('strval', (array) $input->getArgument('scopes'));

        if ($scopesArg === []) {
            $output->writeln('<error>No scopes provided.</error>');
            $output->writeln('Example: php bin/console app:generate-api-token partner categories.write');

            return Command::FAILURE;
        }

        $scopes = $this->normalizeScopes($scopesArg);

        $publicId = 'pk_' . bin2hex(random_bytes(8));
        $secret = bin2hex(random_bytes(24));
        $tokenString = $publicId . '.' . $secret;

        $token = new ApiToken();
        $token->setPublicId($publicId);
        $token->setSecretHash(password_hash($secret, PASSWORD_DEFAULT));
        $token->setScopes($scopes);
        $token->setActive(true);

        $this->em->persist($token);
        $this->em->flush();

        $output->writeln('');
        $output->writeln('<info>✅ API token created successfully!</info>');
        $output->writeln('Label (CLI only): ' . $label);
        $output->writeln('Scopes: ' . implode(', ', $scopes));
        $output->writeln('');
        $output->writeln('<comment>--- COPY & SAVE THIS TOKEN ---</comment>');
        $output->writeln($tokenString);
        $output->writeln('<comment>--------------------------------</comment>');
        $output->writeln('');
        $output->writeln('<fg=yellow>Note:</> The secret part cannot be recovered once lost.');

        return Command::SUCCESS;
    }

    /**
     * Normalizes scope arguments and expands the "all" shortcut.
     *
     * @param string[] $scopesArg
     *
     * @return string[]
     */
    private function normalizeScopes(array $scopesArg): array
    {
        if (count($scopesArg) === 1 && strtolower($scopesArg[0]) === 'all') {
            return $this->getAllEntityScopes();
        }

        $out = [];
        foreach ($scopesArg as $scope) {
            $scope = trim((string) $scope);
            if ($scope !== '') {
                $out[] = $scope;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Returns a full list of supported entity scopes.
     *
     * @return string[]
     */
    private function getAllEntityScopes(): array
    {
        $entities = [
            'categories',
            'products',
            'orders',
            'customers',
            'employees',
            'colors',
            'sizes',
            'currencies',
            'returnrequests',
            'shopinfos',
        ];

        $scopes = [];
        foreach ($entities as $entity) {
            $scopes[] = $entity . '.read';
            $scopes[] = $entity . '.write';
        }

        return $scopes;
    }
}