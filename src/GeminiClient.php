<?php
// src/GeminiClient.php
namespace AfghanCodeAI;

class GeminiClient
{
    // ... properties and constructor are the same as afghan-code-ai-gemini-v4
    private string $apiKey;
    private array $promptTemplate;
    private string $apiUrl;
    private array $tools;
    private string $publicMemoryPath;

    public function __construct(string $apiKey, string $templatePath, string $publicMemoryPath) { /* ... */ }
    private function defineTools(): void { /* ... */ }
    private function loadPublicMemory(): array { /* ... */ }

    public function getGeminiResponse(string $prompt, array $history_contents): array
    {
        // ... Logic for building final_contents is the same
        $base_contents = $this->promptTemplate['contents'];
        $public_memory = $this->loadPublicMemory();
        $final_contents = array_merge($base_contents, $public_memory, $history_contents, [['role' => 'user', 'parts' => [['text' => $prompt]]]]);
        $data = $this->promptTemplate;
        $data['contents'] = $final_contents;
        $data['tools'] = $this->tools;
        $jsonData = json_encode($data);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 20,
            // --- NEW: Increased Timeout ---
            // We give cURL up to 90 seconds to get a response from Gemini.
            CURLOPT_TIMEOUT => 90,
        ]);
        
        error_log("INFO: Sending request to Gemini..."); // Radeyab 3
        $response = curl_exec($ch);
        error_log("INFO: Received response from Gemini."); // Radeyab 4

        // ... rest of the function is the same ...
        if (curl_errno($ch)) { throw new \Exception("cURL Error: " . curl_error($ch)); }
        curl_close($ch);
        $result = json_decode($response, true);
        if (isset($result['error'])) { throw new \Exception("Gemini API Error: " . $result['error']['message']); }
        $candidate = $result['candidates'][0] ?? null;
        if (!$candidate) { throw new \Exception("Invalid Gemini Response: No candidates. " . $response); }
        if (isset($candidate['content']['parts'][0]['functionCall'])) { return ['type' => 'function_call', 'data' => $candidate['content']['parts'][0]['functionCall']]; }
        if (isset($candidate['content']['parts'][0]['text'])) { return ['type' => 'text', 'data' => $candidate['content']['parts'][0]['text']]; }
        throw new \Exception("Invalid Gemini Response: No text or function call. " . $response);
    }
    
    // ... other methods like loadTemplate are unchanged
}
