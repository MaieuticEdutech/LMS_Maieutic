<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| audit_logs — append-only, now enforced at the database too
|--------------------------------------------------------------------------
|
| PHASE 14. NFR-SEC-16, architecture.md §18.4.
|
| TABLE CLASSIFICATION (rule S-5): TENANT-OWNED, same as the table itself.
|
| The original migration's protection is application-level only: the
| AuditLog model's `updating`/`deleting` events throw, and AuditLogPolicy
| denies update/delete to every actor including a super admin. Both are
| real controls, but both live in PHP — a raw SQL client, a `DB::table(
| 'audit_logs')` query bypassing the model, or a future migration/seeder
| bug would sail straight past them.
|
| This adds the backstop those can't be bypassed by: a BEFORE UPDATE OR
| DELETE trigger that raises at the database level, unconditionally, no
| matter what issued the statement. It costs nothing on the INSERT-only
| path every write actually takes.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        // CREATE OR REPLACE + DROP TRIGGER IF EXISTS, not a bare CREATE:
        // `php artisan migrate:fresh` drops tables (taking the trigger with
        // them) but never drops schema-level functions, so a function from
        // a previous fresh-migrate survives and a bare CREATE FUNCTION
        // collides with it on the very next test run.
        //
        // ONE UPDATE IS DELIBERATELY LET THROUGH: `user_id`'s
        // ON DELETE SET NULL foreign-key action (2026_08_12_100003) IS an
        // UPDATE under the hood, fired when the actor's account is deleted
        // — the original migration's whole point is that this must NOT
        // erase what they did. A blanket "no UPDATE, ever" trigger broke
        // exactly that. So the guard allows precisely a user_id -> NULL
        // change with every other column untouched, and still raises on
        // anything else — including re-nulling an already-null user_id
        // alongside any other change.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_audit_log_mutation() RETURNS TRIGGER AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'audit_logs is append-only: DELETE is not permitted on row id %', OLD.id;
                END IF;

                IF NEW.user_id IS NULL
                    AND NEW.action IS NOT DISTINCT FROM OLD.action
                    AND NEW.auditable_type IS NOT DISTINCT FROM OLD.auditable_type
                    AND NEW.auditable_id IS NOT DISTINCT FROM OLD.auditable_id
                    AND NEW.description IS NOT DISTINCT FROM OLD.description
                    AND NEW.changes IS NOT DISTINCT FROM OLD.changes
                    AND NEW.ip_address IS NOT DISTINCT FROM OLD.ip_address
                    AND NEW.user_agent IS NOT DISTINCT FROM OLD.user_agent
                    AND NEW.created_at IS NOT DISTINCT FROM OLD.created_at
                THEN
                    RETURN NEW;
                END IF;

                RAISE EXCEPTION 'audit_logs is append-only: UPDATE is not permitted on row id %', OLD.id;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS audit_logs_append_only ON audit_logs;

            CREATE TRIGGER audit_logs_append_only
                BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW
                EXECUTE FUNCTION prevent_audit_log_mutation();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS audit_logs_append_only ON audit_logs;
            DROP FUNCTION IF EXISTS prevent_audit_log_mutation();
            SQL);
    }
};
