# Community for Statamic

Curated rooms, posts, comments and reactions with a chronological feed. Posting as a team uses `goldnead/statamic-teams`. No routes and no UI: a site builds those on the services.

## Model

- `Room`: `region`, `style` or `topic`, curated flag, `archived_at`. `RoomMembership` per account.
- `Post`: author (account, optionally as a team), category, room, region, title, sanitised HTML body, `status` (`published`, `pending`), `hidden`.
- `Comment`: one level of replies, sanitised HTML body, `hidden`.
- `Reaction`: one per account and target (post or comment); reacting again changes the kind.

## Rules

- Curated rooms live in `community.rooms`. `RoomSeeder` (or `RoomService::syncCurated()`) creates them by slug, idempotent.
- Posting, commenting and reacting need a confirmed account (`email_verified_at`, or `accounts.verification.field`). `community.require_confirmed` turns this off.
- As a team: only with the team permission `post as team` (owner has it).
- Edit: author only. Delete: author, or a moderator (`moderate community`). Moderators hide and restore with `PostService::hide()`.
- Pre-moderation (`community.pre_moderation`, default off): a new post is `pending` until `PostService::approve()`. Moderators and accounts with `trusted_after` published posts skip the queue. Off means nobody on a fresh install is silenced; on means the first post of a newcomer needs a moderator.
- `PostService::feed($room, $region)` returns a query ordered by `published_at`, then id. Reactions never change the order. Pending and hidden posts are not in it.
- Bodies keep only `community.allowed_tags` and `allowed_attributes`: no scripts, no event handlers, no `javascript:` links.
- `PostCreated` fires when a post becomes public (creation, or approval), `CommentCreated` per comment, both after commit.

## Personal data

With `goldnead/statamic-accounts`, `CommunityContributor` is tagged for export and erasure. Erasing an account deletes its posts, comments, reactions and memberships, plus comments on its posts, replies to its comments and the reactions to them.

## Tests

```bash
composer install
vendor/bin/phpunit
vendor/bin/pint --test
```
