<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Actions\Certificate\IssueCertificate;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
final class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /*
         * The name and title are snapshots on the real row, so the factory
         * writes literal values rather than deriving them from the related
         * models. A factory that read through the relation would hide exactly
         * the bug the snapshot exists to prevent: a test could rename the user,
         * see the certificate follow, and conclude that was correct.
         */
        return [
            'enrollment_id' => Enrollment::factory(),
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'number' => 'MAI-CERT-'.$this->block().'-'.$this->block(),
            'recipient_name' => $this->faker->name(),
            'course_title' => $this->faker->sentence(3),
            'issued_at' => $this->faker->dateTimeBetween('-1 year'),
        ];
    }

    /**
     * Reads IssueCertificate's own constant rather than repeating it. The first
     * version wrote the alphabet out here as well, and the two copies had
     * already drifted over whether `8` was allowed.
     */
    private function block(): string
    {
        $alphabet = IssueCertificate::ALPHABET;

        return collect(range(1, 4))
            ->map(static fn (): string => $alphabet[random_int(0, strlen($alphabet) - 1)])
            ->join('');
    }
}
