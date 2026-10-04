# Feed Digest Extension for FreshRSS

Automatically summarize newly retrieved RSS articles using LLM APIs (OpenAI-compatible). This extension processes articles during feed updates, creates combined summary articles in your destination language, and marks the originals as read.

## Features

- 🤖 **Automatic Summarization**: Processes unread articles using LLM APIs during scheduled feed updates
- 🌍 **Multi-language**: Writes summaries and digests in your chosen language while keeping original titles
- ⚡ **Efficient Batch Processing**: Summarizes multiple articles in a single API call to reduce costs
- 📊 **Per-feed Control**: Enable/disable summarization and configure batch size for each feed individually
- 📑 **Titles-only Digests**: Group original titles by feed without per-article summaries
- 🕒 **Per-feed Scheduling**: Choose automatic, daily times, or interval-based runs
- ✅ **Read-state Control**: Choose whether successfully processed source articles are marked read
- 🧭 **Feed Overview**: Adds a batch theme, a short high-level overview, and configurable key-theme bullet points above each batch's article summaries
- 🚫 **Per-category Main stream visibility**: Hide an entire category's source feeds from the Main stream / All view while they remain accessible inside the category and through its `@ Category Summary` feed
- 🗂️ **Shared Digests category**: Every category summary feed lives in one **Digests** category, so external readers (Reeder, and other Google Reader clients) can show all digests in a single folder
- 🎯 **Smart Filtering**: Summarizes short articles too, skipping only image-only posts with no text
- 🎨 **Clean Output**: Creates formatted summary articles with links to originals

## Requirements

- FreshRSS 1.24.0 or later
- PHP 7.4+ with cURL extension
- An OpenAI-compatible API key (OpenAI, Anthropic Claude, local models, etc.)
- Sufficient PHP `max_execution_time` (recommended: 300+ seconds for large batches)

## Installation

1. Download or clone this repository
2. Copy the `xExtension-FeedDigest` directory to your FreshRSS `extensions` directory:
   ```bash
   cp -r xExtension-FeedDigest /path/to/FreshRSS/extensions/
   ```
3. In FreshRSS, navigate to **Settings → Extensions**
4. Enable the "Feed Digest" extension
5. Click "Configure" to set up your API credentials

## Configuration

### Global Settings

Navigate to **Settings → Extensions → Feed Digest → Configure**

Required settings:
- **API Endpoint**: OpenAI-compatible API endpoint URL
  - OpenAI: `https://api.openai.com/v1`
  - Anthropic Claude (via OpenAI compatibility): Check your provider's documentation
  - Local models: Your local endpoint URL

- **API Secret Key**: Your API authentication key
  - Keep this secure!
  - Never share or commit this key to version control

- **Model Name**: The LLM model to use
  - OpenAI: `gpt-5.6-luna` (recommended for current-generation, high-volume workloads), `gpt-5.6-terra` (higher quality)
  - Claude (through an OpenAI-compatible provider): `claude-haiku-4-5` or the pinned `claude-haiku-4-5-20251001`
  - Other: Check your provider's model names

- **Destination Language**: Target language for summaries and translations
  - Examples: `English`, `Spanish`, `Simplified Chinese`, `French`, `Japanese`, `German`
  - The LLM writes summaries in this language; article titles are kept as-is
  - Feeds and categories can optionally override this with their own summary language

- **Secondary Language** *(optional)*: Second language shown as a separate muted paragraph below the primary language for the theme and TL;DR lines. Disabled by default; feeds and categories can optionally override the global choice.

- **Max Content Length**: Maximum visible article-text characters per article (500 or more)
  - Default: 4000
  - HTML markup, image/tracking boilerplate, and newsletter padding do not count toward the limit, so this measures what the model actually receives
  - Truncates longer articles to avoid LLM context limits
  - Estimate: 1 char ≈ 0.4 tokens

### Per-Feed Settings

To enable summarization for a specific feed:

1. Navigate to **Settings → Feeds**
2. Select the feed you want to summarize
3. Scroll to the **Feed Digest** section
4. Configure the following:
   - **Summarize articles with LLM**: Set to **Yes**
   - **Articles per summary batch**: Number of articles to include in each summary (default: 0 = unlimited)
     - Set to **0** for no limit: every unread article is summarized in a single run
     - Set to 1 to create a translated copy per article
     - Any other value batches articles (e.g. 10 → 10 articles per summary article)
     - Each run writes a new summary entry. While a summary is still **unread**, every article it lists is carried into the new summary and the old one is marked read, so you never face a backlog of pending summaries and the summary you read keeps the articles it had. Once you read a summary, the next run starts a fresh digest with only new articles. Carrying items over does not depend on whether the individual source articles are already read, and carried per-article summaries are reused as-is (no extra LLM cost)
     - Each line starts with the article's publish time as `HH:MM`, and articles are listed newest first within each source-feed group
     - Example: 35 unread articles with batch size 10 → 4 batches summarized in one run, combined into 1 pending digest, 0 remain unread
   - **Titles only**: When enabled (default), no per-article summaries are generated. Instead, one digest is created listing the original titles (each labeled with its source feed), with links to the original articles. Titles are not translated, which saves one LLM call per digest.
   - **Full summary mode**: Per-article summaries are written in the destination language, but original titles are kept as-is, which saves one LLM call per digest.
   - **Top-level theme bullets**: How many key-theme bullet points to list below the TL;DR (default: 3; set 0 to disable bullets and keep only the overview)
   - **Summary language**: Optional per-feed override for the destination language; leave blank to use the global setting
   - **Secondary language**: Optional per-feed override for the global secondary-language setting; leave Disabled to use the global default
   - **Mark source articles as read**: Controls whether successfully processed source articles are marked read. Failed and skipped articles remain unread.
   - **Schedule mode**: Choose one of `Automatic`, `Daily times`, or `Interval`.
     - Automatic runs during every maintenance cycle, as before.
     - Daily times accepts comma-separated local `HH:MM` values (default `06:00, 11:00, 17:00, 21:00`).
     - Interval runs after the configured number of minutes since the last successful run (default 60 = 1 hour; 1440 = 24 hours).
     - Daily times use the timezone configured for the FreshRSS/PHP runtime.
5. Click **Submit**

### Per-Category Visibility

Each category's **Feed Digest Summary** panel includes **Hide source feeds from
Main stream**. When enabled, the category's ordinary source feeds no longer
appear in Main stream / All; they remain in the category itself and in the
Feed Digest summary feed for that category. Their unread counts stay visible
inside the category.

Turning the option back off restores the source feeds' previous Main stream
visibility automatically. New feeds added to a hidden category start
category-only as well.

### Shared Digests Category

Category summary feeds (`@ <Category> Summary`) are collected into one shared
**Digests** category instead of being placed inside the category they summarize.
External readers such as Reeder group subscriptions by category, so a single
folder keeps every digest together and easy to reach; in FreshRSS itself the
summary feeds keep their **Important** priority, so they still appear in
Main stream / All / Important exactly as before.

The category is created automatically the first time a summary feed is needed.
You can rename it: Feed Digest remembers the category it created and keeps using
it, rather than forcing the name back to "Digests". If the category is deleted,
the next run recreates it (or adopts an existing category named "Digests") and
moves the summary feeds into it.

## API Endpoint Examples

### OpenAI

```
Endpoint: https://api.openai.com/v1
Model: gpt-5.6-luna
Key: sk-...
```

### Anthropic Claude (via OpenAI-compatible wrappers)

This extension currently uses OpenAI's Chat Completions request format, not Anthropic's native Messages API. To use Claude, configure an OpenAI-compatible provider or gateway and use its endpoint and model name. Claude Haiku 4.5 (`claude-haiku-4-5`) is a fast, cost-efficient option; verify the exact model identifier and pricing with your provider.

### Local Models (Ollama, LM Studio, etc.)

```
Endpoint: http://localhost:11434/v1  # Ollama
Model: llama3.2
Key: not-needed  # Often not required for local models
```

### OpenRouter

```
Endpoint: https://openrouter.ai/api/v1
Model: anthropic/claude-haiku-4.5
Key: sk-or-v1-...
```

## How It Works

1. **Scheduled Updates**: During your regular FreshRSS cron/scheduled feed updates, the extension activates
2. **Feed Check**: For each feed with summarization enabled, it fetches all unread articles
3. **Article Filtering**:
   - Filters out previously created summary articles
   - Skips only image-only articles that carry no text at all
   - Adds explanatory notes to skipped articles (they remain unread for you to review)
4. **Batch Processing**: Articles are processed in configurable batches
   - Batch size **0** processes every unread article in a single run
   - With a batch size, full batches are processed first, then any remaining unread articles as a final partial batch
   - Each batch is sent to the LLM API in one request for efficiency
   - Each batch succeeds or fails independently
5. **Summary Creation**: For each batch, a "summary" article is created with:
   - One natural, headline-style theme sentence for the whole batch (shown above the TL;DR, separated by a divider)
   - A detailed plain-text TL;DR overview generated from the batch's per-article summaries (or titles in titles-only mode), preserving important exact figures such as amounts, percentages, rates, counts, and time periods
   - Key-theme bullet points (configurable count, primary language only) listing the most important or recurring themes, with important exact figures stated in context and each followed by its linked source feed where available
   - Article titles grouped under one source-feed header per feed (the same list layout for single-article and multi-article feeds)
   - Original article titles (not translated)
   - Concise summaries (2-4 sentences each)
   - Links to original articles
   - Clean, compact, box-free formatting shared by both digest modes
   - With **Titles only** enabled (default), the digest instead lists original titles with source-feed labels, with links to the originals
6. **Consolidation**: A run produces a **new** summary entry at the current timestamp. If the previous summary is still unread, every article it lists is carried into the new one, and the previous summary is then marked read — so the new summary stays comprehensive and you never face a backlog of pending summaries. Per-article summaries are carried verbatim and never regenerated, so carrying them costs no extra tokens; only the theme, TL;DR, and bullets are regenerated. Because the previous summary is never modified, reading it while a run is in progress cannot hide new articles, and it is retired only after the new summary is safely stored (a failed run leaves it pending for the next attempt). A digest whose articles cannot be recovered from its stored metadata or rendered HTML is left unread and untouched, so its articles are never discarded; the extension logs `could not be recovered` when this happens. Each run logs `Created digest for ...` with how many items were new, carried, and total.
7. **Mark as Read**: Only successfully summarized articles are marked as read when enabled for that feed
8. **Auto-retry**: Failed batches remain unread and are retried after a backoff period; HTTP 429 failures wait until the provider's rate-limit reset time

The TL;DR, theme bullets, and batch theme line are produced by one LLM request per multi-article batch. If the theme field is missing from that response, the digest is still created without the theme line; if the TL;DR fails, no summary article is created and source articles remain unread.

Summaries are matched to articles by position. If the model returns a slightly different count than the number of articles sent (for example 12 summaries for 10 articles, or 8 for 10), the batch still succeeds: surplus summaries are ignored and any article without one is listed by its title and link alone. The extension logs `realigning positionally` when this happens. A response with no usable summaries at all is still treated as a failed batch, leaving the articles unread for the next run.

Link-only feeds (such as Hacker News, whose items carry only a headline and a "Comments" link) used to produce digests where every entry was title-only, because the model treated the missing article body as a reason to return an empty summary. The prompt now requires a best-effort one-sentence summary from the headline in that case, so titles-only output is again limited to feeds configured with **Titles only**. If a run still returns no summary for an article, a summary previously generated for the same article is never overwritten by the empty result: when two items share a link, the one carrying a summary is kept and only the remaining fields are refreshed.

## PHP Timeout Configuration

For large batches, you may need to increase PHP execution time:

### In php.ini:
```ini
max_execution_time = 300
```

### In FreshRSS .htaccess (Apache):
```apache
php_value max_execution_time 300
```

### In Nginx config:
```nginx
fastcgi_read_timeout 300;
```

**Estimation**:
- Each batch of 10 articles takes ~5-15 seconds (API call + processing)
- Multiple batches are processed sequentially per feed
- With an unlimited batch (0), one very large request can take longer; recommended: 300+ seconds (5+ minutes) for safety with multiple feeds

## Cost Estimation

API costs vary by provider, model, tokenization, and response length. The estimates below use standard list prices as of August 2026 and assume a typical batch of 10 articles at the default maximum of 4,000 characters each:

- Approximately 40,000 input characters, estimated as 16,000 input tokens
- Approximately 800 output tokens for 10 summaries plus the theme, TL;DR, and bullets

| Model | Input / 1M tokens | Output / 1M tokens | Estimated cost per batch |
| --- | ---: | ---: | ---: |
| `gpt-5.6-luna` (recommended) | $1.00 | $6.00 | ~$0.022 |
| `gpt-5.6-terra` | $2.50 | $15.00 | ~$0.055 |
| Claude Haiku 4.5 | $1.00 | $5.00 | ~$0.021 |

**Example scenario**: 5 feeds, each with 20 unread articles/day, batch size 10:
- 5 feeds × 2 batches/day = 10 batches/day
- With `gpt-5.6-luna`: **~$0.22/day or ~$6.60/month**
- With Claude Haiku 4.5 at Anthropic list prices: **~$0.21/day or ~$6.30/month**

Actual usage is often lower because many articles are shorter than the configured maximum. Gateway pricing, reasoning tokens, cached tokens, retries, taxes, and provider-specific fees can change the total. Check the [OpenAI model pricing](https://developers.openai.com/api/docs/models) and [Anthropic model pricing](https://platform.claude.com/docs/en/about-claude/models/overview) before deployment.

> **Tip**: Start with `gpt-5.6-luna` for current-generation quality at high volume. Claude Haiku 4.5 is a similarly priced alternative when used through an OpenAI-compatible provider.

## Troubleshooting

### API Connection Failed

1. Test your API connection using the "Test API Connection" button
2. Verify your API endpoint URL is correct
3. Check your API key is valid and has sufficient credits
4. Review FreshRSS logs for detailed error messages

### Articles Not Being Summarized

1. Verify the feed has "Summarize articles with LLM" enabled
2. Check that articles are marked as **unread**
3. Ensure your API key is configured and valid
4. Look for errors in FreshRSS logs: `data/users/_/log*.txt`

### PHP Timeout Errors

1. Increase `max_execution_time` in PHP configuration (recommended: 300 seconds)
2. Reduce "Articles per summary batch" setting for individual feeds
3. Disable summarization for some feeds to reduce total processing time

### Summaries in Wrong Language

1. Check "Destination Language" setting is correct
2. Be specific (e.g., "Simplified Chinese" vs just "Chinese")
3. Test with a single article first

### High API Costs

1. Use `gpt-5.6-luna` or Claude Haiku 4.5 for cost-sensitive workloads
2. Reduce "Articles per summary batch" for feeds (processes fewer articles at once)
3. Lower "Max Content Length" to send less data per article
4. Enable summarization only for high-value feeds
5. Monitor API usage on your provider's dashboard

## Privacy & Data Usage

- **API Calls**: Article content is sent to your configured LLM API
- **Data Storage**: Only summaries are stored locally; API has its own data retention policies
- **Security**: API keys are stored in FreshRSS configuration (keep backups secure)
- **Logging**: Errors and processing info logged to FreshRSS logs

## Limitations

- **Cron-based**: Summarization happens during scheduled updates, not immediately on manual refresh
- **Batch Processing**: Articles are grouped into batches up to the configured batch size; a smaller final batch is still processed
- **Sequential Batches**: Each feed's batches are processed sequentially to avoid timeouts
- **Retry Backoff**: Failed requests are retried after a short backoff, or after the provider's rate-limit reset time when available
- **Context Limits**: Very long articles are truncated based on max content length setting
- **Image-only Articles**: Only articles whose content is an image with no accompanying text are skipped and left unread with an explanatory note; short articles are still summarized

## Development

### Testing

To test the extension:

1. Enable for a single test feed with few articles
2. Manually trigger feed update
3. Check logs for processing messages
4. Verify summary article appears in feed
5. Confirm original articles marked as read

### Debugging

Enable detailed logging in FreshRSS and monitor:
- `data/users/_/log.txt` or `data/users/_/log_*.txt`
- Look for "Feed Digest:" prefixed messages

## Support

For issues, questions, or contributions:
- GitHub Issues: https://github.com/fengchang/xExtension-FeedDigest
- FreshRSS Community: https://github.com/FreshRSS/FreshRSS/discussions

## License

GNU Affero General Public License v3.0 (AGPL-3.0)

See [LICENSE](LICENSE) file for details.

## Credits

Developed for the FreshRSS community.

---

**Note**: This extension uses third-party AI services. Review their terms of service and privacy policies before use.
