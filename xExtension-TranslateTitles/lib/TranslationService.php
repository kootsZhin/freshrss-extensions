<?php
class TranslationService {
    private $googleBaseUrl;
    private $sourceLang;
    private $targetLang;

    public function __construct($sourceLang, $targetLang) {
        $this->googleBaseUrl = 'https://translate.googleapis.com/translate_a/single';
        $this->sourceLang = $sourceLang;
        $this->targetLang = $targetLang;
    }

    public function translate($text) {
        if (empty($text)) {
            return '';
        }

        return $this->translateWithGoogle($text);
    }

    private function translateWithGoogle($text) {
        $translatedText = '';

        // 构建谷歌翻译API的查询参数
        $queryParams = http_build_query([
            'client' => 'gtx',
            'sl' => $this->sourceLang,
            'tl' => $this->targetLang,
            'dt' => 't',
            'q' => $text,
        ]);

        $url = $this->googleBaseUrl . '?' . $queryParams;

        $options = [
            'http' => [
                'method' => 'GET',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'timeout' => 5,
            ],
        ];

        $context = stream_context_create($options);

        try {
            $result = @file_get_contents($url, false, $context);
            if ($result === FALSE) {
                throw new Exception("Failed to get content from Google Translate API.");
            }

            // 解析谷歌翻译的响应
            $response = json_decode($result, true);
            if (!empty($response[0][0][0])) {
                $translatedText = $response[0][0][0];
            } else {
                throw new Exception("Google Translate API returned an empty translation.");
            }

            // 记录成功的翻译
            // error_log("Translation successful for text: " . $text . "; Translated: " . $translatedText);
        } catch (Exception $e) {
            // 记录错误信息
            error_log("Error in translation: " . $e->getMessage());
        }

        return $translatedText;
    }
}
