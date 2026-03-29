# Event Subscription Matrix

> **INTEGRATION-001 / INTEGRATION-002** — Authoritative cross-module event routing reference.
> All consumer bindings must only reference declared payload fields from the corresponding event schema.

---

## Section 1: Event → Consumers

| Event | Producer Module | Consumers |
|-------|----------------|-----------|
| `auth.user_authenticated` | Auth | [none — terminal] |
| `auth.user_logged_out` | Auth | [none — terminal] |
| `users.user_registered` | Users | DigitalID |
| `users.user_deactivated` | Users | Auth, Forum, Projects, News, Notifications, Admin |
| `users.points_awarded` | Users | Leaderboard (INTEGRATION-002) |
| `forum.topic_created` | Forum | Notifications |
| `forum.reply_added` | Forum | Notifications |
| `projects.project_published` | Projects | Notifications |
| `news.published` | News | Notifications |
| `news.comment_added` | News | Notifications |
| `digital_id.generated` | DigitalID | Notifications |
| `forms.submission_received` | Forms | Scholarships |
| `scholarships.application_submitted` | Scholarships | Notifications |
| `scholarships.application_awarded` | Scholarships | Notifications |
| `ads.impression_recorded` | Ads | [analytics — internal] |
| `ads.click_recorded` | Ads | [analytics — internal] |
| `notifications.message_queued` | Notifications | [queue worker — internal] |
| `notifications.message_delivered` | Notifications | [audit log] |
| `admin.moderation_case_resolved` | Admin | Notifications |
| `leaderboard.rebuilt` | Integration | [cache invalidation] |

---

## Section 2: Consumer → Source Event

| Consumer Module | Source Event | Purpose |
|----------------|--------------|---------|
| DigitalID | `users.user_registered` | Auto-generate digital identity artifact on new registration |
| Auth | `users.user_deactivated` | Invalidate active sessions for the deactivated user |
| Forum | `users.user_deactivated` | Mark user posts/topics as from a deactivated account |
| Projects | `users.user_deactivated` | Suspend user's published projects |
| News | `users.user_deactivated` | Hide or flag news articles/comments by deactivated user |
| Notifications | `users.user_deactivated` | Cancel any pending notifications for the deactivated user |
| Admin | `users.user_deactivated` | Record deactivation in moderation audit log |
| Leaderboard | `users.points_awarded` | Trigger incremental leaderboard rebuild |
| Notifications | `forum.topic_created` | Notify followers of new topic |
| Notifications | `forum.reply_added` | Notify topic author of new reply |
| Notifications | `projects.project_published` | Notify followers of newly published project |
| Notifications | `news.published` | Notify subscribers of new news article |
| Notifications | `news.comment_added` | Notify news author of new comment |
| Notifications | `digital_id.generated` | Notify user that digital ID is ready |
| Scholarships | `forms.submission_received` | Process form submission as a scholarship application |
| Notifications | `scholarships.application_submitted` | Confirm application receipt to applicant |
| Notifications | `scholarships.application_awarded` | Notify applicant of scholarship award |
| Notifications | `admin.moderation_case_resolved` | Notify affected parties of moderation outcome |
| Cache layer | `leaderboard.rebuilt` | Invalidate leaderboard read-model cache |

---

## Section 3: Schema Binding Declarations

Each consumer may only access the fields declared here; no ad-hoc field access is permitted.

### `users.user_registered` → DigitalID
| Field path | Type | Usage |
|------------|------|-------|
| `payload.user_id` | uuid | Subject of the digital identity artifact |
| `payload.display_name` | string | Embedded in artifact metadata |

### `users.user_deactivated` → Auth
| Field path | Type | Usage |
|------------|------|-------|
| `payload.user_id` | uuid | Look up and revoke sessions for this user |
| `payload.reason` | string | Log entry context |

### `users.user_deactivated` → Forum
| Field path | Type | Usage |
|------------|------|-------|
| `payload.user_id` | uuid | Flag topics/replies authored by this user |

### `users.user_deactivated` → Projects
| Field path | Type | Usage |
|------------|------|-------|
| `payload.user_id` | uuid | Suspend projects owned by this user |

### `users.user_deactivated` → News
| Field path | Type | Usage |
|------------|------|-------|
| `payload.user_id` | uuid | Flag articles/comments by this user |

### `users.user_deactivated` → Notifications
| Field path | Type | Usage |
|------------|------|-------|
| `payload.user_id` | uuid | Cancel pending notifications for this user |

### `users.user_deactivated` → Admin
| Field path | Type | Usage |
|------------|------|-------|
| `payload.user_id` | uuid | Subject of the audit log entry |
| `payload.reason` | string | Audit log detail |
| `payload.deactivated_by` | uuid | Actor recorded in audit log |

### `users.points_awarded` → Leaderboard
| Field path | Type | Usage |
|------------|------|-------|
| `payload.user_id` | uuid | User whose rank may have changed |
| `payload.points_delta` | integer | Points delta to apply to read model |
| `payload.new_total` | integer | Updated total for the user in the read model |

### `forum.topic_created` → Notifications
| Field path | Type | Usage |
|------------|------|-------|
| `payload.topic_id` | uuid | Reference for notification deep-link |
| `payload.author_id` | uuid | Actor for notification context |
| `payload.title` | string | Preview text in notification |

### `forum.reply_added` → Notifications
| Field path | Type | Usage |
|------------|------|-------|
| `payload.reply_id` | uuid | Reference for notification deep-link |
| `payload.topic_id` | uuid | Parent topic reference |
| `payload.author_id` | uuid | Reply author for notification context |

### `projects.project_published` → Notifications
| Field path | Type | Usage |
|------------|------|-------|
| `payload.project_id` | uuid | Reference for notification deep-link |
| `payload.owner_id` | uuid | Project owner for notification context |
| `payload.title` | string | Preview text in notification |

### `news.published` → Notifications
| Field path | Type | Usage |
|------------|------|-------|
| `payload.news_id` | uuid | Reference for notification deep-link |
| `payload.author_id` | uuid | Author for notification context |
| `payload.title` | string | Preview text in notification |

### `news.comment_added` → Notifications
| Field path | Type | Usage |
|------------|------|-------|
| `payload.comment_id` | uuid | Reference for notification deep-link |
| `payload.news_id` | uuid | Parent article reference |
| `payload.commenter_id` | uuid | Commenter for notification context |

### `digital_id.generated` → Notifications
| Field path | Type | Usage |
|------------|------|-------|
| `payload.user_id` | uuid | Recipient of the notification |
| `payload.digital_id` | uuid | Artifact identifier included in notification |

### `forms.submission_received` → Scholarships
| Field path | Type | Usage |
|------------|------|-------|
| `payload.submission_id` | uuid | Linked form submission for the application |
| `payload.form_id` | uuid | Form type validation |
| `payload.submitter_id` | uuid | Applicant identity |

### `scholarships.application_submitted` → Notifications
| Field path | Type | Usage |
|------------|------|-------|
| `payload.application_id` | uuid | Reference for notification deep-link |
| `payload.applicant_id` | uuid | Recipient of the confirmation notification |

### `scholarships.application_awarded` → Notifications
| Field path | Type | Usage |
|------------|------|-------|
| `payload.application_id` | uuid | Reference for notification deep-link |
| `payload.applicant_id` | uuid | Recipient of the award notification |
| `payload.award_amount` | number | Award detail in notification body |

### `admin.moderation_case_resolved` → Notifications
| Field path | Type | Usage |
|------------|------|-------|
| `payload.case_id` | uuid | Reference for notification deep-link |
| `payload.resolution` | string | Outcome description in notification |
| `payload.target_type` | string | Identifies the moderated entity type |
| `payload.target_id` | uuid | Identifies the moderated entity |

---

## Section 4: Integration Validation Checklist

- [ ] Every event in Section 1 has a corresponding `.json` schema file in `app/Contracts/Events/`
- [ ] Every consumer in Section 2 only binds to fields listed in Section 3
- [ ] No consumer accesses a field absent from the corresponding event schema
- [ ] No consumer reads from another module's database tables or repositories
- [ ] All subscription handler stubs are declared in `app/Bootstrap/EventSubscriptions.php`
- [ ] `EventSubscriptions::register()` is invoked during kernel boot (see `app/Core/Application/Kernel.php`)
- [ ] `leaderboard.rebuilt` event schema exists at `app/Contracts/Events/leaderboard.rebuilt.json`
- [ ] `LeaderboardQueryInterface` exists at `app/Contracts/Services/LeaderboardQueryInterface.php`
- [ ] No duplicate leaderboard API contract (MCL-01) — API contract owned by Users module at `app/Contracts/Api/Users/get_leaderboard.json`

---

## Section 5: Leaderboard Aggregation Contract

> **INTEGRATION-002** — Leaderboard read-model integration.

### Trigger Events

The leaderboard read model must be rebuilt (or incrementally updated) when any of the following events occur:

| Source Event | Trigger Condition |
|-------------|-------------------|
| `users.points_awarded` | Primary trigger — any point award/deduction |
| `forum.topic_created` | If platform awards points for creating topics |
| `forum.reply_added` | If platform awards points for replies |
| `projects.project_published` | If platform awards points for published projects |

> **Note:** `users.points_awarded` is the canonical trigger. Events from Forum and Projects may themselves cause `users.points_awarded` to be emitted; the leaderboard consumer binds to `users.points_awarded` only, not to the upstream trigger events directly.

### Tie-break Policy

When two users have equal `total_points`, the user with the **earlier `first_points_earned_at`** timestamp wins (lower rank number = better rank). This is the canonical tie-break rule for all leaderboard queries.

### Cache Invalidation

- `leaderboard.rebuilt` event → signals all cache layers to invalidate the leaderboard read-model cache
- Cache consumers bind only to `payload.rebuilt_at` and `payload.entry_count`
- No consumer modifies the leaderboard data directly in response to this event

### API Reference (MCL-01 — No Duplication)

The leaderboard API contract is **owned exclusively by the Users module**:

```
app/Contracts/Api/Users/get_leaderboard.json
```

This Integration layer does **not** duplicate or re-define the API contract. All API consumers must reference the Users module contract directly.

### Interface Reference

Query interface: `app/Contracts/Services/LeaderboardQueryInterface.php`
- `getLeaderboard(int $limit, int $offset): LeaderboardEntryDto[]`
- `getUserRank(string $userId): ?int`
- `rebuild(): void`
