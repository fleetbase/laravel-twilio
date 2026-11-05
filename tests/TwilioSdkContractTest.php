<?php

namespace Fleetbase\Twilio\Tests;

use Fleetbase\Twilio\Twilio;
use PHPUnit\Framework\TestCase;
use Twilio\AuthStrategy\AuthStrategy;
use Twilio\Http\Client as HttpClient;
use Twilio\Http\Response;
use Twilio\Rest\Api\V2010\Account\CallInstance;
use Twilio\Rest\Api\V2010\Account\MessageInstance;
use Twilio\Rest\Client;
use Twilio\TwiML\VoiceResponse;

/**
 * Pins what this package sends through the Twilio SDK, using the SDK's real resource classes
 * and a fake HTTP client, so an SDK major that changes a request fails here.
 */
class TwilioSdkContractTest extends TestCase
{
    private const SID = 'AC00000000000000000000000000000000';

    private function twilio(HttpClient $http): Twilio
    {
        return new class($http) extends Twilio {
            public function __construct(private HttpClient $http)
            {
                parent::__construct(TwilioSdkContractTest::sid(), 'token', '+15550000001');
            }

            public function getTwilio(): Client
            {
                return new Client(TwilioSdkContractTest::sid(), 'token', null, null, $this->http);
            }
        };
    }

    public static function sid(): string
    {
        return self::SID;
    }

    private function http(array $body): HttpClient
    {
        return new class($body) implements HttpClient {
            public array $requests = [];

            public function __construct(private array $body)
            {
            }

            public function request(
                string $method,
                string $url,
                array $params = [],
                array $data = [],
                array $headers = [],
                ?string $user = null,
                ?string $password = null,
                ?int $timeout = null,
                ?AuthStrategy $authStrategy = null
            ): Response {
                $this->requests[] = compact('method', 'url', 'data', 'user', 'password');

                return new Response(201, json_encode($this->body));
            }
        };
    }

    public function testMessageSendsBodyToAndDefaultFrom(): void
    {
        $http = $this->http(['sid' => 'SM1', 'status' => 'queued', 'to' => '+15550000002']);

        $message = $this->twilio($http)->message('+15550000002', 'Hello', ['https://example.test/a.png']);

        $this->assertInstanceOf(MessageInstance::class, $message);
        $this->assertSame('SM1', $message->sid);
        $this->assertCount(1, $http->requests);

        $request = $http->requests[0];
        $this->assertSame('POST', strtoupper($request['method']));
        $this->assertStringEndsWith('/Accounts/' . self::SID . '/Messages.json', $request['url']);
        $this->assertSame(self::SID, $request['user']);
        $this->assertSame('token', $request['password']);
        $this->assertSame('+15550000002', $request['data']['To']);
        $this->assertSame('+15550000001', $request['data']['From']);
        $this->assertSame('Hello', $request['data']['Body']);
        $this->assertSame(['https://example.test/a.png'], (array) $request['data']['MediaUrl']);
    }

    public function testMessageKeepsAnExplicitFrom(): void
    {
        $http = $this->http(['sid' => 'SM2']);

        $this->twilio($http)->message('+15550000002', 'Hello', [], ['from' => 'Fleetbase']);

        $this->assertSame('Fleetbase', $http->requests[0]['data']['From']);
        $this->assertArrayNotHasKey('MediaUrl', $http->requests[0]['data']);
    }

    public function testCallSendsAUrl(): void
    {
        $http = $this->http(['sid' => 'CA1']);

        $call = $this->twilio($http)->call('+15550000002', 'https://example.test/voice.xml');

        $this->assertInstanceOf(CallInstance::class, $call);
        $this->assertStringEndsWith('/Accounts/' . self::SID . '/Calls.json', $http->requests[0]['url']);
        $this->assertSame('+15550000002', $http->requests[0]['data']['To']);
        $this->assertSame('+15550000001', $http->requests[0]['data']['From']);
        $this->assertSame('https://example.test/voice.xml', $http->requests[0]['data']['Url']);
    }

    public function testCallRendersACallbackToTwiml(): void
    {
        $http = $this->http(['sid' => 'CA2']);

        $this->twilio($http)->call('+15550000002', function (VoiceResponse $response) {
            $response->say('Hello');
        });

        $this->assertStringContainsString('<Say>Hello</Say>', $http->requests[0]['data']['Twiml']);
        $this->assertArrayNotHasKey('Url', $http->requests[0]['data']);
    }
}
