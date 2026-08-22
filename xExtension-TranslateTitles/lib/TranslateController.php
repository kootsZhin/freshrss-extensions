<?php
require_once('TranslationService.php');

class TranslateController {
    public function translateTitle($title) {
        if (empty($title)) {
            error_log("TranslateTitles: Empty title provided");
            return '';
        }

        $sourceLang = TranslateTitlesExtension::normalizeLanguageCode(
            FreshRSS_Context::$user_conf->SourceLang ?? null,
            TranslateTitlesExtension::DEFAULT_SOURCE_LANG
        );
        $targetLang = TranslateTitlesExtension::normalizeLanguageCode(
            FreshRSS_Context::$user_conf->TargetLang ?? null,
            TranslateTitlesExtension::DEFAULT_TARGET_LANG
        );

        $translationService = new TranslationService($sourceLang, $targetLang);
        $translatedTitle = '';
        $attempts = 0;
        $sleepTime = 1; // 初始等待时间

        error_log("TranslateTitles: Service: google, Source: " . $sourceLang . ", Target: " . $targetLang . ", Title: " . $title);

        while ($attempts < 2) {
            try {
                $translatedTitle = $translationService->translate($title);
                if (!empty($translatedTitle)) {
                    error_log("TranslateTitles: Translation successful: " . $translatedTitle);
                    break;
                }
                error_log("TranslateTitles: Empty translation result on attempt " . ($attempts + 1));
            } catch (Exception $e) {
                error_log("TranslateTitles: Translation error on attempt " . ($attempts + 1) . " - " . $e->getMessage());
                $attempts++;
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
