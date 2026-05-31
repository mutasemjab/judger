<?php

namespace App\Enums;

enum NotificationType: string
{
    case HearingReminder = 'hearing_reminder';
    case AnalysisReady = 'analysis_ready';
    case SubscriptionAlert = 'subscription_alert';
    case AiSuggestion = 'ai_suggestion';
    case Deadline = 'deadline';
    case TeamMention = 'team_mention';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
