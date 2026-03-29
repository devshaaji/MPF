<?php

declare(strict_types=1);

/**
 * API Route Stubs — Village Community Digital Platform
 *
 * This file declares all module route constants and stub comments.
 * Actual handler registration will occur in the framework bootstrap layer.
 *
 * Convention: METHOD /api/v1/{path}  →  Module\HandlerClass
 *
 * Event consumers declared per module:
 *   Auth        ← users.user_deactivated
 *   Forum       ← users.user_deactivated
 *   Projects    ← users.user_deactivated
 *   News        ← users.user_deactivated
 *   DigitalID   ← users.user_registered, users.user_deactivated
 *   Notifications ← users.user_deactivated, news.published,
 *                   scholarships.application_awarded, digital_id.generated
 */

// ---------------------------------------------------------------------------
// AUTH MODULE (AUTH-001)
// ---------------------------------------------------------------------------
// POST   /api/v1/auth/google/verify          → Auth\VerifyGoogleTokenHandler
// POST   /api/v1/auth/logout                 → Auth\LogoutHandler

// ---------------------------------------------------------------------------
// USERS MODULE (USERS-001)
// ---------------------------------------------------------------------------
// GET    /api/v1/users/{id}                  → Users\GetUserHandler
// PATCH  /api/v1/users/{id}                  → Users\UpdateUserHandler
// GET    /api/v1/leaderboard                 → Users\GetLeaderboardHandler

// ---------------------------------------------------------------------------
// FORMS MODULE (FORMS-001)
// ---------------------------------------------------------------------------
// POST   /api/v1/forms                       → Forms\CreateFormHandler
// GET    /api/v1/forms/{id}                  → Forms\GetFormHandler
// POST   /api/v1/forms/{id}/submissions      → Forms\SubmitFormHandler
// GET    /api/v1/forms/{id}/submissions      → Forms\ListSubmissionsHandler

// ---------------------------------------------------------------------------
// ADS MODULE (ADS-001)
// ---------------------------------------------------------------------------
// POST   /api/v1/ads/campaigns               → Ads\CreateCampaignHandler
// GET    /api/v1/ads/campaigns/{id}          → Ads\GetCampaignHandler
// GET    /api/v1/ads/placement               → Ads\GetPlacementHandler
// POST   /api/v1/ads/impressions             → Ads\RecordImpressionHandler
// POST   /api/v1/ads/clicks                  → Ads\RecordClickHandler

// ---------------------------------------------------------------------------
// FORUM MODULE (FORUM-001)
// Depends on: USERS-001
// Consumes:   users.user_deactivated
// ---------------------------------------------------------------------------
// POST   /api/v1/forum/topics                → Forum\CreateTopicHandler
// GET    /api/v1/forum/topics                → Forum\ListTopicsHandler
// GET    /api/v1/forum/topics/{id}           → Forum\GetTopicHandler
// POST   /api/v1/forum/topics/{id}/replies   → Forum\AddReplyHandler
// GET    /api/v1/forum/topics/{id}/replies   → Forum\ListRepliesHandler

// ---------------------------------------------------------------------------
// PROJECTS MODULE (PROJECTS-001)
// Depends on: USERS-001
// Consumes:   users.user_deactivated
// ---------------------------------------------------------------------------
// POST   /api/v1/projects                    → Projects\CreateProjectHandler
// GET    /api/v1/projects                    → Projects\ListProjectsHandler
// GET    /api/v1/projects/{id}               → Projects\GetProjectHandler
// POST   /api/v1/projects/{id}/publish       → Projects\PublishProjectHandler

// ---------------------------------------------------------------------------
// NEWS MODULE (NEWS-001)
// Depends on: USERS-001
// Consumes:   users.user_deactivated
// ---------------------------------------------------------------------------
// POST   /api/v1/news                        → News\CreateNewsHandler
// GET    /api/v1/news                        → News\ListNewsHandler
// GET    /api/v1/news/{id}                   → News\GetNewsHandler
// POST   /api/v1/news/{id}/publish           → News\PublishNewsHandler
// POST   /api/v1/news/{id}/comments          → News\AddCommentHandler
// GET    /api/v1/news/{id}/comments          → News\ListCommentsHandler

// ---------------------------------------------------------------------------
// DIGITAL ID MODULE (DIGITALID-001)
// Consumes: users.user_registered (trigger), users.user_deactivated
// ---------------------------------------------------------------------------
// POST   /api/v1/digital-id/generate         → DigitalID\GenerateDigitalIdHandler
// GET    /api/v1/digital-id/{user_id}        → DigitalID\GetDigitalIdHandler

// ---------------------------------------------------------------------------
// NOTIFICATIONS MODULE (NOTIFICATIONS-001)
// Depends on: INFRA-QUEUE-001, INFRA-EVENT-001, USERS-001
// Consumes:   users.user_deactivated, news.published,
//             scholarships.application_awarded, digital_id.generated
// ---------------------------------------------------------------------------
// GET    /api/v1/notifications/preferences   → Notifications\GetPreferencesHandler
// PUT    /api/v1/notifications/preferences   → Notifications\UpdatePreferencesHandler
// GET    /api/v1/notifications               → Notifications\ListNotificationsHandler
// POST   /api/v1/notifications/{id}/read     → Notifications\MarkReadHandler

// ---------------------------------------------------------------------------
// SCHOLARSHIPS MODULE (SCHOLARSHIPS-001)
// Depends on: FORMS-001, USERS-001, INFRA-DB-001, INFRA-EVENT-001
// Consumes:   forms.submission_received, users.user_deactivated
// Emits:      scholarships.application_submitted, scholarships.application_awarded
// ---------------------------------------------------------------------------
// POST   /api/v1/scholarships                          → Scholarships\CreateScholarshipHandler
// GET    /api/v1/scholarships                          → Scholarships\ListScholarshipsHandler
// GET    /api/v1/scholarships/{id}                     → Scholarships\GetScholarshipHandler
// POST   /api/v1/scholarships/{id}/apply               → Scholarships\ApplyScholarshipHandler
// GET    /api/v1/scholarships/applications/{id}        → Scholarships\GetApplicationHandler
// POST   /api/v1/scholarships/applications/{id}/award  → Scholarships\AwardScholarshipHandler

// ---------------------------------------------------------------------------
// ADMIN MODULE (ADMIN-001)
// Depends on: USERS-001, AUTH-001
// Required roles: admin | moderator (per route)
// ---------------------------------------------------------------------------
// GET    /api/v1/admin/dashboard                         → Admin\GetDashboardHandler          [role: admin]
// GET    /api/v1/admin/moderation                        → Admin\ListModerationCasesHandler   [role: admin|moderator]
// POST   /api/v1/admin/moderation/{id}/resolve           → Admin\ResolveModerationCaseHandler [role: admin|moderator]
// GET    /api/v1/admin/users                             → Admin\ListUsersHandler             [role: admin]
// POST   /api/v1/admin/users/{id}/deactivate             → Admin\DeactivateUserHandler        [role: admin]
