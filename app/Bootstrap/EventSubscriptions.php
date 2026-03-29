<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Contracts\Events\EventEnvelope;
use App\Contracts\Services\EventPublisherInterface;

/**
 * EventSubscriptions
 * Registers all cross-module event consumer bindings.
 *
 * IMPLEMENTATION BOUNDARY: subscription declarations only.
 * Do NOT add business logic here. Handler stubs delegate to module handlers.
 *
 * Authoritative routing reference: app/Contracts/Events/subscription_matrix.md
 */
final class EventSubscriptions
{
    public function __construct(
        private readonly EventPublisherInterface $publisher
    ) {}

    /**
     * Register all cross-module event consumer bindings.
     * Called once during kernel boot.
     */
    public function register(): void
    {
        // ------------------------------------------------------------------
        // users.user_registered → DigitalID (auto-generate digital identity)
        // ------------------------------------------------------------------
        $this->publisher->subscribe('users.user_registered', [self::class, 'onUserRegistered']);

        // ------------------------------------------------------------------
        // users.user_deactivated → Auth (invalidate sessions)
        // ------------------------------------------------------------------
        $this->publisher->subscribe('users.user_deactivated', [self::class, 'onUserDeactivatedAuth']);

        // ------------------------------------------------------------------
        // users.user_deactivated → Forum (flag authored content)
        // ------------------------------------------------------------------
        $this->publisher->subscribe('users.user_deactivated', [self::class, 'onUserDeactivatedForum']);

        // ------------------------------------------------------------------
        // users.user_deactivated → Projects (suspend owned projects)
        // ------------------------------------------------------------------
        $this->publisher->subscribe('users.user_deactivated', [self::class, 'onUserDeactivatedProjects']);

        // ------------------------------------------------------------------
        // users.user_deactivated → News (flag articles and comments)
        // ------------------------------------------------------------------
        $this->publisher->subscribe('users.user_deactivated', [self::class, 'onUserDeactivatedNews']);

        // ------------------------------------------------------------------
        // users.user_deactivated → Notifications (cancel pending notifications)
        // ------------------------------------------------------------------
        $this->publisher->subscribe('users.user_deactivated', [self::class, 'onUserDeactivatedNotifications']);

        // ------------------------------------------------------------------
        // users.user_deactivated → Admin (record in moderation audit log)
        // ------------------------------------------------------------------
        $this->publisher->subscribe('users.user_deactivated', [self::class, 'onUserDeactivatedAdmin']);

        // ------------------------------------------------------------------
        // users.points_awarded → Leaderboard (trigger rebuild / incremental update)
        // ------------------------------------------------------------------
        $this->publisher->subscribe('users.points_awarded', [self::class, 'onPointsAwarded']);

        // ------------------------------------------------------------------
        // forum.topic_created → Notifications (notify followers)
        // ------------------------------------------------------------------
        $this->publisher->subscribe('forum.topic_created', [self::class, 'onForumTopicCreated']);

        // ------------------------------------------------------------------
        // forum.reply_added → Notifications (notify topic author)
        // ------------------------------------------------------------------
        $this->publisher->subscribe('forum.reply_added', [self::class, 'onForumReplyAdded']);

        // ------------------------------------------------------------------
        // projects.project_published → Notifications (notify followers)
        // ------------------------------------------------------------------
        $this->publisher->subscribe('projects.project_published', [self::class, 'onProjectPublished']);

        // ------------------------------------------------------------------
        // news.published → Notifications (notify subscribers)
        // ------------------------------------------------------------------
        $this->publisher->subscribe('news.published', [self::class, 'onNewsPublished']);

        // ------------------------------------------------------------------
        // news.comment_added → Notifications (notify article author)
        // ------------------------------------------------------------------
        $this->publisher->subscribe('news.comment_added', [self::class, 'onNewsCommentAdded']);

        // ------------------------------------------------------------------
        // digital_id.generated → Notifications (notify user artifact is ready)
        // ------------------------------------------------------------------
        $this->publisher->subscribe('digital_id.generated', [self::class, 'onDigitalIdGenerated']);

        // ------------------------------------------------------------------
        // forms.submission_received → Scholarships (process as application)
        // ------------------------------------------------------------------
        $this->publisher->subscribe('forms.submission_received', [self::class, 'onFormSubmissionReceived']);

        // ------------------------------------------------------------------
        // scholarships.application_submitted → Notifications (confirm receipt)
        // ------------------------------------------------------------------
        $this->publisher->subscribe('scholarships.application_submitted', [self::class, 'onScholarshipApplicationSubmitted']);

        // ------------------------------------------------------------------
        // scholarships.application_awarded → Notifications (notify applicant)
        // ------------------------------------------------------------------
        $this->publisher->subscribe('scholarships.application_awarded', [self::class, 'onScholarshipApplicationAwarded']);

        // ------------------------------------------------------------------
        // admin.moderation_case_resolved → Notifications (notify affected parties)
        // ------------------------------------------------------------------
        $this->publisher->subscribe('admin.moderation_case_resolved', [self::class, 'onModerationCaseResolved']);

        // ------------------------------------------------------------------
        // leaderboard.rebuilt → cache invalidation (internal cache layer)
        // ------------------------------------------------------------------
        $this->publisher->subscribe('leaderboard.rebuilt', [self::class, 'onLeaderboardRebuilt']);
    }

    // ------------------------------------------------------------------
    // Stub handler methods — implementations live in module handlers.
    // Only declared payload fields (per subscription_matrix.md §3) are used.
    // ------------------------------------------------------------------

    /** Payload fields used: user_id, display_name */
    public static function onUserRegistered(EventEnvelope $envelope): void
    {
        // DigitalID module handler: auto-generate digital identity artifact
    }

    /** Payload fields used: user_id, reason */
    public static function onUserDeactivatedAuth(EventEnvelope $envelope): void
    {
        // Auth module handler: invalidate active sessions for payload.user_id
    }

    /** Payload fields used: user_id */
    public static function onUserDeactivatedForum(EventEnvelope $envelope): void
    {
        // Forum module handler: flag topics/replies by payload.user_id
    }

    /** Payload fields used: user_id */
    public static function onUserDeactivatedProjects(EventEnvelope $envelope): void
    {
        // Projects module handler: suspend projects owned by payload.user_id
    }

    /** Payload fields used: user_id */
    public static function onUserDeactivatedNews(EventEnvelope $envelope): void
    {
        // News module handler: flag articles/comments by payload.user_id
    }

    /** Payload fields used: user_id */
    public static function onUserDeactivatedNotifications(EventEnvelope $envelope): void
    {
        // Notifications module handler: cancel pending notifications for payload.user_id
    }

    /** Payload fields used: user_id, reason, deactivated_by */
    public static function onUserDeactivatedAdmin(EventEnvelope $envelope): void
    {
        // Admin module handler: record deactivation in moderation audit log
    }

    /** Payload fields used: user_id, points_delta, new_total */
    public static function onPointsAwarded(EventEnvelope $envelope): void
    {
        // Leaderboard handler: trigger incremental leaderboard read-model update
    }

    /** Payload fields used: topic_id, author_id, title */
    public static function onForumTopicCreated(EventEnvelope $envelope): void
    {
        // Notifications module handler: notify followers of new topic
    }

    /** Payload fields used: reply_id, topic_id, author_id */
    public static function onForumReplyAdded(EventEnvelope $envelope): void
    {
        // Notifications module handler: notify topic author of new reply
    }

    /** Payload fields used: project_id, owner_id, title */
    public static function onProjectPublished(EventEnvelope $envelope): void
    {
        // Notifications module handler: notify followers of published project
    }

    /** Payload fields used: news_id, author_id, title */
    public static function onNewsPublished(EventEnvelope $envelope): void
    {
        // Notifications module handler: notify subscribers of new article
    }

    /** Payload fields used: comment_id, news_id, commenter_id */
    public static function onNewsCommentAdded(EventEnvelope $envelope): void
    {
        // Notifications module handler: notify article author of new comment
    }

    /** Payload fields used: user_id, digital_id */
    public static function onDigitalIdGenerated(EventEnvelope $envelope): void
    {
        // Notifications module handler: notify user that digital ID is ready
    }

    /** Payload fields used: submission_id, form_id, submitter_id */
    public static function onFormSubmissionReceived(EventEnvelope $envelope): void
    {
        // Scholarships module handler: process form submission as application
    }

    /** Payload fields used: application_id, applicant_id */
    public static function onScholarshipApplicationSubmitted(EventEnvelope $envelope): void
    {
        // Notifications module handler: confirm application receipt to applicant
    }

    /** Payload fields used: application_id, applicant_id, award_amount */
    public static function onScholarshipApplicationAwarded(EventEnvelope $envelope): void
    {
        // Notifications module handler: notify applicant of scholarship award
    }

    /** Payload fields used: case_id, resolution, target_type, target_id */
    public static function onModerationCaseResolved(EventEnvelope $envelope): void
    {
        // Notifications module handler: notify affected parties of moderation outcome
    }

    /** Payload fields used: rebuilt_at, entry_count */
    public static function onLeaderboardRebuilt(EventEnvelope $envelope): void
    {
        // Cache layer handler: invalidate leaderboard read-model cache
    }
}
