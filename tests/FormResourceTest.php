<?php

namespace Scorimmo\Tests;

use PHPUnit\Framework\TestCase;
use Scorimmo\Client\FormResource;
use Scorimmo\Client\ScorimmoClient;

class FormResourceTest extends TestCase
{
    private const BASE = [
        'store_id'   => 1,
        'libelle_id' => 2,
        'to_email'   => 'contact@agence.fr',
        'origin'     => 'Site web',
        'message'    => 'Bonjour',
    ];

    private function assertSubmittedCustomer(array $customer, array $expected): void
    {
        $client = $this->createMock(ScorimmoClient::class);
        $client->expects($this->once())
            ->method('request')
            ->with('POST', '/api/v2/form', self::BASE + ['customer' => $expected])
            ->willReturn(['id' => 1]);

        (new FormResource($client))->submit(self::BASE + ['customer' => $customer]);
    }

    public function testSubmitSendsTitle(): void
    {
        $this->assertSubmittedCustomer(['title' => 'Mme'], ['title' => 'Mme']);
    }

    public function testSubmitConvertsLegacyCivilityToTitle(): void
    {
        $this->assertSubmittedCustomer(['civility' => 'M.', 'last_name' => 'Dupont'], ['last_name' => 'Dupont', 'title' => 'M.']);
    }

    public function testSubmitKeepsTitleOverLegacyCivility(): void
    {
        $this->assertSubmittedCustomer(['civility' => 'M.', 'title' => 'Mme'], ['title' => 'Mme']);
    }
}
