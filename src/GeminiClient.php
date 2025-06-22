<?php
// src/GeminiClient.php

namespace AfghanCodeAI;

class GeminiClient
{
    private string $apiKey;
    private array $promptTemplate;
    private string $apiUrl;

    public function __construct(string $apiKey, string $templatePath)
    {
        $this->apiKey = $apiKey;
        $this->apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $this->apiKey;
        $this->loadTemplate($templatePath);
    }

    /**
     * Loads the prompt template from a JSON file.
     */
    private function loadTemplate(string $templatePath): void
    {
        if (!file_exists($templatePath)) {
            throw new \Exception("Prompt template file not found at: $templatePath");
        }
        $template_json = file_get_contents($templatePath);
        if ($template_json === false) {
            throw new \Exception("Failed to read prompt_template.json file.");
        }
        $data = json_decode($template_json, true);
        if (!is_array($data) || !isset($data['contents'])) {
            throw new \Exception("Invalid JSON in prompt_template.json.");
        }
        $this->promptTemplate = $data;
    }

    /**
     * Gets a response from the Gemini API.
     */
    public function getGeminiResponse(string $prompt, array $history_contents): string
    {
        $data = $this->promptTemplate;
        // Construct the final payload by merging system prompts, history, and the new user message
        $final_contents = array_merge($data['contents'], $history_contents, [['role' => 'user', 'parts' => [['text' => $prompt]]]]);
        $data['contents'] = $final_contents;

        $jsonData = json_encode($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("cURL Error while calling Gemini API: " . $error);
        }
        curl_close($ch);

        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return $result['candidates'][0]['content']['parts'][0]['text'];
        }
        
        // Throw a more detailed error for better debugging if the response format is unexpected
        throw new \Exception("Invalid or empty response from Gemini API. Response: " . $response);
    }
}

