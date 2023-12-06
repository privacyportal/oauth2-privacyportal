<?php

namespace League\OAuth2\Client\Test\Provider;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Tool\QueryBuilderTrait;
use GuzzleHttp\ClientInterface;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

use function http_build_query;
use function json_encode;
use function sprintf;
use function uniqid;

class PrivacyPortalTest extends TestCase
{
    use QueryBuilderTrait;

    protected $provider;

    protected function setUp(): void
    {
        $this->provider = new \League\OAuth2\Client\Provider\PrivacyPortal(
            [
            'clientId' => 'mock_client_id',
            'clientSecret' => 'mock_secret',
            'redirectUri' => 'none',
            ]
        );
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);
        parse_str($uri['query'], $query);

        $this->assertArrayHasKey('client_id', $query);
        $this->assertArrayHasKey('redirect_uri', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('scope', $query);
        $this->assertArrayHasKey('response_type', $query);
        $this->assertArrayHasKey('approval_prompt', $query);
        $this->assertNotNull($this->provider->getState());
    }


    public function testScopes(): void
    {
        $scopeSeparator = ',';
        $options = ['scope' => [uniqid(), uniqid()]];
        $query = ['scope' => implode($scopeSeparator, $options['scope'])];
        $url = $this->provider->getAuthorizationUrl($options);
        $encodedScope = $this->buildQueryString($query);

        $this->assertStringContainsString($encodedScope, $url);
    }

    public function testGetAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl();
        $uri = parse_url($url);

        $this->assertEquals('/oauth/authorize', $uri['path']);
    }

    public function testGetBaseAccessTokenUrl(): void
    {
        $params = [];

        $url = $this->provider->getBaseAccessTokenUrl($params);
        $uri = parse_url($url);

        $this->assertEquals('/oauth/token', $uri['path']);
    }

    public function testGetAccessToken(): void
    {
        $stream = Mockery::spy(StreamInterface::class)->makePartial();
        $stream->shouldReceive('__toString')
               ->once()
               ->andReturn('{"access_token":"mock_access_token", "token_type":"Bearer"}');
        $response = Mockery::spy(ResponseInterface::class)->makePartial();
        $response->shouldReceive('getBody')
                 ->once()
                 ->andReturn($stream);
        $response->shouldReceive('getHeader')
                 ->once()
                 ->andReturn(['content-type' => 'json']);
        $response->shouldReceive('getStatusCode')
                 ->andReturn(200);

        $client = Mockery::spy(ClientInterface::class)->makePartial();
        $client->shouldReceive('send')
               ->once()
               ->andReturn($response);
        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);

        $this->assertEquals('mock_access_token', $token->getToken());
        $this->assertNull($token->getExpires());
        $this->assertNull($token->getRefreshToken());
        $this->assertNull($token->getResourceOwnerId());
    }

    public function testUserData(): void
    {
        $userId = uniqid();
        $name = uniqid();
        $email = uniqid();

        $postStream = Mockery::spy(StreamInterface::class)->makePartial();
        $postStream->shouldReceive('__toString')
                   ->once()
                   ->andReturn(http_build_query([
                        'access_token' => 'mock_access_token',
                        'expires' => 86400,
                        'refresh_token' => 'mock_refresh_token',
                   ]));
        $postResponse = Mockery::spy(ResponseInterface::class)->makePartial();
        $postResponse->shouldReceive('getBody')
                     ->once()
                     ->andReturn($postStream);
        $postResponse->shouldReceive('getHeader')
                     ->once()
                     ->andReturn(['content-type' => 'application/x-www-form-urlencoded']);
        $postResponse->shouldReceive('getStatusCode')
                     ->andReturn(200);

        $userStream = Mockery::spy(StreamInterface::class)->makePartial();
        $userStream->shouldReceive('__toString')
                   ->once()
                   ->andReturn(json_encode([
                        "sub" => $userId,
                        "name" => $name,
                        "email" => $email
                   ]));
        $userResponse = Mockery::spy(ResponseInterface::class)->makePartial();
        $userResponse->shouldReceive('getBody')
                     ->once()
                     ->andReturn($userStream);
        $userResponse->shouldReceive('getHeader')
                     ->once()
                     ->andReturn(['content-type' => 'json']);
        $userResponse->shouldReceive('getStatusCode')
                     ->andReturn(200);

        $client = Mockery::spy(ClientInterface::class)->makePartial();
        $client->shouldReceive('send')
               ->times(2)
               ->andReturn($postResponse, $userResponse);

        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
        $user = $this->provider->getResourceOwner($token);

        $this->assertEquals($userId, $user->getId());
        $this->assertEquals($userId, $user->toArray()['sub']);
        $this->assertEquals($name, $user->getName());
        $this->assertEquals($name, $user->toArray()['name']);
        $this->assertEquals($email, $user->getEmail());
        $this->assertEquals($email, $user->toArray()['email']);
    }

    public function testExceptionThrownWhenErrorObjectReceived(): void
    {
        $status = rand(400, 600);
        $postStream = Mockery::spy(StreamInterface::class)->makePartial();
        $postStream->shouldReceive('__toString')->andReturn(
            json_encode([
                'message' => 'Server error. Please try again later.'
            ])
        );
        $postResponse = Mockery::spy(ResponseInterface::class)->makePartial();
        $postResponse->shouldReceive('getBody')->andReturn($postStream);
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn($status);

        $client = Mockery::spy(ClientInterface::class)->makePartial();
        $client->shouldReceive('send')->andReturn($postResponse);

        $this->provider->setHttpClient($client);

        $this->expectException(IdentityProviderException::class);

        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }

    public function testExceptionThrownWhenOAuthErrorReceived(): void
    {
        $status = 400;
        $postStream = Mockery::spy(StreamInterface::class)->makePartial();
        $postStream->shouldReceive('__toString')->andReturn(
            json_encode([
                "error" => "invalid_client",
                "error_description" => "App not found."
            ])
        );
        $postResponse = Mockery::spy(ResponseInterface::class)->makePartial();
        $postResponse->shouldReceive('getBody')->andReturn($postStream);
        $postResponse->shouldReceive('getHeader')->andReturn(['content-type' => 'json']);
        $postResponse->shouldReceive('getStatusCode')->andReturn($status);

        $client = Mockery::spy(ClientInterface::class)->makePartial();
        $client->shouldReceive('send')->andReturn($postResponse);
        $this->provider->setHttpClient($client);

        $this->expectException(IdentityProviderException::class);

        $this->provider->getAccessToken('authorization_code', ['code' => 'mock_authorization_code']);
    }
}
