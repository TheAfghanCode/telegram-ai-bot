<?php
// src/GeminiClient.php
namespace AfghanCodeAI;

/**
 * =================================================================
 * AfghanCodeAI - Gemini AI Client (Final Corrected Version)
 * =================================================================
 * This class is the sole point of contact with the Google Gemini API.
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
        $this->apiUrl = '[https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=](https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=)' . $this->apiKey;
        $this->loadTemplate($templatePath);
        $this->defineTools();
    }

    private function defineTools(): void
    {
        // This defines the function calling tools as a complete, separate tool object
        $this->tools = [
            [
                'functionDeclarations' => [
                    [
                        'name' => 'send_private_message',
                        'description' => 'Sends a private message to another user.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'user_id_to_send' => ['type' => 'NUMBER'],
                                'message_text' => ['type' => 'STRING']
                            ],
                            'required' => ['user_id_to_send', 'message_text']
                        ]
                    ],
                    [
                        'name' => 'delete_chat_history',
                        'description' => 'Deletes the current conversation history.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => new \stdClass() // Represents an empty object {}
                        ]
                    ]
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

        // --- CORRECTED TOOL MERGING LOGIC ---
        // 1. Get the tools from the template (e.g., Google Search). This is already an array of tools.
        $templateTools = $this->promptTemplate['tools'] ?? [];

        // 2. Get the tools defined in PHP (e.g., Function Calling). This is also an array of tools.
        $definedTools = $this->tools ?? [];

        // 3. Merge the two arrays of tools into one final array of tool objects.
        // This correctly creates a list like [ {googleSearchRetrieval}, {functionDeclarations} ]
        $allTools = array_merge($templateTools, $definedTools);

        // 4. Assign the final array to the payload if it's not empty.
        if (!empty($allTools)) {
            $data['tools'] = $allTools;
        } else {
            // If no tools are defined anywhere, remove the key to avoid errors.
            unset($data['tools']);
        }
        // --- END OF CORRECTION ---

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

        $response = curl_exec($ch);

        if (curl_errno($ch)) { 
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("cURL Error: " . $error); 
        }
        curl_close($ch);

        $result = json_decode($response, true);
        if (isset($result['error'])) { 
            // Log the detailed error for better debugging
            $errorMessage = "Gemini API Error: " . ($result['error']['message'] ?? 'Unknown Error');
            error_log("FATAL Gemini Error Payload: " . $jsonData);
            error_log("FATAL Gemini Error Response: " . $response);
            throw new \Exception($errorMessage); 
        }

        $candidate = $result['candidates'][0] ?? null;
        if (!$candidate) { 
            throw new \Exception("Invalid Gemini Response: No candidates. Full Response: " . $response); 
        }

        if (isset($candidate['content']['parts'][0]['functionCall'])) { 
            return ['type' => 'function_call', 'data' => $candidate['content']['parts'][0]['functionCall']]; 
        }

        if (isset($candidate['content']['parts'][0]['text'])) { 
            return ['type' => 'text', 'data' => $candidate['content']['parts'][0]['text']]; 
        }

        // Handle cases where the response is valid but empty (e.g., safety block)
        if (isset($candidate['finishReason']) && $candidate['finishReason'] !== 'STOP') {
             throw new \Exception("Gemini Response Finished Unusually. Reason: " . $candidate['finishReason'] . ". Full Response: " . $response);
        }

        throw new \Exception("Invalid Gemini Response: No text or function call. Full Response: " . $response);
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
