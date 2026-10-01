<?php

declare(strict_types=1);

use App\Enums\AcademicResultStatus;
use App\Enums\AfterTenthQualification;
use App\Enums\MarkingScheme;
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
     * Optional academic history: Class Xth, Class XIIth and
     * Graduation, as stated by the learner on their own profile.
     *
     * THREE NAMED STAGES, NOT A REPEATABLE LIST. The source design shows a
     * fourth stage (Post-Graduation) and branching on "After Xth
     * Qualification" that would change which fields appear next; both were
     * scoped out when this was built, so `after_tenth_qualification` is
     * recorded but never drives the form. If those are wanted later, this is
     * still flat columns on `users` because it is exactly three fixed things
     * — a `hasMany` table is the right shape only once the list can grow.
     *
     * BOARD AND DEGREE NAME ARE FREE TEXT, not an enum, for the same reason
     * country/state were left free text in the address migration: the real
     * list (every exam board, every degree name) is far larger than this
     * schema should be the authority on. MARKING SCHEME and RESULT STATUS ARE
     * enums — both are genuinely two-value questions.
     *
     * ALL NULLABLE AND OPTIONAL ON THE FORM, like every other personal detail
     * here (NFR-DATA-01). Nobody is required to state their academic history
     * to correct their name.
     *
     * Score columns are decimal(5,2): enough for a percentage up to 100.00 or
     * a CGPA up to 10.00, whichever MarkingScheme says it is.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Class Xth.
            $table->string('tenth_school_name')->nullable()->after('permanent_address_pincode');
            $table->string('tenth_board')->nullable()->after('tenth_school_name');
            $table->unsignedSmallInteger('tenth_year_of_passing')->nullable()->after('tenth_board');
            $table->string('tenth_marking_scheme', 16)->nullable()->after('tenth_year_of_passing');
            $table->decimal('tenth_score', 5, 2)->nullable()->after('tenth_marking_scheme');

            // Recorded, never branched on — see the class docblock.
            $table->string('after_tenth_qualification', 16)->nullable()->after('tenth_score');

            // Class XIIth.
            $table->string('twelfth_school_name')->nullable()->after('after_tenth_qualification');
            $table->string('twelfth_board')->nullable()->after('twelfth_school_name');
            $table->unsignedSmallInteger('twelfth_year_of_passing')->nullable()->after('twelfth_board');
            $table->string('twelfth_result_status', 16)->nullable()->after('twelfth_year_of_passing');
            $table->string('twelfth_marking_scheme', 16)->nullable()->after('twelfth_result_status');
            $table->decimal('twelfth_score', 5, 2)->nullable()->after('twelfth_marking_scheme');

            // Graduation.
            $table->string('graduation_university_name')->nullable()->after('twelfth_score');
            $table->string('graduation_degree_name')->nullable()->after('graduation_university_name');
            $table->unsignedSmallInteger('graduation_year_of_passing')->nullable()->after('graduation_degree_name');
            $table->string('graduation_result_status', 16)->nullable()->after('graduation_year_of_passing');
            $table->string('graduation_marking_scheme', 16)->nullable()->after('graduation_result_status');
            $table->decimal('graduation_score', 5, 2)->nullable()->after('graduation_marking_scheme');
        });

        $markingSchemes = self::quoted(MarkingScheme::values());
        $resultStatuses = self::quoted(AcademicResultStatus::values());
        $afterTenth = self::quoted(AfterTenthQualification::values());

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_tenth_marking_scheme_check CHECK (tenth_marking_scheme IN ({$markingSchemes}))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_after_tenth_qualification_check CHECK (after_tenth_qualification IN ({$afterTenth}))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_twelfth_result_status_check CHECK (twelfth_result_status IN ({$resultStatuses}))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_twelfth_marking_scheme_check CHECK (twelfth_marking_scheme IN ({$markingSchemes}))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_graduation_result_status_check CHECK (graduation_result_status IN ({$resultStatuses}))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_graduation_marking_scheme_check CHECK (graduation_marking_scheme IN ({$markingSchemes}))");
    }

    public function down(): void
    {
        // Dropping the columns drops their CHECK constraints with them.
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'tenth_school_name', 'tenth_board', 'tenth_year_of_passing', 'tenth_marking_scheme', 'tenth_score',
                'after_tenth_qualification',
                'twelfth_school_name', 'twelfth_board', 'twelfth_year_of_passing', 'twelfth_result_status', 'twelfth_marking_scheme', 'twelfth_score',
                'graduation_university_name', 'graduation_degree_name', 'graduation_year_of_passing', 'graduation_result_status', 'graduation_marking_scheme', 'graduation_score',
            ]);
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
