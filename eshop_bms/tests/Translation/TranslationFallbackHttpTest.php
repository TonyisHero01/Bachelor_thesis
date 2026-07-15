<?php

declare(strict_types=1);

namespace App\Tests\Translation;

use App\Entity\Employee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TranslationFallbackHttpTest extends WebTestCase
{
    private ?string $createdTemplatePath = null;
    private ?string $createdLangDir = null;

    protected function tearDown(): void
    {
        if (
            $this->createdTemplatePath !== null
            && file_exists($this->createdTemplatePath)
        ) {
            @unlink($this->createdTemplatePath);
        }

        if (
            $this->createdLangDir !== null
            && is_dir($this->createdLangDir)
        ) {
            $this->removeDirIfEmpty(
                $this->createdLangDir . '/accounting'
            );

            $this->removeDirIfEmpty($this->createdLangDir);
        }

        if (static::$kernel !== null) {
            try {
                /** @var EntityManagerInterface $entityManager */
                $entityManager = static::getContainer()->get(
                    EntityManagerInterface::class
                );

                $entityManager->clear();
                $entityManager->getConnection()->close();
            } catch (\Throwable) {
                // Ignore cleanup failures.
            }
        }

        static::ensureKernelShutdown();

        parent::tearDown();
    }

    public function testLocalizedTemplateIsPreferredWhenExists(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(
            EntityManagerInterface::class
        );

        $user = $this->createAccountingEmployee($entityManager);

        $entityManager->flush();

        $client->loginUser($user);

        $marker = '<<<LOCALE_ZZ_MARKER>>>';

        $this->createLocalizedTemplateForAccountingIndex(
            'ZZ',
            $marker
        );

        $client->request(
            'GET',
            '/bms/accounting?_locale=ZZ'
        );

        self::assertResponseStatusCodeSame(200);

        $content = $client->getResponse()->getContent();

        self::assertNotFalse($content);

        self::assertStringContainsString(
            $marker,
            $content,
            'Should render localized template when it exists.'
        );
    }

    public function testFallbackToDefaultTemplateWhenLocalizedMissing(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(
            EntityManagerInterface::class
        );

        $user = $this->createAccountingEmployee($entityManager);

        $entityManager->flush();

        $client->loginUser($user);

        $marker = '<<<LOCALE_ZZ_MARKER>>>';

        $this->createLocalizedTemplateForAccountingIndex(
            'ZZ',
            $marker
        );

        $client->request(
            'GET',
            '/bms/accounting?_locale=YY'
        );

        self::assertResponseStatusCodeSame(200);

        $content = $client->getResponse()->getContent();

        self::assertNotFalse($content);

        self::assertStringNotContainsString(
            $marker,
            $content,
            'Should fall back to the default template when the localized template is missing.'
        );
    }

    private function createLocalizedTemplateForAccountingIndex(
        string $lang,
        string $marker
    ): void {
        $projectDir = static::getContainer()->getParameter(
            'kernel.project_dir'
        );

        self::assertIsString($projectDir);

        $langDir = $projectDir . '/templates/locale/' . $lang;
        $accountingDir = $langDir . '/accounting';
        $filePath = $accountingDir . '/index.html.twig';

        if (
            !is_dir($accountingDir)
            && !mkdir($accountingDir, 0777, true)
            && !is_dir($accountingDir)
        ) {
            self::fail(
                sprintf(
                    'Unable to create directory "%s".',
                    $accountingDir
                )
            );
        }

        $template = <<<TWIG
{# Auto-created by TranslationFallbackHttpTest #}
<!doctype html>
<html>
  <body>
    <div>{$marker}</div>
  </body>
</html>
TWIG;

        $writtenBytes = file_put_contents(
            $filePath,
            $template
        );

        self::assertNotFalse(
            $writtenBytes,
            'Unable to create localized test template.'
        );

        $this->createdTemplatePath = $filePath;
        $this->createdLangDir = $langDir;
    }

    private function createAccountingEmployee(
        EntityManagerInterface $entityManager
    ): Employee {
        $user = new Employee();

        if (method_exists($user, 'setEmail')) {
            $user->setEmail(
                'accounting_test_'
                . bin2hex(random_bytes(8))
                . '@example.com'
            );
        }

        if (method_exists($user, 'setRoles')) {
            $user->setRoles(['ROLE_ACCOUNTING']);
        }

        if (method_exists($user, 'setPassword')) {
            $user->setPassword('dummy');
        }

        if (method_exists($user, 'setSurname')) {
            $user->setSurname('Accounting');
        }

        if (method_exists($user, 'setName')) {
            $user->setName('Test');
        }

        if (method_exists($user, 'setFirstName')) {
            $user->setFirstName('Test');
        }

        if (method_exists($user, 'setLastName')) {
            $user->setLastName('Accounting');
        }

        if (method_exists($user, 'setPhone')) {
            $user->setPhone('000000000');
        }

        $entityManager->persist($user);

        return $user;
    }

    private function removeDirIfEmpty(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = array_diff(
            scandir($directory) ?: [],
            ['.', '..']
        );

        if ($files === []) {
            @rmdir($directory);
        }
    }
}