<?php

namespace App\Tests\Translation;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\Process;

final class TranslationFallbackSubprocessTest extends KernelTestCase
{
    public function testLocalizedTemplatePreferredAndFallbackWorks(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $script = sys_get_temp_dir() . '/translation_fallback_' . uniqid() . '.php';

        $code = <<<PHP
<?php
require '{$projectRoot}/vendor/autoload.php';

use App\Kernel;
use App\Entity\Employee;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

// 1) boot kernel
\$kernel = new Kernel('test', true);
\$kernel->boot();
\$container = \$kernel->getContainer();

// 2) doctrine EM (public service)
\$em = \$container->get('doctrine')->getManager();

// 3) create ROLE_ACCOUNTING user (fill required fields)
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

// 4) create localized template templates/locale/ZZ/accounting/index.html.twig with marker
\$projectDir = \$kernel->getProjectDir();
\$marker = '<<<LOCALE_ZZ_MARKER>>>';

\$langDir = \$projectDir . '/templates/locale/ZZ';
\$tplDir  = \$langDir . '/accounting';
\$tplPath = \$tplDir . '/index.html.twig';

@mkdir(\$tplDir, 0777, true);
file_put_contents(\$tplPath, "<html><body>{\$marker}</body></html>");

// 5) Browser (internal HTTP, no web server needed)
\$client = new KernelBrowser(\$kernel);
\$client->loginUser(\$user);

// --- request with existing locale template ---
\$client->request('GET', '/bms/accounting?_locale=ZZ');
\$resp1 = \$client->getResponse();

if (\$resp1->getStatusCode() !== 200) {
    fwrite(STDERR, "Expected 200 for ZZ, got " . \$resp1->getStatusCode() . "\\n");
    exit(10);
}
\$body1 = \$resp1->getContent() ?? '';
if (strpos(\$body1, \$marker) === false) {
    fwrite(STDERR, "Marker not found -> localized template not used\\n");
    exit(11);
}

// --- request with missing locale template (fallback expected) ---
\$client->request('GET', '/bms/accounting?_locale=YY');
\$resp2 = \$client->getResponse();

if (\$resp2->getStatusCode() !== 200) {
    fwrite(STDERR, "Expected 200 for YY fallback, got " . \$resp2->getStatusCode() . "\\n");
    exit(12);
}
\$body2 = \$resp2->getContent() ?? '';
if (strpos(\$body2, \$marker) !== false) {
    fwrite(STDERR, "Marker found in YY -> fallback did not happen\\n");
    exit(13);
}

// cleanup template
@unlink(\$tplPath);
// remove empty dirs best-effort
@rmdir(\$tplDir);
@rmdir(\$langDir);

echo "OK\\n";
PHP;

        file_put_contents($script, $code);

        $process = new Process(['php', '-d', 'xdebug.mode=off', $script], $projectRoot);
        $process->setTimeout(15);
        $process->run();

        @unlink($script);

        $this->assertTrue(
            $process->isSuccessful(),
            "Translation fallback test failed.\nSTDOUT:\n".$process->getOutput()."\nSTDERR:\n".$process->getErrorOutput()
        );

        $this->assertStringContainsString('OK', $process->getOutput());
    }
}