# Changelog

## Unreleased (1.x)

### Changed
- omnishow/tmdb continues omnifilm/tmdb (merged into omnisense/omnishow on 2026-11-09): the
  namespace `Omnishow\Tmdb\`, the requirement `omnisense/omnishow`.

### Added
- TMDB's usage policy declared as data in `policy.json` (omnisense/contracts' schema, read on
  2026-11-10): about 40 requests a second, commercial use under a written agreement, nothing cached
  longer than 6 months, TMDB's credit and JustWatch's. `TmdbProvider` implements
  `DeclaresUsagePolicyInterface` and takes the provider's Backoff (its last, optional argument): no
  php-tmdb/api call goes out while TMDB asked to wait, a 429 is a `UsageLimitReachedException` with
  its `Retry-After`, every answer's RateLimit fields are read (a `ResponseEvent` listener).
  `composer.json` names the policy (`extra.omnisense.policy`) and requires `omnisense/contracts`.
- A record's videos: `append_to_response=credits,external_ids,videos`, each video whose `site` is
  YouTube or Vimeo a `VideoLink` in `Work::$videos` (its key, name, type as kind, language, official,
  published date), so that `Work::trailer()` names the trailer and omnishow/youtube or
  omnishow/vimeo gives its record. A site no `Platform` is (Dailymotion...) is left out.

### Fixed
- A refusal (`ProviderException`, TMDB's 401) keeps no argument in its trace: the closure there held
  the provider, its client and its key or token.
- An `UnreachableException`'s chain no longer holds the key or the token: its previous is a copy
  of the PSR-18 client's exception (the same class and code, `api_key=***` in its message, its own
  previous copied too), and no trace of the chain keeps the arguments where the request stood.
  Needs omnisense/omnishow's `Http\Redaction`.
- `capabilities()->attribution` is the wording TMDB's API terms ask for: "This product uses TMDB
  and the TMDB APIs but is not endorsed, certified, or otherwise approved by TMDB.", where it read
  "uses the TMDB API but is not endorsed or certified by TMDB".
- With the `api_key` option, the key no longer appears in an `UnreachableException`'s message: it
  names the method and the path (`TMDB could not be reached for GET /3/movie/603: ...`), then the
  PSR-18 client's reason without the URL. Needs omnisense/omnishow's `JsonClient::reason()`.
