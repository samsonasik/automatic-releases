<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Gpg;

use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

use function Safe\file_put_contents;
use function Safe\preg_match;
use function Safe\tempnam;
use function sys_get_temp_dir;
use function unlink;

final class ImportGpgKeyFromStringViaTemporaryFile implements ImportGpgKeyFromString
{
    public function __invoke(string $keyContents): SecretKeyId
    {
        $keyFileName = tempnam(sys_get_temp_dir(), 'imported-key');

        file_put_contents($keyFileName, $keyContents);

        $output = (new Process(['gpg', '--import', $keyFileName]))
            ->mustRun()
            ->getErrorOutput();

        Assert::regex($output, '/key\\s+([A-F0-9]+):\\s+secret\\s+key\\s+imported/im');

        preg_match('/key\\s+([A-F0-9]+):\\s+secret\\s+key\\s+imported/im', $output, $matches);

        unlink($keyFileName);

        Assert::isList($matches);
        Assert::allString($matches);

        return SecretKeyId::fromBase16String($matches[1]);
    }
}
