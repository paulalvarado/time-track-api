<?php

namespace App\Controllers;

use App\Models\OdooConfigModel;
use App\Services\GeminiService;

class AiController extends BaseController
{
    public function transcribeTimesheet()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        $apiKey = $config->geminiApiKey ?? env('GEMINI_API_KEY', '');

        if (empty($apiKey)) {
            return $this->respondError('Gemini API key not configured. Set it in Settings.', 503);
        }

        $data = $this->getJsonInput();
        $audio = $data['audio'] ?? '';
        $mimeType = $data['mimeType'] ?? 'audio/webm';

        if (!$audio) {
            return $this->respondError('No audio data provided');
        }

        $audioBuffer = base64_decode($audio);
        if (strlen($audioBuffer) > 10 * 1024 * 1024) {
            return $this->respondError('Audio too large. Maximum 10MB.', 413);
        }

        try {
            $entries = GeminiService::transcribeTimesheetAudio($audio, $mimeType, $apiKey);
            return $this->respondSuccess(['entries' => $entries]);
        } catch (\Exception $e) {
            return $this->respondError($e->getMessage(), 502);
        }
    }

    public function transcribeTask()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        $apiKey = $config->geminiApiKey ?? env('GEMINI_API_KEY', '');

        if (empty($apiKey)) {
            return $this->respondError('Gemini API key not configured. Set it in Settings.', 503);
        }

        $data = $this->getJsonInput();
        $audio = $data['audio'] ?? '';
        $mimeType = $data['mimeType'] ?? 'audio/webm';
        $stageOptions = $data['stageOptions'] ?? [];

        if (!$audio) {
            return $this->respondError('No audio data provided');
        }

        $audioBuffer = base64_decode($audio);
        if (strlen($audioBuffer) > 10 * 1024 * 1024) {
            return $this->respondError('Audio too large. Maximum 10MB.', 413);
        }

        try {
            $tasks = GeminiService::transcribeTaskAudio($audio, $mimeType, $apiKey, $stageOptions);
            return $this->respondSuccess(['tasks' => $tasks]);
        } catch (\Exception $e) {
            return $this->respondError($e->getMessage(), 502);
        }
    }
}
