<?php
declare(strict_types=1);

namespace App\tests\client;

use App\client\PostmanClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PostmanClientTest extends TestCase
{
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();
    }

    private function createClientWithMockedResponses(array $responses): PostmanClient
    {
        $handlerStack = HandlerStack::create($this->mockHandler);
        $guzzleClient = new Client(['handler' => $handlerStack]);

        $client = new PostmanClient($guzzleClient);

        foreach ($responses as $response) {
            $this->mockHandler->append($response);
        }

        return $client;
    }

    public static function getRequestProvider(): array
    {
        return [
            'success response' => [
                '/collections',
                new Response(200, [], json_encode(['collections' => [['id' => '1', 'name' => 'Test Collection']]])),
                ['collections' => [['id' => '1', 'name' => 'Test Collection']]]
            ],
            'empty response' => [
                '/collections',
                new Response(200, [], ''),
                []
            ]
        ];
    }

    #[DataProvider('getRequestProvider')]
    public function testGetRequest(string $endpoint, Response $response, array $expected): void
    {
        $client = $this->createClientWithMockedResponses([$response]);
        $result = $client->get($endpoint);
        $this->assertEquals($expected, $result);
    }

    public static function postRequestProvider(): array
    {
        return [
            'success response' => [
                '/collections',
                ['collection' => ['name' => 'New Collection']],
                new Response(200, [], json_encode(['collection' => ['id' => '2', 'name' => 'New Collection']])),
                ['collection' => ['id' => '2', 'name' => 'New Collection']]
            ],
            'empty payload' => [
                '/collections',
                [],
                new Response(200, [], json_encode(['status' => 'ok'])),
                ['status' => 'ok']
            ]
        ];
    }

    #[DataProvider('postRequestProvider')]
    public function testPostRequest(string $endpoint, array $payload, Response $response, array $expected): void
    {
        $client = $this->createClientWithMockedResponses([$response]);
        $result = $client->post($endpoint, $payload);
        $this->assertEquals($expected, $result);
    }

    public function testApiError(): void
    {
        $errorResponse = new Response(404, [], 'Not Found');
        $exception = new RequestException('Error Communicating with Server', new Request('GET', 'test'), $errorResponse);

        $client = $this->createClientWithMockedResponses([$exception]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Erreur API Postman (404): Error Communicating with Server');
        $client->get('/error');
    }
}
