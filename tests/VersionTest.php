<?php

namespace Scorimmo\Tests;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Scorimmo\Client\ScorimmoClient;
use Scorimmo\OAuth\ScorimmoOAuth;

class VersionTest extends TestCase
{
    public function testVersionMatchesLatestChangelogEntry(): void
    {
        preg_match('/^## \[([^\]]+)\]/m', (string) file_get_contents(__DIR__ . '/../CHANGELOG.md'), $matches);

        $this->assertSame(ScorimmoClient::VERSION, $matches[1]);
    }

    private function userAgentOf(object $owner): string
    {
        /** @var Client $http */
        $http = (new \ReflectionProperty($owner, 'http'))->getValue($owner);

        return $http->getConfig('headers')['User-Agent'];
    }

    public function testDefaultClientsIdentifyTheSdk(): void
    {
        $expected = 'scorimmo-php/' . ScorimmoClient::VERSION;

        $this->assertSame($expected, $this->userAgentOf(new ScorimmoClient(refreshToken: 'token')));
        $this->assertSame($expected, $this->userAgentOf(new ScorimmoOAuth()));
    }
}
