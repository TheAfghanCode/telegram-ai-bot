<?php
// src/GeminiClient.php
namespace AfghanCodeAI;

class GeminiClient
{
    private string $apiKey;
    private array $promptTemplate;
    private string $apiUrl;
    private array $tools;
    private string $publicMemoryPath; // NEW

    public function __construct(string $apiKey, string $templatePath, string $publicMemoryPath)
    {
        $this->apiKey = $apiKey;
        $this->apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $this->apiKey;
        $this->publicMemoryPath = $publicMemoryPath; // NEW
        $this->loadTemplate($templatePath);
        $this->defineTools();
    }
    
    // defineTools() method remains unchanged from afghan-code-ai-gemini-v3
    private function defineTools(): void
    {
        $this->tools = [
            [
                'functionDeclarations' => [
                    ['name' => 'send_private_message', 'description' => 'Sends a private message.', 'parameters' => ['type' => 'OBJECT','properties' => ['user_id_to_send' => ['type' => 'NUMBER'],'message_text' => ['type' => 'STRING']],'required' => ['user_id_to_send', 'message_text']]],
                    ['name' => 'delete_chat_history', 'description' => 'Deletes the conversation history.','parameters' => ['type' => 'OBJECT', 'properties' => new \stdClass()]]
                ]
            ]
        ];
    }
    
    /**
     * NEW: Loads global instructions from the public memory file.
     */
    private function loadPublicMemory(): array
    {
        if (!file_exists($this->publicMemoryPath)) {
            return [];
        }
        $publicInstructions = [];
        $lines = file($this->publicMemoryPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Each line is a global rule from the admin
            $publicInstructions[] = ['role' => 'user', 'parts' => [['text' => "قانون عمومی و همیشگی: " . $line]]];
            // We add a model part to confirm the rule is understood by the AI
            $publicInstructions[] = ['role' => 'model', 'parts' => [['text' => "قانون عمومی دریافت شد و اجرا می‌شود."]]];
        }
        return $publicInstructions;
    }

    public function getGeminiResponse(string $prompt, array $history_contents): array
    {
        // 1. Start with the base system prompt
        $base_contents = $this->promptTemplate['contents'];
        
        // 2. Load and inject global instructions
        $public_memory = $this->loadPublicMemory();

        // 3. Build the final context in the correct order
        $final_contents = array_merge(
            $base_contents,
            $public_memory,
            $history_contents,
            [['role' => 'user', 'parts' => [['text' => $prompt]]]]
        );
        
        $data = $this->promptTemplate; // Keep other settings like generationConfig
        $data['contents'] = $final_contents;
        $data['tools'] = $this->tools;
        
        $jsonData = json_encode($data);

        // cURL logic remains the same...
        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 20, CURLOPT_TIMEOUT => 45,
        ]);
        $response = curl_exec($ch);
        // ... error handling and response parsing logic remains the same
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
    
    private function loadTemplate(string $templatePath): void
    {
        if (!file_exists($templatePath)) throw new \Exception("Prompt template file not found: " . $templatePath);
        $this->promptTemplate = json_decode(file_get_contents($templatePath), true);
        if (json_last_error() !== JSON_ERROR_NONE) throw new \Exception("Invalid JSON in prompt template: " . json_last_error_msg());
    }
}
