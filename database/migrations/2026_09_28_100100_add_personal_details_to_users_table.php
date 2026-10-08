<?php

declare(strict_types=1);

use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\PersonTitle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * TENANCY CLASSIFICATION: TENANT-OWNED.
 *
 * These columns live on `users`, which is tenant-owned (see the table's own
 * migration). A person belongs to one organisation in V2 (planning.md S-1).
 */
return new class extends Migration
{
    /**
     * Optional personal details a learner can state on their own profile.
     *
     * NFR-DATA-01 was widened to allow exactly these. Religion and caste
     * category were deliberately left out. Aadhaar and PAN have their own
     * migration (2026_09_28_100200), because they are handled differently from
     * everything here: encrypted, masked, and kept out of the audit log.
     *
     * ALL NULLABLE, AND OPTIONAL ON THE FORM. Accounts arrive by
     * self-registration, administrator creation and purchase, and none of those
     * asks for any of this; nor should saving a corrected name require a blood
     * group. Blank is a legitimate answer to every one of these questions.
     *
     * Each enum-backed column carries a CHECK constraint matching its PHP enum,
     * as every status and type in this schema does.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('title', 8)->nullable()->after('name');
            $table->string('gender', 32)->nullable()->after('title');
            // A calendar date, not a timestamp: a birthday has no time zone.
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->string('blood_group', 3)->nullable()->after('date_of_birth');
            $table->string('marital_status', 32)->nullable()->after('blood_group');
            // Free text rather than an enum: a complete list of nationalities
            // is not something this schema should be the authority on.
            $table->string('nationality', 100)->nullable()->after('marital_status');
        });

        $titles = self::quoted(PersonTitle::values());
        $genders = self::quoted(Gender::values());
        $bloodGroups = self::quoted(BloodGroup::values());
        $maritalStatuses = self::quoted(MaritalStatus::values());

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_title_check CHECK (title IN ({$titles}))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_gender_check CHECK (gender IN ({$genders}))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_blood_group_check CHECK (blood_group IN ({$bloodGroups}))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_marital_status_check CHECK (marital_status IN ({$maritalStatuses}))");
    }

    public function down(): void
    {
        // Dropping the columns drops their CHECK constraints with them.
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['title', 'gender', 'date_of_birth', 'blood_group', 'marital_status', 'nationality']);
        });
    }

    /**
     * @param  list<string>  $values
     */
    private static function quoted(array $values): string
    {
        return implode(', ', array_map(
            static fn (string $value): string => "'".str_replace("'", "''", $value)."'",
            $values,
        ));
    }
};
