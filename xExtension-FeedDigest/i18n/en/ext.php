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
		'warning_overview_cost' => 'Each multi-article batch uses one additional API call for its short overview.',
		'batch_threshold' => 'Batch threshold:',
		'batch_threshold_help' => 'Set the per-feed minimum article count to control when summarization triggers.',

		'api_endpoint' => 'API Endpoint',
		'api_endpoint_help' => 'OpenAI-compatible API endpoint. Examples: https://api.openai.com/v1, https://api.anthropic.com, or local model endpoints.',

		'secret_key' => 'API Secret Key',
		'secret_key_help' => 'Your API secret key for authentication. Keep this secure!',

		'model' => 'Model Name',
		'model_help' => 'LLM model name. Examples: gpt-5-nano, gpt-4o-mini, claude-3-5-sonnet-20241022, etc.',

		'dest_language' => 'Destination Language',
		'dest_language_help' => 'Target language for summaries and title translations. Examples: English, Spanish, Chinese, French, Japanese, etc.',

		'max_content_length' => 'Max Content Length per Article',
		'max_content_length_help' => 'Maximum characters per article content before truncation (500-16000). Helps avoid exceeding LLM context limits. Default 4000 is safe for most models.',

		'test_api' => 'Test API Connection',
		'test_success' => 'API connection successful!',
		'test_failed' => 'API connection failed: %s',

		// Per-feed configuration
		'per_feed_title' => 'Per-Feed Configuration',
		'per_feed_help' => 'To enable LLM summarization for a specific feed, go to the feed\'s settings page and check the "Summarize articles with LLM" option.',
		'per_feed_location' => 'Location: Settings → Feeds → [Select a feed] → Advanced Settings → Summarize articles with LLM',
		'category_title' => 'Per-Category Configuration',
		'category_help' => 'Category summaries create a native Feed Summary feed containing articles from every feed in the category.',
		'category_location' => 'Location: Settings → Feeds → [Select a category] → Feed Digest Summary',

		// Feed settings
		'feed_setting_title' => 'Feed Digest',
		'feed_setting_label' => 'Summarize articles with LLM',
		'feed_setting_help' => 'Automatically summarize new articles from this feed.',
		'category_setting_title' => 'Feed Digest Summary',
		'category_setting_label' => 'Create a summary feed for this category',
		'category_setting_help' => 'Create a Feed Summary feed containing summaries from all feeds in this category.',
		'batch_size_label' => 'Articles per summary batch',
		'batch_size_help' => 'When set to 1, each article gets a translated copy. When greater than 1, a combined summary article is created.',
		'mark_read_label' => 'Mark source articles as read',
		'mark_read_help' => 'Only successfully processed source articles are marked read. Failed and skipped articles remain unread.',
		'schedule_modes_label' => 'Schedule modes',
		'schedule_modes_help' => 'Select one or more modes. Enabled modes are combined; Automatic runs on each maintenance cycle.',
		'schedule_auto' => 'Automatic',
		'schedule_daily' => 'Daily times',
		'schedule_interval' => 'Interval',
		'schedule_times_label' => 'Daily times',
		'schedule_times_help' => 'Comma-separated local times in HH:MM format. Uses the FreshRSS/PHP timezone.',
		'schedule_interval_label' => 'Interval hours',
		'schedule_interval_help' => 'Run after this many hours since the last successful scheduled run.',
		'overview_label' => 'Feed Digest Overview:',

		// How it works
		'how_it_works_title' => 'How It Works',
		'how_step1' => 'During maintenance, the extension checks each enabled feed when one of its selected schedule modes is due.',
		'how_step2' => 'Unread articles are collected according to each feed\'s schedule and sent to the LLM API in batches.',
		'how_step3' => 'The LLM summarizes each article and translates titles to your destination language.',
		'how_step4' => 'A second LLM call creates a short overview, which is placed above the per-article summaries.',
		'how_step5' => 'Original articles are marked as read only when that feed option is enabled.',
		'how_step6' => 'If any errors occur, articles remain unread and will be retried on the next update.',
	],
];
