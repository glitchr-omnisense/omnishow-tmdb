<?php

namespace Omnishow\Tmdb\Tests;

use Omnisense\Contracts\Policy\InvalidPolicyException;
use Omnisense\Contracts\Policy\Permission;
use Omnishow\Exception\OmnishowException;
use Omnishow\Exception\UsageLimitReachedException;
use Omnishow\Model\WorkRef;
use Omnishow\Registry;
use Omnishow\Tmdb\TmdbFactory;
use Omnishow\Tmdb\TmdbProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * TmdbProvider's usage policy (policy.json) through omnishow's registry:
 * as declared, a site's limit counted with the call over it never made,
 * a 429 and its Retry-After holding every next call, a looser setting
 * refused. No network: Symfony's MockHttpClient, as TmdbProviderTest.
 */
final class PolicyTest extends TestCase
{
    private const OPTIONS = ['token' => 'not-a-real-tmdb-token'];

    public function testThePolicyIsTheOneDeclared(): void
    {
        $policy = (new Registry([new TmdbFactory(self::http())], ['films' => ['factory' => 'tmdb', 'options' => self::OPTIONS]]))->policy('films');

        self::assertSame('tmdb', $policy->provider);
        self::assertSame('2026-11-10', $policy->provenance->verifiedAt);
        self::assertFileExists(TmdbProvider::POLICY);
        self::assertSame(['40/second/application/*'], array_map(static fn ($l) => $l->id(), $policy->rateLimits ?? []));
        self::assertSame(Permission::Contract, $policy->rights->commercial);
        self::assertSame(15552000, $policy->rights->caching->maxTtl, 'six months at most');
        self::assertStringStartsWith('This product uses TMDB', (string) $policy->rights->attribution->text);
    }

    public function testASitesLimitIsCountedAndTheCallOverItIsNeverMade(): void
    {
        $http = self::http();
        $registry = new Registry([new TmdbFactory($http)], ['films' => ['factory' => 'tmdb', 'options' => self::OPTIONS, 'policy' => ['rateLimits' => [['max' => 1, 'window' => 'day', 'operations' => ['work']]]]]]);

        try {
            $registry->lookup('films')->work(WorkRef::tmdb(603));
        } catch (UsageLimitReachedException $e) {
            throw $e;
        } catch (OmnishowException) {
            // Nothing recorded: the provider's own answer to that, counted all the same.
        }
        $made = $http->getRequestsCount();
        self::assertGreaterThan(0, $made);
        try {
            $registry->lookup('films')->work(WorkRef::tmdb(603));
            self::fail('The call over the site\'s limit was made.');
        } catch (UsageLimitReachedException $e) {
            self::assertInstanceOf(OmnishowException::class, $e, 'the family\'s catch holds it');
            self::assertSame('1/day/application/work', $e->limit?->id());
        }
        self::assertSame($made, $http->getRequestsCount(), 'the refused call reached nothing');
    }

    public function testA429AndItsRetryAfterHoldEveryCallUntilTheWaitIsOver(): void
    {
        // TMDB's own answer over its limit (its status code 25), which php-tmdb/api reads into its exception.
        $http = self::http(429, '{"success":false,"status_code":25,"status_message":"Your request count (41) is over the allowed limit of (40)."}', ['retry-after: 120']);
        $registry = new Registry([new TmdbFactory($http)], ['films' => ['factory' => 'tmdb', 'options' => self::OPTIONS]]);

        try {
            $registry->lookup('films')->work(WorkRef::tmdb(603));
            self::fail('A 429 was read as an answer.');
        } catch (UsageLimitReachedException $e) {
            self::assertSame(120, $e->retryAfter);
            self::assertSame('tmdb answered 429 Too Many Requests: try again in 120 s.', $e->getMessage());
        }
        try {
            $registry->lookup('films')->work(WorkRef::tmdb(603));
            self::fail('A call went out while the provider asked to wait.');
        } catch (UsageLimitReachedException $e) {
            self::assertGreaterThanOrEqual(119, $e->retryAfter);
        }
        self::assertSame(1, $http->getRequestsCount(), 'the wait held the next request back');
    }

    public function testASiteCannotLoosenThePolicy(): void
    {
        $registry = new Registry([new TmdbFactory(self::http())], ['films' => ['factory' => 'tmdb', 'options' => self::OPTIONS, 'policy' => ['rateLimits' => [['max' => 100, 'window' => 'second']]]]]);

        $this->expectException(InvalidPolicyException::class);
        $this->expectExceptionMessage('40 a second');
        $registry->lookup('films');
    }

    /**
     * Every request answered alike: TMDB's 404 (its status code 34) by default.
     *
     * @param list<string> $headers
     */
    private static function http(int $status = 404, string $body = '{"success":false,"status_code":34,"status_message":"The resource you requested could not be found."}', array $headers = []): MockHttpClient
    {
        return new MockHttpClient(static fn (): MockResponse => new MockResponse($body, ['http_code' => $status, 'response_headers' => ['content-type: application/json', ...$headers]]));
    }
}
