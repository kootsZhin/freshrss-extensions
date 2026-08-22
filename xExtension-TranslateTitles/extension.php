<?php
require_once('lib/TranslateController.php');

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
        
        if (php_sapi_name() == 'cli') {
            // 确保 CLI 模式下有正确的用户上下文
            if (!FreshRSS_Context::$user_conf) {
                error_log('TranslateTitles: No user context in CLI mode');
                // 可能需要手动初始化用户上下文
                $username = 'default'; // 或其他用户名
                FreshRSS_Context::$user_conf = new FreshRSS_UserConfiguration($username);
                FreshRSS_Context::$user_conf->load();
            }
        }
        
        $this->registerHook('feed_before_insert', array($this, 'addTranslationOption'));
        $this->registerHook('entry_before_insert', array($this, 'translateTitle'));

        // Legacy key kept for backward compatibility but always coerced to Google-only mode.
        FreshRSS_Context::$user_conf->TranslateService = 'google';

        $sourceLang = self::normalizeLanguageCode(
            FreshRSS_Context::$user_conf->SourceLang ?? null,
            self::DEFAULT_SOURCE_LANG
        );
        $targetLang = self::normalizeLanguageCode(
            FreshRSS_Context::$user_conf->TargetLang ?? null,
            self::DEFAULT_TARGET_LANG
        );
        FreshRSS_Context::$user_conf->SourceLang = $sourceLang;
        FreshRSS_Context::$user_conf->TargetLang = $targetLang;

        FreshRSS_Context::$user_conf->save();

        error_log('TranslateTitles: Hooks registered');
        // error_log('TranslateTitles: Current translation config: ' . json_encode(FreshRSS_Context::$user_conf->TranslateTitles));
    }

    public function handleConfigureAction() {
        // 处理配置请求
        if (Minz_Request::isPost()) {
            FreshRSS_Context::$user_conf->TranslateService = 'google';

            $sourceLang = self::normalizeLanguageCode(
                Minz_Request::param('SourceLang', self::DEFAULT_SOURCE_LANG),
                self::DEFAULT_SOURCE_LANG
            );
            $targetLang = self::normalizeLanguageCode(
                Minz_Request::param('TargetLang', self::DEFAULT_TARGET_LANG),
                self::DEFAULT_TARGET_LANG
            );
            FreshRSS_Context::$user_conf->SourceLang = $sourceLang;
            FreshRSS_Context::$user_conf->TargetLang = $targetLang;
            
            $translateTitles = Minz_Request::param('TranslateTitles', array());
            error_log("TranslateTitles: Saving translation config: " . json_encode($translateTitles));
            
            // 确保配置是数组形式
            if (!is_array($translateTitles)) {
                $translateTitles = array();
            }
            
            // 保存配置
            FreshRSS_Context::$user_conf->TranslateTitles = $translateTitles;

            // 保存并记录结果
            $saveResult = FreshRSS_Context::$user_conf->save();
            error_log("TranslateTitles: Config save result: " . ($saveResult ? 'success' : 'failed'));
            
            // 保存后立即验证配置
            error_log("TranslateTitles: Saved config verification: " . 
                json_encode(FreshRSS_Context::$user_conf->TranslateTitles));
        }
    }

    public function handleUninstallAction() {
        // 清除所有与插件相关的用户配置
        if (isset(FreshRSS_Context::$user_conf->TranslateService)) {
            unset(FreshRSS_Context::$user_conf->TranslateService);
        }
        if (isset(FreshRSS_Context::$user_conf->TranslateTitles)) {
            unset(FreshRSS_Context::$user_conf->TranslateTitles);
        }
        if (isset(FreshRSS_Context::$user_conf->SourceLang)) {
            unset(FreshRSS_Context::$user_conf->SourceLang);
        }
        if (isset(FreshRSS_Context::$user_conf->TargetLang)) {
            unset(FreshRSS_Context::$user_conf->TargetLang);
        }
        FreshRSS_Context::$user_conf->save();
    }

    public function translateTitle($entry) {
        // CLI 模式下的特殊处理
        if (php_sapi_name() == 'cli') {
            if (!FreshRSS_Context::$user_conf) {
                // 获取所有用户列表
                $usernames = $this->listUsers();
                foreach ($usernames as $username) {
                    // 初始化用户配置
                    FreshRSS_Context::$user_conf = new FreshRSS_UserConfiguration($username);
                    FreshRSS_Context::$user_conf->load();
                    break; // 只处理第一个用户
                }
            }
        }
        
        // 原有的翻译逻辑
        $feedId = $entry->feed()->id();
        if (isset(FreshRSS_Context::$user_conf->TranslateTitles[$feedId]) && 
            FreshRSS_Context::$user_conf->TranslateTitles[$feedId] == '1') {
            $title = $entry->title();
            error_log("Original title: " . $title);
            
            $translateController = new TranslateController();
            $translatedTitle = $translateController->translateTitle($title);
            
            error_log("Translated title: " . ($translatedTitle ?: 'translation failed'));
            
            if (!empty($translatedTitle)) {
                $entry->_title($translatedTitle . ' - ' . $title); // 将翻译后的标题放在前，原文标题放在后
            }
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

        try {
            $sourceLang = self::normalizeLanguageCode(
                FreshRSS_Context::$user_conf->SourceLang ?? null,
                self::DEFAULT_SOURCE_LANG
            );
            $targetLang = self::normalizeLanguageCode(
                FreshRSS_Context::$user_conf->TargetLang ?? null,
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