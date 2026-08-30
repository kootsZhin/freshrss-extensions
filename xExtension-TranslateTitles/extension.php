<?php
require_once __DIR__ . '/lib/TranslateController.php';

class TranslateTitlesExtension extends Minz_Extension {
    public const DEFAULT_SOURCE_LANG = 'zh-CN';
    public const DEFAULT_TARGET_LANG = 'zh-TW';
    public const SUPPORTED_LANGUAGES = [
        'zh-CN' => 'Chinese (Simplified)',
        'zh-TW' => 'Chinese (Traditional)',
        'zh' => 'Chinese (Generic)',
        'en' => 'English',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'fr' => 'French',
        'de' => 'German',
        'es' => 'Spanish',
        'ru' => 'Russian',
    ];

    public function init() {
        error_log('TranslateTitles: Plugin initializing...');
        
        if (!extension_loaded('mbstring')) {
            error_log('TranslateTitles 插件需要 PHP mbstring 扩展支持');
        }
        
        $this->registerHook('feed_before_insert', array($this, 'addTranslationOption'));
        $this->registerHook('entry_before_insert', array($this, 'translateTitle'));

        if (!class_exists('FreshRSS_Context', false) || !FreshRSS_Context::hasUserConf()) {
            error_log('TranslateTitles: No user context available during init');
            return;
        }

        $userConf = FreshRSS_Context::userConf();

        // Legacy key kept for backward compatibility but always coerced to Google-only mode.
        $userConf->TranslateService = 'google';

        $sourceLang = self::normalizeLanguageCode(
            $userConf->SourceLang ?? null,
            self::DEFAULT_SOURCE_LANG
        );
        $targetLang = self::normalizeLanguageCode(
            $userConf->TargetLang ?? null,
            self::DEFAULT_TARGET_LANG
        );
        $userConf->SourceLang = $sourceLang;
        $userConf->TargetLang = $targetLang;

        $userConf->save();

        error_log('TranslateTitles: Hooks registered');
        // error_log('TranslateTitles: Current translation config: ' . json_encode(FreshRSS_Context::$user_conf->TranslateTitles));
    }

    public function handleConfigureAction() {
        // 处理配置请求
        if (Minz_Request::isPost()) {
            if (!class_exists('FreshRSS_Context', false) || !FreshRSS_Context::hasUserConf()) {
                error_log('TranslateTitles: No user context while saving configuration');
                return;
            }

            $userConf = FreshRSS_Context::userConf();
            $userConf->TranslateService = 'google';

            $sourceLang = self::normalizeLanguageCode(
                Minz_Request::param('SourceLang', self::DEFAULT_SOURCE_LANG),
                self::DEFAULT_SOURCE_LANG
            );
            $targetLang = self::normalizeLanguageCode(
                Minz_Request::param('TargetLang', self::DEFAULT_TARGET_LANG),
                self::DEFAULT_TARGET_LANG
            );
            $userConf->SourceLang = $sourceLang;
            $userConf->TargetLang = $targetLang;
            
            $translateTitles = Minz_Request::param('TranslateTitles', array());
            error_log("TranslateTitles: Saving translation config: " . json_encode($translateTitles));
            
            // 确保配置是数组形式
            if (!is_array($translateTitles)) {
                $translateTitles = array();
            }
            
            // 保存配置
            $userConf->TranslateTitles = $translateTitles;

            // 保存并记录结果
            $saveResult = $userConf->save();
            error_log("TranslateTitles: Config save result: " . ($saveResult ? 'success' : 'failed'));
            
            // 保存后立即验证配置
            error_log("TranslateTitles: Saved config verification: " . 
                json_encode($userConf->TranslateTitles));
        }
    }

    public function handleUninstallAction() {
        if (!class_exists('FreshRSS_Context', false) || !FreshRSS_Context::hasUserConf()) {
            error_log('TranslateTitles: No user context while uninstalling');
            return;
        }

        $userConf = FreshRSS_Context::userConf();

        // 清除所有与插件相关的用户配置
        if (isset($userConf->TranslateService)) {
            unset($userConf->TranslateService);
        }
        if (isset($userConf->TranslateTitles)) {
            unset($userConf->TranslateTitles);
        }
        if (isset($userConf->SourceLang)) {
            unset($userConf->SourceLang);
        }
        if (isset($userConf->TargetLang)) {
            unset($userConf->TargetLang);
        }
        $userConf->save();
    }

    public function translateTitle($entry) {
        if (!class_exists('FreshRSS_Context', false) || !FreshRSS_Context::hasUserConf()) {
            error_log('TranslateTitles: No user context during entry processing');
            return $entry;
        }

        $userConf = FreshRSS_Context::userConf();
        $feed = $entry->feed();
        if ($feed === null) {
            error_log('TranslateTitles: Entry has no associated feed');
            return $entry;
        }

        // 原有的翻译逻辑
        $feedId = $feed->id();
        $translateTitles = $userConf->TranslateTitles ?? array();
        if (!isset($translateTitles[$feedId]) || $translateTitles[$feedId] != '1') {
            return $entry;
        }

        $title = $entry->title();
        error_log("Original title: " . $title);

        $translateController = new TranslateController();
        $translatedTitle = $translateController->translateTitle($title, $userConf);

        if (empty($translatedTitle) || $translatedTitle === $title) {
            error_log("TranslateTitles: Translation failed or unchanged for feed {$feedId}");
        } else {
            error_log("Translated title: " . $translatedTitle);
            $entry->_title($translatedTitle); // 只保留翻译后的标题
        }
        return $entry;
    }

    // 添加一个辅助函数来获取用户列表
    private function listUsers() {
        $path = DATA_PATH . '/users';
        $users = array();
        if ($handle = opendir($path)) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." && is_dir($path . '/' . $entry)) {
                    $users[] = $entry;
                }
            }
            closedir($handle);
        }
        return $users;
    }

    public function addTranslationOption($feed) {
        $feed->TranslateTitles = '0';
        return $feed;
    }

    public function handleTestAction() {
        header('Content-Type: application/json');
        
        $text = Minz_Request::param('test-text', '');
        if (empty($text)) {
            return $this->view->_error(404);
        }

        if (!class_exists('FreshRSS_Context', false) || !FreshRSS_Context::hasUserConf()) {
            $this->view->_path('configure');
            $this->view->testResult = [
                'success' => false,
                'message' => 'No user context available',
            ];
            return;
        }

        $userConf = FreshRSS_Context::userConf();

        try {
            $sourceLang = self::normalizeLanguageCode(
                $userConf->SourceLang ?? null,
                self::DEFAULT_SOURCE_LANG
            );
            $targetLang = self::normalizeLanguageCode(
                $userConf->TargetLang ?? null,
                self::DEFAULT_TARGET_LANG
            );

            $translationService = new TranslationService($sourceLang, $targetLang);
            $translatedText = $translationService->translate($text);

            if (!empty($translatedText)) {
                // 返回成功页面
                $this->view->_path('configure');
                $this->view->testResult = [
                    'success' => true,
                    'message' => $translatedText
                ];
            } else {
                $this->view->_path('configure');
                $this->view->testResult = [
                    'success' => false,
                    'message' => '翻译失败'
                ];
            }
        } catch (Exception $e) {
            $this->view->_path('configure');
            $this->view->testResult = [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public static function normalizeLanguageCode($code, $fallback) {
        if (!is_string($code)) {
            return $fallback;
        }

        $candidate = trim($code);
        if (isset(self::SUPPORTED_LANGUAGES[$candidate])) {
            return $candidate;
        }

        return $fallback;
    }
}
