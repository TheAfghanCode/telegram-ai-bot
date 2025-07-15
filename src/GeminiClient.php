<?php
// src/GeminiClient.php
namespace AfghanCodeAI;

/**
 * =================================================================
 * AfghanCodeAI - Gemini AI Client
 * =================================================================
 * This class is the sole point of contact with the Google Gemini API.
 * It is now simpler as it no longer manages public memory itself.
 */
class GeminiClient
{
    private string $apiKey;
    private array $promptTemplate;
    private string $apiUrl;
    private array $tools;

    public function __construct(string $apiKey, string $templatePath)
    {
        $this->apiKey = $apiKey;
        // NOTE: You can dynamically change the model name here if needed
        $this->apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $this->apiKey;
        $this->loadTemplate($templatePath);
        $this->defineTools();
    }

    private function defineTools(): void
    {
        // This defines the function calling tools
        $this->tools = [
            [
                'functionDeclarations' => [
                    ['name' => 'send_private_message', 'description' => 'Sends a private message to another user.', 'parameters' => ['type' => 'OBJECT','properties' => ['user_id_to_send' => ['type' => 'NUMBER'],'message_text' => ['type' => 'STRING']],'required' => ['user_id_to_send', 'message_text']]],
                    ['name' => 'delete_chat_history', 'description' => 'Deletes the current conversation history.','parameters' => ['type' => 'OBJECT', 'properties' => new \stdClass()]]
                ]
            ]
        ];
    }

    public function getGeminiResponse(array $full_context): array
    {
        // Start with the base structure from the template file
        $data = $this->promptTemplate;
        
        // Overwrite the contents with the real, full conversation history
        $data['contents'] = $full_context;

        // --- THE FINAL FIX IS HERE ---
        // Get the content of the tool from the JSON template (e.g., google_search)
        $templateToolContent = $this->promptTemplate['tools'][0] ?? []; 
        // Get the content of the tool defined in PHP (e.g., function calling)
        $definedToolContent = $this->tools[0] ?? []; 

        // Merge the *contents* of both tools into a single, large tool object
        $mergedTool = array_merge($templateToolContent, $definedToolContent);

        // If the merged tool is not empty, wrap it in an array as the final toolset
        // This creates the correct structure: [ {tool1, tool2, ...} ]
        if (!empty($mergedTool)) {
            $data['tools'] = [$mergedTool];
        } else {
            // If no tools are defined anywhere, remove the key to avoid errors
            unset($data['tools']);
        }
        // --- END OF FINAL FIX ---

        $jsonData = json_encode($data);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 90,
        ]);

        error_log("INFO: Sending request to Gemini...");
        $response = curl_exec($ch);
        error_log("INFO: Received response from Gemini.");

        if (curl_errno($ch)) { 
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("cURL Error: " . $error); 
        }
        curl_close($ch);

        $result = json_decode($response, true);
        if (isset($result['error'])) { 
            throw new \Exception("Gemini API Error: " . $result['error']['message']); 
        }

        $candidate = $result['candidates'][0] ?? null;
        if (!$candidate) { 
            throw new \Exception("Invalid Gemini Response: No candidates. " . $response); 
        }

        if (isset($candidate['content']['parts'][0]['functionCall'])) { 
            return ['type' => 'function_call', 'data' => $candidate['content']['parts'][0]['functionCall']]; 
        }

        if (isset($candidate['content']['parts'][0]['text'])) { 
            return ['type' => 'text', 'data' => $candidate['content']['parts'][0]['text']]; 
        }

        throw new \Exception("Invalid Gemini Response: No text or function call. " . $response);
    }

    private function loadTemplate(string $templatePath): void
    {
        if (!file_exists($templatePath)) {
            throw new \Exception("Prompt template file not found: " . $templatePath);
        }
        $this->promptTemplate = json_decode(file_get_contents($templatePath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON in prompt template: " . json_last_error_msg());
        }
    }
}
