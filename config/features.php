<?php

return [

    'plans' => [

        'free' => [
            'cases' => 3,
            'ai_messages_daily' => 20,
            'document_uploads_monthly' => 10,
            'document_analysis_monthly' => 5,
            'storage_mb' => 100,
            'template_generations_monthly' => 5,
            'features' => [
                'templates' => false,
                'reminders' => false,
                'exports' => false,
                'teams' => false,
                'knowledge_base_management' => false,
                'advanced_ai' => false,
            ],
        ],

        'premium' => [
            'cases' => null,
            'ai_messages_daily' => 200,
            'document_uploads_monthly' => 100,
            'document_analysis_monthly' => 50,
            'storage_mb' => 2048,
            'template_generations_monthly' => 100,
            'features' => [
                'templates' => true,
                'reminders' => true,
                'exports' => true,
                'teams' => false,
                'knowledge_base_management' => false,
                'advanced_ai' => true,
            ],
        ],

        'enterprise' => [
            'cases' => null,
            'ai_messages_daily' => null,
            'document_uploads_monthly' => null,
            'document_analysis_monthly' => null,
            'storage_mb' => null,
            'template_generations_monthly' => null,
            'features' => [
                'templates' => true,
                'reminders' => true,
                'exports' => true,
                'teams' => true,
                'knowledge_base_management' => true,
                'advanced_ai' => true,
            ],
        ],
    ],

    'gates' => [
        'cases' => 'cases',
        'ai_messages' => 'ai_messages_daily',
        'document_uploads' => 'document_uploads_monthly',
        'document_analysis' => 'document_analysis_monthly',
        'storage' => 'storage_mb',
        'templates' => 'templates',
        'reminders' => 'reminders',
        'exports' => 'exports',
        'teams' => 'teams',
        'knowledge_base_management' => 'knowledge_base_management',
        'advanced_ai' => 'advanced_ai',
    ],

];
