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
        // 构建谷歌翻译API的查询参数
        $queryParams = http_build_query([
            'client' => 'dict-chrome-ex',
            'sl' => $this->sourceLang,
            'tl' => $this->targetLang,
            'dt' => 't',
            'q' => $text,
        ]);

        $url = $this->googleBaseUrl . '?' . $queryParams;

        $options = [
            'http' => [
                'method' => 'GET',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                    "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36\r\n",
                'timeout' => 10,
            ],
        ];

        $context = stream_context_create($options);

        try {
            $result = @file_get_contents($url, false, $context);
            if ($result === FALSE) {
                $lastError = error_get_last();
                $message = $lastError['message'] ?? "Failed to get content from Google Translate API.";
                throw new RuntimeException($message);
            }

            // 解析谷歌翻译的响应
            $response = json_decode($result, true);
            if (!empty($response[0][0][0])) {
                return $response[0][0][0];
            }

            throw new RuntimeException("Google Translate API returned an empty translation.");
        } catch (Throwable $e) {
            error_log("Error in translation: " . $e->getMessage());
            throw $e;
        }
    }
}
