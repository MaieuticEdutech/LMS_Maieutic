<?php

declare(strict_types=1);

use App\Enums\EnrollmentSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| enrollments.source — allow 'free_promotion'
|--------------------------------------------------------------------------
|
| TABLE CLASSIFICATION (planning.md rule S-5): TENANT-OWNED.
|
| The free-launch promotion adds a third audited way to hold an enrollment
| (planning.md §16.4). The CHECK constraint mirrors EnrollmentSource (ADR-012),
| so it is replaced rather than loosened by hand. No column or data changes.
*/
return new class extends Migration
{
    public function up(): void
    {
        $this->replaceCheck(EnrollmentSource::values());
    }

    public function down(): void
    {
        $this->replaceCheck(array_values(array_diff(EnrollmentSource::values(), ['free_promotion'])));
    }

    /**
     * @param  list<string>  $values
     */
    private function replaceCheck(array $values): void
    {
        $list = implode(', ', array_map(
            static fn (string $value): string => "'".str_replace("'", "''", $value)."'",
            $values,
        ));

        DB::statement('ALTER TABLE enrollments DROP CONSTRAINT IF EXISTS enrollments_source_check');
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_source_check CHECK (source IN ({$list}))");
    }
};
