<?php

namespace App\Tests\Translation;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

use App\Entity\Employee;

final class TranslationFallbackHttpTest extends WebTestCase
{
    private ?string $createdTemplatePath = null;
    private ?string $createdLangDir = null;

    protected function tearDown(): void
    {
        if ($this->createdTemplatePath && file_exists($this->createdTemplatePath)) {
            @unlink($this->createdTemplatePath);
        }

        if ($this->createdLangDir && is_dir($this->createdLangDir)) {
            $this->removeDirIfEmpty($this->createdLangDir . '/accounting');
            $this->removeDirIfEmpty($this->createdLangDir);
        }

        if (static::$kernel !== null) {
            try {
                /** @var EntityManagerInterface $em */
                $em = static::getContainer()->get(EntityManagerInterface::class);
                $em->clear();
                $em->getConnection()->close();
            } catch (\Throwable $e) {
                // ignore
            }
        }

        static::ensureKernelShutdown();

        while (restore_exception_handler()) {}
        while (restore_error_handler()) {}

        parent::tearDown();
    }

    public function testLocalizedTemplateIsPreferredWhenExists(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->createAccountingEmployee($em);
        $em->flush();
        $client->loginUser($user);

        $marker = '<<<LOCALE_ZZ_MARKER>>>';
        $this->createLocalizedTemplateForAccountingIndex('ZZ', $marker);

        $client->request('GET', '/bms/accounting?_locale=ZZ');

        $this->assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $this->assertStringContainsString($marker, $content, 'Should render localized template when it exists.');
    }

    public function testFallbackToDefaultTemplateWhenLocalizedMissing(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->createAccountingEmployee($em);
        $em->flush();
        $client->loginUser($user);

        $marker = '<<<LOCALE_ZZ_MARKER>>>';
        $this->createLocalizedTemplateForAccountingIndex('ZZ', $marker);

        $client->request('GET', '/bms/accounting?_locale=YY');

        $this->assertResponseStatusCodeSame(200);
        $content = $client->getResponse()->getContent();
        $this->assertNotFalse($content);

        $this->assertStringNotContainsString($marker, $content, 'Should fallback to default template when localized one is missing.');
    }

    private function createLocalizedTemplateForAccountingIndex(string $lang, string $marker): void
    {
        $kernel = static::getKernel();
        $projectDir = $kernel->getProjectDir();

        $langDir = $projectDir . '/templates/locale/' . $lang;
        $accountingDir = $langDir . '/accounting';
        $filePath = $accountingDir . '/index.html.twig';

        if (!is_dir($accountingDir)) {
            @mkdir($accountingDir, 0777, true);
        }

        $tpl = <<<TWIG
{# Auto-created by TranslationFallbackHttpTest #}
<!doctype html>
<html>
  <body>
    <div>{$marker}</div>
  </body>
</html>
TWIG;

        file_put_contents($filePath, $tpl);

        $this->createdTemplatePath = $filePath;
        $this->createdLangDir = $langDir;
    }

    private function createAccountingEmployee(EntityManagerInterface $em): Employee
    {
        $user = new Employee();

        if (method_exists($user, 'setEmail')) {
            $user->setEmail('accounting_test_' . uniqid() . '@example.com');
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

        $em->persist($user);
        return $user;
    }

    private function removeDirIfEmpty(string $dir): void
    {
        if (!is_dir($dir)) return;

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        if (count($files) === 0) {
            @rmdir($dir);
        }
    }
}