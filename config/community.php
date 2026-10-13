<?php

return [
    /*
     * Pre-moderation: a new post stays invisible until a moderator approves
     * it. Off by default, so nobody on a fresh install is silenced. When on,
     * moderators (`moderate community`) always publish directly, and so do
     * accounts with at least `trusted_after` published posts.
     */
    'pre_moderation' => false,
    'trusted_after' => 1,

    // Only confirmed accounts may post, comment and react.
    'require_confirmed' => true,

    // User field that marks a confirmed account. Falls back to accounts.verification.field.
    'confirmed_field' => null,

    'categories' => ['community', 'announcement', 'question', 'offer'],

    // Tags and attributes that survive in post and comment bodies.
    'allowed_tags' => ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'blockquote', 'a'],
    'allowed_attributes' => ['href'],

    // Kinds of reaction; one per account and target.
    'reactions' => ['like', 'helpful', 'thanks'],

    // Curated rooms, synced by `RoomSeeder` (idempotent, keyed by slug).
    'rooms' => [
        ['slug' => 'community', 'name' => 'Allgemein', 'kind' => 'topic', 'icon' => '💬', 'description' => 'Der offene Raum: Vorstellungen, Fragen und alles, was sonst nirgends hinpasst.'],
        ['slug' => 'aushilfen', 'name' => 'Aushilfen-Börse', 'kind' => 'topic', 'icon' => '🔔', 'description' => 'Kurzfristige Aushilfen. Stimmen suchen und anbieten, ehrlich und unkompliziert.'],
        ['slug' => 'stimme', 'name' => 'Stimme & Technik', 'kind' => 'topic', 'icon' => '🎤', 'description' => 'Stimmtechnik im Chor: Einsingen, Registerarbeit, Intonation, Stimmgesundheit.'],
        ['slug' => 'chorleitung', 'name' => 'Chorleitung', 'kind' => 'topic', 'icon' => '🎯', 'description' => 'Austausch unter Chorleiter:innen: Methodik, Probenaufbau, Repertoire, Organisation.'],
        ['slug' => 'verein', 'name' => 'Verein & Orga', 'kind' => 'topic', 'icon' => '📋', 'description' => 'Vereinsführung, Mitgliedergewinnung, Finanzen und der ganze Chor-Alltag.'],
        ['slug' => 'material', 'name' => 'Material & Noten', 'kind' => 'topic', 'icon' => '📒', 'description' => 'Noten, Arrangements, Lizenzfragen und hilfreiches Material zum Teilen.'],
    ],
];
