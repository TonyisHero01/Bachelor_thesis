<?php

namespace App\Tests\Translation;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\Process;

final class TranslationModuleTest extends KernelTestCase
{
    public function testLocalizedTemplateIsPreferredWhenExists(): void
    {
        $this->runSubprocessTranslationCheck('PREFERRED');
    }

    public function testFallbackToDefaultTemplateWhenLocalizedMissing(): void
    {
        $this->runSubprocessTranslationCheck('FALLBACK');
    }

    public function testLocaleSwitchDoesNotLeakTemplateCache(): void
    {
        $this->runSubprocessTranslationCheck('SWITCH');
    }

    private function runSubprocessTranslationCheck(string $mode): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $script = sys_get_temp_dir() . '/translation_module_' . $mode . '_' . uniqid() . '.php';

        $code = <<<PHP
<?php
require '{$projectRoot}/vendor/autoload.php';

use App\\Kernel;
use App\\Entity\\Employee;
use Symfony\\Bundle\\FrameworkBundle\\KernelBrowser;

// ============ BOOT ============
\$kernel = new Kernel('test', true);
\$kernel->boot();
\$container = \$kernel->getContainer();
\$em = \$container->get('doctrine')->getManager();

// ============ USER ============
\$user = new Employee();
if (method_exists(\$user, 'setEmail')) \$user->setEmail('accounting_test_' . uniqid() . '@example.com');
if (method_exists(\$user, 'setRoles')) \$user->setRoles(['ROLE_ACCOUNTING']);
if (method_exists(\$user, 'setPassword')) \$user->setPassword('dummy');
if (method_exists(\$user, 'setSurname')) \$user->setSurname('Accounting');
if (method_exists(\$user, 'setName')) \$user->setName('Test');
if (method_exists(\$user, 'setFirstName')) \$user->setFirstName('Test');
if (method_exists(\$user, 'setLastName')) \$user->setLastName('Accounting');
if (method_exists(\$user, 'setPhone')) \$user->setPhone('000000000');

\$em->persist(\$user);
\$em->flush();

// ============ TEMPLATE (ZZ) ============
\$projectDir = \$kernel->getProjectDir();
\$marker = '<<<LOCALE_ZZ_MARKER>>>';

\$langDir = \$projectDir . '/templates/locale/ZZ';
\$tplDir  = \$langDir . '/accounting';
\$tplPath = \$tplDir . '/index.html.twig';

@mkdir(\$tplDir, 0777, true);
file_put_contents(\$tplPath, "<html><body>{\$marker}</body></html>");

// ============ CLIENT ============
\$client = new KernelBrowser(\$kernel);
\$client->loginUser(\$user);

// ============ MODES ============
\$mode = '{$mode}';

if (\$mode === 'PREFERRED') {
    \$client->request('GET', '/bms/accounting?_locale=ZZ');
    \$resp = \$client->getResponse();
    if (\$resp->getStatusCode() !== 200) { fwrite(STDERR, "Expected 200, got {\$resp->getStatusCode()}\\n"); exit(10); }
    \$body = \$resp->getContent() ?? '';
    if (strpos(\$body, \$marker) === false) { fwrite(STDERR, "Marker not found -> localized template not used\\n"); exit(11); }
    echo "OK\\n";
}
elseif (\$mode === 'FALLBACK') {
    // request locale that doesn't exist, must not show ZZ marker
    \$client->request('GET', '/bms/accounting?_locale=YY');
    \$resp = \$client->getResponse();
    if (\$resp->getStatusCode() !== 200) { fwrite(STDERR, "Expected 200, got {\$resp->getStatusCode()}\\n"); exit(12); }
    \$body = \$resp->getContent() ?? '';
    if (strpos(\$body, \$marker) !== false) { fwrite(STDERR, "Marker found -> fallback failed\\n"); exit(13); }
    echo "OK\\n";
}
elseif (\$mode === 'SWITCH') {
    // 1) ZZ must contain marker
    \$client->request('GET', '/bms/accounting?_locale=ZZ');
    \$body1 = \$client->getResponse()->getContent() ?? '';
    if (strpos(\$body1, \$marker) === false) { fwrite(STDERR, "ZZ missing marker\\n"); exit(20); }

    // 2) en must NOT contain marker
    \$client->request('GET', '/bms/accounting?_locale=en');
    \$body2 = \$client->getResponse()->getContent() ?? '';
    if (strpos(\$body2, \$marker) !== false) { fwrite(STDERR, "en still has marker (leak)\\n"); exit(21); }

    // 3) YY must NOT contain marker
    \$client->request('GET', '/bms/accounting?_locale=YY');
    \$body3 = \$client->getResponse()->getContent() ?? '';
    if (strpos(\$body3, \$marker) !== false) { fwrite(STDERR, "YY still has marker (leak)\\n"); exit(22); }

    echo "OK\\n";
}
else {
    fwrite(STDERR, "Unknown mode\\n");
    exit(99);
}

// ============ CLEANUP ============
@unlink(\$tplPath);
@rmdir(\$tplDir);
@rmdir(\$langDir);
PHP;

        file_put_contents($script, $code);

        $process = new Process(['php', '-d', 'xdebug.mode=off', $script], $projectRoot);
        $process->setTimeout(15);
        $process->run();

        @unlink($script);

        $this->assertTrue(
            $process->isSuccessful(),
            "Translation module test failed (mode={$mode}).\nSTDOUT:\n".$process->getOutput()."\nSTDERR:\n".$process->getErrorOutput()
        );

        $this->assertStringContainsString('OK', $process->getOutput());
    }
}