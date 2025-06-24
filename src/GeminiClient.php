<?php
// src/GeminiClient.php

namespace AfghanCodeAI;

class GeminiClient
{
    private string $apiKey;
    private array $promptTemplate;
    private string $apiUrl;
    private array $tools;

    public function __construct(string $apiKey, string $templatePath)
    {
        $this->apiKey = $apiKey;
        $this->apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $this->apiKey;
        $this->loadTemplate($templatePath);
        $this->defineTools();
    }
    
    /**
     * Defines the functions (tools) available for the AI to call.
     */
    private function defineTools(): void
    {
        $this->tools = [
            [
                'functionDeclarations' => [
                    [
                        'name' => 'send_private_message',
                        'description' => 'Sends a direct private message to a specified user on Telegram.',
                        'parameters' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'user_id_to_send' => [
                                    'type' => 'NUMBER',
                                    'description' => 'The numeric Telegram user ID to send the message to.'
                                ],
                                'message_text' => [
                                    'type' => 'STRING',
                                    'description' => 'The content of the message to be sent.'
                                ]
                            ],
                            'required' => ['user_id_to_send', 'message_text']
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Gets a structured response (text or function call) from the Gemini API.
     * @return array ['type' => 'text'|'function_call', 'data' => string|array]
     */
    public function getGeminiResponse(string $prompt, array $history_contents): array
    {
        $data = $this->promptTemplate;
        $final_contents = array_merge($data['contents'], $history_contents, [['role' => 'user', 'parts' => [['text' => $prompt]]]]);
        $data['contents'] = $final_contents;

        // Add the defined tools to the API request
        $data['tools'] = $this->tools;

        $jsonData = json_encode($data);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 40,
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("cURL Error while calling Gemini API: " . $error);
        }
        curl_close($ch);

        $result = json_decode($response, true);
        $candidate = $result['candidates'][0] ?? null;

        if (!$candidate) {
            throw new \Exception("Invalid Gemini Response: No candidates found. " . $response);
        }

        // Check if the AI requested a function call
        if (isset($candidate['content']['parts'][0]['functionCall'])) {
            $functionCall = $candidate['content']['parts'][0]['functionCall'];
            error_log("INFO: Gemini requested function call: " . $functionCall['name']);
            return [
                'type' => 'function_call',
                'data' => [
                    'name' => $functionCall['name'],
                    'args' => $functionCall['args'] ?? []
                ]
            ];
        }

        // Default to a text response
        if (isset($candidate['content']['parts'][0]['text'])) {
            return [
                'type' => 'text',
                'data' => $candidate['content']['parts'][0]['text']
            ];
        }

        throw new \Exception("Invalid Gemini Response: No text or function call found. " . $response);
    }
    
    private function loadTemplate(string $templatePath): void
    {
        // ... (this method remains unchanged)
        if (!file_exists($templatePath)) throw new \Exception("Prompt template file not found");
        $this->promptTemplate = json_decode(file_get_contents($templatePath), true);
    }
}
