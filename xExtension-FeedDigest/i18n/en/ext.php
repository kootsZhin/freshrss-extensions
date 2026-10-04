<?php

/**
 * English translation for Feed Digest extension
 */

return [
	'feed_digest' => [
		// Configuration page
		'config_title' => 'Feed Digest Configuration',
		'warning_title' => 'Important Warnings:',
		'warning_cost' => 'API calls cost money. Monitor your usage and costs regularly.',
		'warning_retry' => 'Failed API calls remain unread and retry on the next due run.',
		'warning_timeout' => 'You may need to increase PHP max_execution_time for large batches (recommended: 300+ seconds).',
		'warning_rate_limit' => 'High article counts may hit API rate limits.',
		'warning_overview_cost' => 'Each multi-article batch uses one additional API call for its short overview and theme bullets.',
		'batch_threshold' => 'Batch threshold:',
		'batch_threshold_help' => 'Set the number of articles per summary batch; 0 means no limit (everything in one run).',

		'api_endpoint' => 'API Endpoint',
		'api_endpoint_help' => 'OpenAI-compatible API endpoint. Examples: https://api.openai.com/v1, https://api.anthropic.com, or local model endpoints.',

		'secret_key' => 'API Secret Key',
		'secret_key_help' => 'Your API secret key for authentication. Keep this secure!',

		'model' => 'Model Name',
		'model_help' => 'LLM model name. Examples: gpt-5-nano, gpt-4o-mini, claude-3-5-sonnet-20241022, etc.',

		'dest_language' => 'Destination Language',
		'dest_language_help' => 'Target language for summaries and digests. Examples: English, Spanish, Chinese, French, Japanese, etc.',
		'secondary_language' => 'Secondary Language',
		'secondary_language_help' => 'Optional second language shown as a separate muted paragraph below the primary language for the theme and TL;DR lines. Leave Disabled for single-language digests.',
		'secondary_language_off' => 'Disabled',

		'max_content_length' => 'Max Content Length per Article',
		'max_content_length_help' => 'Maximum visible article-text characters per article before truncation (500 or more). HTML markup and tracking boilerplate do not count toward the limit. Default 4000 is safe for most models.',
		'overview_bullets_label' => 'Top-level theme bullets',
		'overview_bullets_help' => 'How many key-theme bullet points to list above the digest. 0 disables bullets and keeps only the 2-sentence overview.',

		'test_api' => 'Test API Connection',
		'test_success' => 'API connection successful!',
		'test_failed' => 'API connection failed: %s',

		// Per-feed configuration
		'per_feed_title' => 'Per-Feed Configuration',
		'per_feed_help' => 'To enable LLM summarization for a specific feed, go to the feed\'s settings page and check the "Summarize articles with LLM" option.',
		'per_feed_location' => 'Location: Settings → Feeds → [Select a feed] → Advanced Settings → Summarize articles with LLM',

		// Per-category configuration
		'category_title' => 'Per-Category Configuration',
		'category_help' => 'Category summaries create one visible native feed named "<category name> Summary" inside the selected category.',
		'category_hide_from_main_stream_blurb' => 'You can also hide a whole category\'s source feeds from Main stream / All from the same per-category panel.',
		'category_location' => 'Location: Settings → Feeds → [Select a category] → Feed Digest Summary',
		'category_setting_title' => 'Feed Digest Summary',
		'category_setting_label' => 'Create a summary feed for this category',
		'category_setting_help' => 'Create a visible native feed containing summaries from all feeds in this category. The summary feed is placed in the shared "Digests" category so external readers can group all digests together.',
		'category_hide_from_main_stream_label' => 'Hide source feeds from Main stream',
		'category_hide_from_main_stream_help' => 'Keep this category\'s feeds visible only inside the category (and their own feed page) instead of also showing them in Main stream / All.',
		'category_batch_size_help' => 'Set to 0 for no limit (summarize everything in one run).',

		// Feed settings
		'feed_setting_title' => 'Feed Digest',
		'feed_setting_label' => 'Summarize articles with LLM',
		'feed_setting_help' => 'Automatically summarize new articles from this feed.',
		'batch_size_label' => 'Articles per summary batch',
		'batch_size_help' => 'Set to 0 for no limit (summarize everything in one run). When set to 1, each article gets a translated copy. When greater than 1, a combined summary article is created.',
		'titles_only_label' => 'Titles only',
		'titles_only_help' => 'Instead of per-article summaries, create one compact digest listing original titles (with source-feed labels) and links to the original articles, without translating titles.',
		'language_label' => 'Summary language',
		'language_help' => 'Optional language override. Leave blank to use the global Destination Language.',
		'language_global_default' => 'Use global default',
		'mark_read_label' => 'Mark source articles as read',
		'mark_read_help' => 'Only successfully processed source articles are marked read. Failed and skipped articles remain unread.',
		'schedule_modes_label' => 'Schedule mode',
		'schedule_modes_help' => 'Choose one schedule mode. Automatic runs on each maintenance cycle; Daily runs at the specified local times.',
		'schedule_auto' => 'Automatic',
		'schedule_daily' => 'Daily times',
		'schedule_interval' => 'Interval',
		'schedule_times_label' => 'Daily times',
		'schedule_times_help' => 'Comma-separated local times in HH:MM format. Uses the FreshRSS/PHP timezone.',
		'schedule_interval_label' => 'Interval minutes',
		'schedule_interval_help' => 'Run after this many minutes since the last successful scheduled run (default 60 = 1 hour).',

		// How it works
		'how_it_works_title' => 'How It Works',
		'how_step1' => 'During maintenance, the extension checks each enabled feed when its selected schedule mode is due.',
		'how_step2' => 'Unread articles are collected according to each feed\'s schedule and sent to the LLM API in batches.',
		'how_step3' => 'The LLM writes summaries in your destination language, keeping the original titles.',
		'how_step4' => 'A second LLM call creates the batch theme, TL;DR, and key-theme bullet points above the per-article summaries.',
		'how_step5_consolidate' => 'Still-unread digests for the same feed are combined into one: earlier article lists are reused as-is, the theme/TL;DR/bullets are regenerated, and the older digests are marked read.',
		'how_step5' => 'Original articles are marked as read only when that feed option is enabled.',
		'how_step6' => 'If any errors occur, articles remain unread and will be retried on the next update.',
	],
];
