# Feed Digest Extension for FreshRSS

Automatically summarize newly retrieved RSS articles using LLM APIs (OpenAI-compatible). This extension processes articles during feed updates, creates combined summary articles in your destination language, and marks the originals as read.

## Features

- 🤖 **Automatic Summarization**: Processes unread articles using LLM APIs during scheduled feed updates
- 🌍 **Multi-language**: Translates article titles and summaries to your chosen language
- ⚡ **Efficient Batch Processing**: Summarizes multiple articles in a single API call to reduce costs
- 📊 **Per-feed Control**: Enable/disable summarization and configure batch size for each feed individually
- 🎯 **Smart Filtering**: Skips image-only and too-short articles, adds explanatory notes
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
  - The LLM will translate titles and write summaries in this language

- **Max Content Length**: Maximum characters per article (500-16000)
  - Default: 4000
  - Truncates longer articles to avoid LLM context limits
  - Estimate: 1 char ≈ 0.4 tokens

### Per-Feed Settings

To enable summarization for a specific feed:

1. Navigate to **Settings → Feeds**
2. Select the feed you want to summarize
3. Scroll to the **Feed Digest** section
4. Configure the following:
   - **Summarize articles with LLM**: Set to **Yes**
   - **Articles per summary batch**: Number of articles to include in each summary (1-50, default: 10)
     - Articles are processed in batches to avoid timeouts
     - Each batch creates one summary article
     - Example: 35 unread articles with batch size 10 → 3 summary articles (10+10+10), 5 remain unread
5. Click **Submit**

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
2. **Feed Check**: For each feed with summarization enabled, it fetches unread articles (up to 200)
3. **Article Filtering**:
   - Filters out previously created summary articles
   - Identifies image-only or too-short articles (< 100 characters)
   - Adds explanatory notes to skipped articles (they remain unread for you to review)
4. **Batch Processing**: Articles are processed in configurable batches (default: 10 per batch)
   - Only processes batches when enough articles are available
   - Each batch is sent to the LLM API in one request for efficiency
   - Each batch succeeds or fails independently
5. **Summary Creation**: For each batch, a new "summary" article is created with:
   - Translated titles (in your destination language)
   - Concise summaries (2-4 sentences each)
   - Links to original articles
   - Clean HTML formatting
6. **Mark as Read**: Only successfully summarized articles are marked as read
7. **Auto-retry**: Failed batches remain unread and will be retried on the next update

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
- Recommended: 300 seconds (5 minutes) for safety with multiple feeds

## Cost Estimation

API costs vary by provider, model, tokenization, and response length. The estimates below use standard list prices as of August 2026 and assume a typical batch of 10 articles at the default maximum of 4,000 characters each:

- Approximately 40,000 input characters, estimated as 16,000 input tokens
- Approximately 1,000 output tokens for 10 translated titles and summaries

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
- **Batch Processing**: Articles must accumulate to the configured batch size before processing
- **Sequential Batches**: Each feed's batches are processed sequentially to avoid timeouts
- **No Retry Tracking**: Failed batches retry every update (no exponential backoff)
- **Context Limits**: Very long articles are truncated based on max content length setting
- **Image-only Articles**: Articles with minimal text are skipped and left unread with an explanatory note

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
