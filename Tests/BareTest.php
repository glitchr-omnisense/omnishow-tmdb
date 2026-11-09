<?php

namespace Omnishow\Tmdb\Tests;

use Omnishow\Testing\BareTestCase;
use Omnishow\Tmdb\TmdbProvider;

/** omnishow/tmdb's classes load with omnisense/omnishow, php-tmdb/api and the PSR interfaces alone. */
final class BareTest extends BareTestCase
{
    protected static function entryClass(): string
    {
        return TmdbProvider::class;
    }

    protected static function requiredPackages(): array
    {
        return ['symfony/http-client-contracts', 'php-tmdb/api', 'psr/event-dispatcher', 'psr/http-client', 'psr/http-message', 'psr/http-factory'];
    }
}
