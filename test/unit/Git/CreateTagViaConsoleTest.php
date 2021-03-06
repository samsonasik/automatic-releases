<?php

declare(strict_types=1);

namespace Laminas\AutomaticReleases\Test\Unit\Git;

use Laminas\AutomaticReleases\Git\CreateTagViaConsole;
use Laminas\AutomaticReleases\Git\Value\BranchName;
use Laminas\AutomaticReleases\Gpg\ImportGpgKeyFromStringViaTemporaryFile;
use Laminas\AutomaticReleases\Gpg\SecretKeyId;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;
use Symfony\Component\Process\Process;

use function file_get_contents;
use function mkdir;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/** @covers \Laminas\AutomaticReleases\Git\CreateTagViaConsole */
final class CreateTagViaConsoleTest extends TestCase
{
    private string $repository;
    private SecretKeyId $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->key = (new ImportGpgKeyFromStringViaTemporaryFile())
            ->__invoke(file_get_contents(__DIR__ . '/../../asset/dummy-gpg-key.asc'));

        $this->repository = tempnam(sys_get_temp_dir(), 'CreateTagViaConsoleRepository');

        unlink($this->repository);
        mkdir($this->repository);

        (new Process(['git', 'init'], $this->repository))
            ->mustRun();
        (new Process(['git', 'config', 'user.email', 'me@example.com'], $this->repository))
            ->mustRun();
        (new Process(['git', 'config', 'user.name', 'Just Me'], $this->repository))
            ->mustRun();
        (new Process(['git', 'checkout', '-b', 'tag-branch'], $this->repository))
            ->mustRun();
        (new Process(['git', 'commit', '--allow-empty', '-m', 'a commit'], $this->repository))
            ->mustRun();
        (new Process(['git', 'checkout', '-b', 'ignored-branch'], $this->repository))
            ->mustRun();
        (new Process(['git', 'commit', '--allow-empty', '-m', 'another commit'], $this->repository))
            ->mustRun();
    }

    public function testCreatesSignedTag(): void
    {
        $sourceUri = $this->createMock(UriInterface::class);

        $sourceUri->method('__toString')
            ->willReturn($this->repository);

        (new CreateTagViaConsole())
            ->__invoke(
                $this->repository,
                BranchName::fromName('tag-branch'),
                'name-of-the-tag',
                'changelog text for the tag',
                $this->key
            );

        (new Process(['git', 'tag', '-v', 'name-of-the-tag'], $this->repository))
            ->mustRun();

        $fetchedTag = (new Process(['git', 'show', 'name-of-the-tag'], $this->repository))
            ->mustRun()
            ->getOutput();

        self::assertStringContainsString('tag name-of-the-tag', $fetchedTag);
        self::assertStringContainsString('changelog text for the tag', $fetchedTag);
        self::assertStringContainsString('a commit', $fetchedTag);
        self::assertStringContainsString('-----BEGIN PGP SIGNATURE-----', $fetchedTag);
    }
}
