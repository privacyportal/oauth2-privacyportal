<?php

namespace League\OAuth2\Client\Test\Provider;

use League\OAuth2\Client\Provider\PrivacyPortalResourceOwner;
use PHPUnit\Framework\TestCase;

class PrivacyPortalResourceOwnerTest extends TestCase
{
    public $id;
    public $name;
    public $user;

    protected function setUp(): void
    {
        $this->id = uniqid();
        $this->name = uniqid();
        $this->user = new PrivacyPortalResourceOwner([
            'sub' => $this->id,
            'name' => $this->name,
        ]);
    }

    public function testGettersReturnNullWhenNoKeyExists(): void
    {
        self::assertEquals($this->id, $this->user->getId());
        self::assertEquals($this->name, $this->user->getName());
        self::assertNull($this->user->getEmail());
    }

    public function testCanGetAllDataBackAsAnArray(): void
    {
        $data = $this->user->toArray();

        $expectedData = [
          'sub' => $this->id,
          'name' => $this->name,
        ];

        self::assertEquals($expectedData, $data);
    }
}
