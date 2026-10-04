# AGENTS.md - xExtension-FeedDigest

Guidance for agents working in this directory. Read this before changing
`extension.php`; the summary pipeline has ordering and idempotency rules that
are easy to break in ways that only show up as lost articles in production.

## What this extension is

A FreshRSS **system** extension (`metadata.json` -> `"type": "system"`) that runs
during feed maintenance. It collects unread articles, sends them to an
OpenAI-compatible chat-completions API, and writes the result back as **new
entries** in FreshRSS. The originals are optionally marked read.

It never fetches a remote feed, and it never modifies the body of a source
entry, with one exception: the "not summarized" note (see below).

## Files

| File | Role |
| --- | --- |
| `extension.php` | Everything. ~2500 lines, one class, no dependencies beyond FreshRSS + cURL + DOM. |
| `configure.phtml` | Global settings form. |
| `views/helpers/feed/update.phtml` | Per-feed settings. Wraps and re-emits the core FreshRSS view with extra fields injected. |
| `views/helpers/category/update.phtml` | Per-category settings. |
| `i18n/en/ext.php` | Translation strings. Only English exists. |
| `static/style.css` | Styles for the injected forms and the rendered digest HTML. |
| `README.md` | User-facing docs. **Keep in sync** when behavior changes. |

There is **no test suite** and no build step. See "Verifying changes".

## Entry points (hooks registered in `init()`)

| Hook | Handler | Purpose |
| --- | --- | --- |
| `freshrss_user_maintenance` | `handleUserMaintenance()` | **The main pipeline.** Runs the whole digest pass. |
| `feed_before_insert` | `handleFeedBeforeInsert()` | Persists per-feed settings for a newly created feed. |
| `feeds_list_before_actualize` | `handleFeedsListBeforeActualize()` | Removes virtual summary feeds from the fetch list. |
| `freshrss_init` | `handleFreshRSSInit()` | Saves settings on feed/category form POSTs. |
| `action_execute` | `handleActionExecute()` | Re-applies the category "hide from Main stream" rule right before core persists a feed. |

`handleUserMaintenance()` bails out early when no API key is configured. It runs
**category** summaries first, then per-feed summaries, because per-feed
processing consults the category's processed-item set to avoid double-summarizing.
`processedFeedIds` guards against the several maintenance hooks that can fire in
one cron cycle.

## Where configuration lives

Three different stores - do not mix them up:

- **Global**: `getSystemConfigurationValue()` / `setSystemConfiguration()`, saved
  by `handleConfigureAction()`. Keys: `api_endpoint`, `secret_key`, `model`,
  `dest_language`, `secondary_language`, `max_content_length`, and the managed
  `digest_category_id` (see the shared Digests category note below).
- **Per-feed**: FreshRSS **feed attributes** named `feed_digest_*`, written by
  `applyFeedSettingsFromRequest()` and read via `$feed->attributeX()`.
- **Per-category**: the **user** extension configuration under
  `category_settings[<categoryId>]`, plus an `interval_unit_version` migration
  flag. Managed by `getCategorySettings()` / `saveCategorySettingsFromRequest()`.

Category summary feeds are real FreshRSS feed rows with a fake URL
(`https://freshrss-feed-digest.invalid/category/<id>`, see
`categorySummaryFeedUrl()`) and the attribute `feed_digest_summary_category`.
`isCategorySummaryFeed()` is the canonical test for one. They are created lazily
by `ensureCategorySummaryFeed()` and deleted by `deleteOrphanCategorySummaryFeeds()`
when the category is gone.

Those feeds are **placed in a single shared category**, not in the category they
summarize, so external readers (Reeder and other Google Reader clients, which
group feeds by category) show all digests in one folder. The id of that category
is remembered in the system config as `digest_category_id`, so a user rename
sticks; `ensureDigestCategory()` resolves it (stored id -> category named
`Digests` -> create) and `reconcileDigestCategoryPlacement()` moves any stragglers
on each run. Because FreshRSS allows one category per feed, the source category
is recorded **only** in `feed_digest_summary_category`, which is what scope
checks and orphan cleanup read. `handleConfigureAction()` must carry
`digest_category_id` over when it rewrites the system config, or a renamed
category would be abandoned. Note `feed_digest_summary_category` is stored as a
string - read it with `attributeString()` and cast, not `attributeInt()`.

## The summary pipeline

`processFeed()` is the core. Its order matters:

1. Skip if this feed already ran in this maintenance cycle (`processedFeedIds`).
2. Read `feed_digest_batch_size` (**0 means unlimited**, not "default 10") and
   `feed_digest_titles_only` (defaults to **true**).
3. Load unread entries across the feed's source feeds, sort ascending by date,
   deduplicate by GUID (the same article can arrive via two feeds).
4. Drop entries that are our own output (`isSummaryArticle()`), already
   processed (`isAlreadyProcessed()`), or already covered by a category summary
   (`isProcessedByCategoryScope()` / `isProcessedForScope()`).
5. Split the rest with `getSkipReason()` into "worth summarizing" and "skipped".
6. Clear any stale skip note from articles that are now eligible; write or
   refresh skip notes on the ones still skipped.
7. Process in batches of `batchSize` (a final partial batch is always processed),
   choosing a mode per batch:
   - **feed mode + batch size 1** -> `processTranslation()` (one translated copy per article)
   - **titles-only** -> `processTitlesOnly()` (no per-article LLM call)
   - **otherwise** -> `processSummary()` (one LLM call for the whole batch)
Each batch is independent: it either succeeds (articles marked read when
`shouldMarkRead()`, default true) or throws, and a throw breaks out of the loop
leaving the remaining articles unread for the next attempt. On overall success
`handleUserMaintenance()` calls `recordFeedRun()`, which computes the next due
time.

### The skip rule (`getSkipReason()`)

This is the part most often misunderstood:

- An article is skipped **only** when it contains an `<img>` **and** has
  **zero** characters of visible text. There is nothing but a title to send.
- Short articles are **deliberately included**. The summary prompt explicitly
  instructs the model to write a best-effort one-sentence summary from the
  headline when the body is thin.
- The rule used to be `hasImages && textLength < 200`, which silently dropped
  ordinary short posts and photo captions. Do not reintroduce a length threshold
  here without a strong reason - summarizing short items is a product decision.

Two supporting details make re-evaluation safe:

- `stripSkipNote()` removes the extension's own "not summarized" `<div>` before
  any length measurement, before the `isAlreadyProcessed()` check, and before
  content is sent to the API. Without this the note itself would count as article
  text and the model would be asked to summarize the extension's own warning.
- `isAlreadyProcessed()` therefore keys off real Feed Digest markup only (e.g. a
  summary box), not the skip note, so articles skipped under an older, stricter
  rule become eligible again automatically.

### Batch summarization (`processSummary()`)

One API call covers the whole batch. The model returns a JSON **array** whose
entries carry a `summary` (and optionally `translated_content`).

`parseLLMResponse()` is defensive by design: if the model returns a different
number of summaries than articles sent, it realigns **positionally** rather than
failing the batch. Surplus summaries are ignored, missing ones become empty
summaries, and the article is still listed by title and link. A response with no
usable summaries at all still throws, leaving the articles unread for a retry.

### Items are the durable unit

`buildDigestItemsFromEntries()` produces the canonical item shape:

```
{title, link, feed, feed_link, summary, id, time}
```

Everything downstream works on item lists, not on "articles". This is the key
idea in the codebase:

- `createConsolidatedDigest()` looks for **still-unread** digests for the same
  feed, recovers their items, and merges them with the new batch so the new
  digest stays comprehensive. Per-article summaries are carried **verbatim** and
  never regenerated - only the theme/TL;DR/bullets cost a second LLM call.
  A run always **inserts a new entry** at the current timestamp; the carried
  items are never written back into the existing row. That is deliberate: it
  makes an append visible as a new summary, and it means reading the pending
  summary mid-run cannot hide articles (the read row is simply retired).
- Items are persisted in the entry attribute `feed_digest_items`
  (`digestItemsAttribute()`), capped at 60 KB because FreshRSS stores attributes
  in a TEXT column capped at 64 KB on MySQL/MariaDB. Oversized digests drop the
  attribute and are re-parsed from rendered HTML by `parseLegacyDigestItems()`.
- `mergeDigestItems()` deduplicates by normalized link and **never lets an empty
  summary overwrite a non-empty one**, so a model that declines to summarize
  cannot erase a summary generated on an earlier run.
- `extractDigestItems()` prefers the stored attribute and falls back to HTML
  parsing for digests written before the attribute existed.
- `time` is the **article publish time**, not the fetch time. It is rendered as
  a leading `HH:MM` label (`formatDigestItemTime()`) and is what orders the list.
  `fillMissingItemTimes()` backfills it for items stored before the field
  existed, resolving by article id first and by link second - so old digests
  still sort correctly instead of collapsing to the bottom.

### Ordering and grouping (easy to get backwards)

- Items are grouped by source feed, preserving first-seen feed order, and sorted
  **newest first inside each group** by publish time (`groupDigestItems()`).
  Undated items sort last.
- The stored list order is the *carried-then-new* order used for merging. It is
  **not** the render order - never assume the persisted array is display order.
- The `HH:MM` label is real rendered text, not hidden metadata, so ordering and
  the visible time survive the oversized-digest path where item metadata is
  dropped and HTML is re-parsed.
- The label deliberately carries time only, with no date. Do not "fix" it by
  anchoring a bare `HH:MM` to today when parsing: an article published days ago
  would then sort among today's. Resolve the real date from the article row.

### Write-then-retire ordering (do not change)

`createConsolidatedDigest()` **inserts the new digest before marking the old ones
read**. If the insert fails it throws, the pending digest stays unread, and the
whole batch is retried next run. Reversing this would drop articles whenever an
insert failed. A digest whose items cannot be recovered but which does contain
links (`digestHasAnchors()`) is left unread and untouched on purpose - retiring it
would silently discard its articles.

`insertUniqueDigest()` builds the GUID from the entry id set plus a timestamp and
`uTimeString()`, and returns `false` instead of throwing on a duplicate, so a
double-fired maintenance hook cannot retire a pending digest with no replacement.

### Top-level section

`createTopLevelSummaryFromItems()` makes a single LLM call for the batch theme,
the TL;DR, and the bullet points, on top of the per-article summaries (or titles).
It works from the merged item list, so it is regenerated on every run even when
all per-article summaries were carried. Output is validated (non-empty, length
capped) and the theme is optional: if it fails to validate, the digest still
renders without the theme line.

`formatDigestContent()` renders theme -> TL;DR -> bullets -> per-feed groups. The
same renderer serves titles-only and full-summary modes, and every source feed
uses the same list layout even when it contributes a single article.

## Other behaviors worth knowing

- **Scheduling**: `isFeedDue()` / `computeNextRunAt()` support `auto` (always
  due), `daily` (HH:MM list, local timezone), and `interval` (minutes).
  `migrateIntervalUnitToMinutes()` converts legacy hour values once, guarded by
  `interval_unit_version`.
- **Backoff**: `recordFeedFailure()` sets `feed_digest_backoff_until`, honoring an
  `X-RateLimit-Reset` value parsed out of the error text when present.
- **Logging**: `Minz_Log::notice` is effectively invisible in production - only
  warning and error are recorded. Use `warning` for anything you need to see.
- **Category Main-stream hiding** is implemented by demoting each source feed's
  priority to `PRIORITY_CATEGORY` and stashing the original in the feed attribute
  `feed_digest_hide_main_original_priority`. A stricter explicit per-feed choice
  wins and stops being tracked. This is why `handleActionExecute()` exists:
  FreshRSS's own handlers write priority after our init hook runs.
- **Prompt injection**: prompts tell the model to treat article text as data, not
  as instructions. Keep that language when editing prompts.

## Verifying changes

There is no PHP on the host and no test suite or build step. PHP is available
inside the running FreshRSS container, and the extension directory is
bind-mounted live, so edits are visible to it immediately:

```bash
# Syntax check (must pass)
docker exec freshrss php -l /var/www/FreshRSS/extensions/xExtension-FeedDigest/extension.php

# Equivalent without touching the running container (or when it is down)
docker run --rm -v "$PWD":/app -w /app php:8.2-cli php -l extension.php
```

The class **cannot be instantiated outside FreshRSS** - it extends
`Minz_Extension` and calls `FreshRSS_Factory`. Verify logic by copying the pure
string helpers into a standalone scratch script and running them under Docker
against representative HTML fixtures (only works for helpers that never touch
the DAOs), or by exercising it in a live FreshRSS instance and reading
`data/users/<user>/log.txt` for `Feed Digest:` lines.

### End-to-end harness recipe

This is the only way to catch pipeline bugs (item carry-over, write ordering,
read state). Bootstrap FreshRSS from the CLI, create a throwaway user, drive the
real methods by reflection, and serve a stub OpenAI-compatible endpoint.

```bash
# Never test against the real 'admin' user.
docker exec freshrss php /var/www/FreshRSS/cli/create-user.php \
  --user fdtest --password 'TestPass123456' --no-default-feeds

# Stub LLM on loopback inside the container.
docker exec -d freshrss sh -c \
  'php -S 127.0.0.1:8899 /tmp/fdtest/stub.php > /tmp/fdtest/server.log 2>&1'

docker exec freshrss php /tmp/fdtest/harness.php fdtest all
```

Harness prologue that makes this work:

```php
chdir('/var/www/FreshRSS');
require '/var/www/FreshRSS/constants.php';
require LIB_PATH . '/lib_rss.php';
Minz_Session::init('FreshRSS', true);
FreshRSS_Context::initSystem();
Minz_ExtensionManager::init();
Minz_Translate::init(Minz_Translate::DEFAULT_LANGUAGE);
FreshRSS_Context::$isCli = true;   // matches cli/_cli.php; avoids web-only side effects
FreshRSS_Context::initUser('fdtest');
Minz_ExtensionManager::enableByList(
    FreshRSS_Context::systemConf()->extensions_enabled, 'system');  // system, not user
$ext = Minz_ExtensionManager::findExtension('Feed Digest');
```

Details that cost real time to discover:

- The extension is registered under the **`metadata.json` name** ("Feed
  Digest"), not the class name or directory.
- It is a **system** extension: enable it from `systemConf()`. Looking it up via
  the per-user `userConf()` returns `findExtension() === null`.
- `processFeed()` and `handleUserMaintenance()` are private - drive them with
  `ReflectionMethod::setAccessible(true)`.
- `processedFeedIds` suppresses a second run of the same feed **within one
  process**. To simulate separate cron cycles either reset it by reflection
  between runs, or run each cycle as a separate `php` invocation.
- Pass the stub URL as `processFeed()`'s `$apiEndpoint`
  (`http://127.0.0.1:8899/v1`); the stub ignores the key and model.
- Have the stub record the *kind* of each request (per-article batch vs
  top-level). That is how you prove carried items are not re-summarized.
- Give fixtures distinct publish times; item ordering and the `HH:MM` label both
  depend on them.

### Clean up afterwards

```bash
docker exec freshrss sh -c 'php /var/www/FreshRSS/cli/delete-user.php --user=fdtest'
docker exec freshrss sh -c 'pkill -f "php -S 127.0.0.1:8899"'
```

Remove scratch files from `/tmp` in the container **and** on the host - the
extensions directory is shared, `/tmp` is not. Confirm you left no test entries
behind by querying the real user's DB for your fixture GUID/title prefixes.

## Inspecting a live instance

The live instance is the `freshrss` container, bind-mounting the host paths:

```
/home/k/.config/freshrss/data       -> /var/www/FreshRSS/data
/home/k/.config/freshrss/extensions -> /var/www/FreshRSS/extensions
```

Query it with `docker exec freshrss php -r '...'` or a heredoc
(`docker exec -i freshrss php <<'EOF'`). Facts that make forensics much faster:

- **Every digest is an entry** with `guid LIKE 'llm-summary-%'` inside a real
  feed row (an ordinary feed, or a `@ <Category> Summary` feed).
- **Insertion time is recoverable from the row id**: `uTimeString()` is
  `seconds . microseconds`, so `intdiv((int)$id, 1000000)` is the epoch second
  the row was created. Use it to reconstruct history independently of `date`.
- `date` is the digest's own timestamp; `lastUserModified` is when the row was
  marked read (by the user, or by the extension retiring it); `is_read` is the
  current flag. Compare them: `lastUserModified > date` means it was read after
  its last write, `date > lastUserModified` means an older build rewrote a row
  the user had already read. Current builds never rewrite - each run inserts.
- `attributes.feed_digest_items` is the item list. When it is absent or empty,
  either the digest was written before the field existed or it exceeded the
  64 KB attribute cap (`digestItemsAttribute()`); both cases are recovered from
  the rendered HTML (`parseLegacyDigestItems()`). Check the item count and the
  rendered `<li>` count together - a mismatch means the parser is not
  recognising that markup, which is a bug worth fixing rather than tolerating.
- All per-feed and per-category state lives in feed attributes named
  `feed_digest_*`. Dump the whole attribute JSON before concluding "the setting
  did not save".

DAO rules that bite:

- `Minz_ModelPdo::fetchAssoc()` binds parameters **by name only**. Positional
  `?` placeholders fail with
  `ValueError: PDOStatement::bindValue(): Argument #1 ($param) must be >= 1`.
- SQL is written as `` `_entry` `` and auto-prefixed: on SQLite the prefix is
  empty so the physical table is `entry`, on MySQL/MariaDB it is
  `<prefix><user>_entry`. Go through the DAO rather than hard-coding a name.
- Chunk `IN (...)` lists instead of sending one giant list. Core uses
  `FreshRSS_DatabaseDAO::MAX_VARIABLE_NUMBER` (998); this extension uses 400
  (`fillMissingItemTimes()`).
- `searchById()` takes the id **as a string** and returns `?FreshRSS_Entry`.
- The DB is owned by `www-data` and `data/users/<user>/` is mode `0700`, so the
  host cannot read it directly - always go through the container.

## Failure signatures

| Symptom | Check | Likely cause |
| --- | --- | --- |
| A summary I read is missing articles | `lastUserModified` vs `date` on that row | older build rewrote the row in place after it was read |
| No new summary, articles seemingly lost | unread digests with `items=0` but `<a href` present | unrecognised legacy format; see `could not be recovered` |
| Two pending digests for one feed | `GROUP BY id_feed HAVING COUNT(*) > 1` over unread digests | pending digest not found by the lookup, or an insert failed |
| A cycle produced nothing | log `Batch #N failed for ...` | LLM error, count mismatch, HTTP 429/524 |
| Log is missing lines you expect | `data/users/<user>/log.txt` | `notice` is filtered in production - log at `warning` |
| Articles stay unread for hours | `feed_digest_backoff_until` in feed attributes | backoff after a failure |
| Settings appear not to save | feed attribute JSON | core rewrites feed rows after our hooks; see the two save paths in "Known discrepancies / gotchas" |

## Known discrepancies / gotchas

- `README.md` claims PHP 7.4+, but `extension.php` uses named arguments and
  `str_starts_with()`, so it requires **PHP 8.0+**. PHP 7.4 fails to parse.
  `#[\Override]` is a no-op on versions that do not know the attribute.
- `extension.php` is one large class with no namespace. Prefer adding a private
  helper over introducing new structure, and keep the surrounding style (tabs,
  `private function`, docblocks on non-obvious logic).
- Per-feed settings are persisted from request parameters in two places
  (`handleFreshRSSInit()` and `handleActionExecute()`), because FreshRSS core
  rewrites feed rows after the extension's hooks run. When changing how a feed
  setting is saved, check both paths.
- README wording about filtering, batching, and consolidation is the
  user-visible documentation of the rules described above. Update it alongside
  behavior changes.
- **`minz_log`/`Minz_Log::notice` is invisible in production.** The default
  `environment` is `production`, which records only `warning` and `error`, so
  any `notice` you add - including ones already in this file - will not appear in
  `data/users/<user>/log.txt`. Log anything you need to observe at `warning`.
- **Per-account logs, not one shared log.** `Minz_Log` without an explicit file
  writes to `data/users/<current user>/log.txt` (only `ADMIN_LOG` goes to
  `data/users/_/log.txt`). When hunting `Feed Digest:` lines, read the account
  the maintenance run executed as - usually `admin`.
- **Category summary feeds are never actualized.** They exist only as rows to
  hold digest entries; `handleFeedsListBeforeActualize()` keeps FreshRSS from
  trying to fetch their placeholder URL, and clears any stale error state. Do
  not "fix" a summary feed's error flag anywhere else.
- **A run is several LLM calls, not one.** One per multi-article batch, plus one
  for the top-level theme/TL;DR/bullets. Recipes that count calls must expect
  `batches + 1`; only titles-only mode with a single batch is `1`.
- **Retention is unbounded.** Read digests are kept forever and summary feeds
  have no TTL, so entries accumulate (roughly under 1 MB/day across the current
  feeds). Any pruning must never delete an unread digest - that would drop its
  articles - and must leave at least one summary per feed.
- **Do not test against the real `admin` user.** Create a throwaway user; a
  harness bug that marks entries read or rewrites a feed's attributes is
  otherwise indistinguishable from the extension misbehaving, and the real
  pending digests get consumed.
