<?php
require_once __DIR__ . '/TranslationService.php';

class TranslateController {
    public function translateTitle($title, $userConf = null) {
        if (empty($title)) {
            error_log("TranslateTitles: Empty title provided");
            return '';
        }

        if ($userConf === null) {
            if (!class_exists('FreshRSS_Context', false) || !FreshRSS_Context::hasUserConf()) {
                error_log("TranslateTitles: No user context for translation");
                return $title;
            }
            $userConf = FreshRSS_Context::userConf();
        }

        $sourceLang = TranslateTitlesExtension::normalizeLanguageCode(
            $userConf->SourceLang ?? null,
            TranslateTitlesExtension::DEFAULT_SOURCE_LANG
        );
        $targetLang = TranslateTitlesExtension::normalizeLanguageCode(
            $userConf->TargetLang ?? null,
            TranslateTitlesExtension::DEFAULT_TARGET_LANG
        );

        $translationService = new TranslationService($sourceLang, $targetLang);
        $translatedTitle = '';
        $maxAttempts = 2;
        $sleepTime = 1; // 初始等待时间

        error_log("TranslateTitles: Service: google, Source: " . $sourceLang . ", Target: " . $targetLang . ", Title: " . $title);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                $translatedTitle = $translationService->translate($title);
                if (!empty($translatedTitle)) {
                    error_log("TranslateTitles: Translation successful: " . $translatedTitle);
                    break;
                }
                error_log("TranslateTitles: Empty translation result on attempt " . ($attempt + 1));
            } catch (Throwable $e) {
                error_log("TranslateTitles: Translation error on attempt " . ($attempt + 1) . " - " . $e->getMessage());
            }

            if ($attempt + 1 < $maxAttempts) {
                sleep($sleepTime);
                $sleepTime *= 2; // 每次失败后增加等待时间
            }
        }

        // 如果翻译仍然失败，使用原始标题
        if (empty($translatedTitle)) {
            error_log("TranslateTitles: All translation attempts failed, returning original title");
            return $title;
        }

        return $translatedTitle;
    }
}
