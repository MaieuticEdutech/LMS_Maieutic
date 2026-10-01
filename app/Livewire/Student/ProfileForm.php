<?php

declare(strict_types=1);

namespace App\Livewire\Student;

use App\Actions\Profile\ChangeEmail;
use App\Actions\Profile\UpdateProfile;
use App\Enums\AcademicResultStatus;
use App\Enums\AfterTenthQualification;
use App\Enums\BloodGroup;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\MarkingScheme;
use App\Enums\PersonTitle;
use App\Models\User;
use App\Rules\Aadhaar;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Editable profile details (FR-STU-12, FR-AUTH-12).
 *
 * Sits inside the existing profile page alongside the read-only account card
 * and Fortify's password form, which are unchanged. Password changes stay with
 * Fortify — writing a second password path here would be a second door.
 *
 * ═════════════════════════════════════════════════════════════════════════
 * CHANGING THE EMAIL REQUIRES THE CURRENT PASSWORD. THE NAME DOES NOT.
 *
 * They are different risks. A wrong name is cosmetic. A changed email moves
 * the address every password-reset link and notification goes to — it is the
 * step an account takeover needs, and the one a borrowed unlocked laptop makes
 * trivial.
 *
 * The password prompt is also the only protection against a typo, because
 * ChangeEmail writes immediately and sends verification to the NEW address:
 * get it wrong and the recovery path goes somewhere you do not control.
 *
 * Asking for it on every profile edit would train people to type their
 * password without reading. Asking only where it matters keeps it meaningful.
 * ═════════════════════════════════════════════════════════════════════════
 */
final class ProfileForm extends Component
{
    #[Validate('required|string|max:255')]
    public string $firstName = '';

    #[Validate('required|string|max:255')]
    public string $lastName = '';

    /*
     * REQUIRED HERE, NULLABLE IN THE DATABASE, AND THAT IS DELIBERATE.
     *
     * A learner must give a contact number before they can save their profile
     * — asked for explicitly. But the column stays nullable, because accounts
     * also arrive by administrator creation and (from Phase 12) by purchase,
     * neither of which involves anybody filling in this form. A NOT NULL
     * constraint would stop an administrator adding a student on a phone call
     * and would break every account that predates the rule.
     *
     * "Must be supplied before this form will save" is a product rule. It
     * belongs in validation, not in the schema.
     */
    #[Validate('required|string|min:6|max:32|regex:/^[0-9 +()\-]+$/')]
    public string $phone = '';

    /*
     * Optional personal details (NFR-DATA-01). Every one may be left blank —
     * correcting a misspelled name must never require a blood group. Validated
     * in saveDetails() rather than by attribute, because the enum rules are
     * built at runtime and an attribute can only hold a constant expression.
     *
     * `personTitle`, not `title`: a component property of that name would sit
     * next to Livewire's own page-title handling, and nobody reading the view
     * should have to wonder which one they are looking at.
     */
    public ?string $personTitle = null;

    public ?string $gender = null;

    /** Y-m-d, as a native date input sends it. */
    public ?string $dateOfBirth = null;

    public ?string $bloodGroup = null;

    public ?string $maritalStatus = null;

    public ?string $nationality = null;

    /*
     * ═════════════════════════════════════════════════════════════════════
     * AADHAAR AND PAN START EMPTY, EVERY TIME — THE STORED NUMBER IS NEVER
     * LOADED INTO THE FORM.
     *
     * Component state is serialised into the page. Pre-filling these would
     * put the full number into the HTML of every profile view. Instead the
     * view shows the masked form (User::maskedAadhaar()) beside an empty box:
     * blank keeps what is stored, a new number replaces it, and "Remove"
     * (removeAadhaar / removePan) clears it. Both are cleared again after
     * every save, so a typed number does not linger in state either.
     * ═════════════════════════════════════════════════════════════════════
     */
    public string $aadhaarNumber = '';

    public string $panNumber = '';

    /*
     * ═════════════════════════════════════════════════════════════════════
     * POSTAL ADDRESS — FREE TEXT THROUGHOUT, ALL OPTIONAL.
     *
     * Country/state/district are plain strings, not a cascading places
     * lookup: that needs a real geographic dataset, a materially bigger piece
     * of work than the rest of this form, and was scoped out. See the
     * migration for the full reasoning.
     * ═════════════════════════════════════════════════════════════════════
     */
    public ?string $addressCountry = null;

    public ?string $addressState = null;

    public ?string $addressDistrict = null;

    public ?string $addressCity = null;

    public ?string $addressLine1 = null;

    public ?string $addressLine2 = null;

    public ?string $addressPincode = null;

    /*
     * Defaults to true — an account with no address on file reads as "the two
     * would be the same" rather than as an unanswered question, and it is
     * what the form itself pre-selects. The second address block only appears
     * once this is explicitly set to false.
     */
    public bool $permanentSameAsCorrespondence = true;

    public ?string $permanentCountry = null;

    public ?string $permanentState = null;

    public ?string $permanentDistrict = null;

    public ?string $permanentCity = null;

    public ?string $permanentLine1 = null;

    public ?string $permanentLine2 = null;

    public ?string $permanentPincode = null;

    /*
     * ═════════════════════════════════════════════════════════════════════
     * ACADEMIC DETAILS — THREE FIXED STAGES, ALL OPTIONAL.
     *
     * Class Xth, Class XIIth and Graduation. Not a repeatable list —
     * see the migration for why. `afterTenthQualification` is recorded but
     * never branches the form: a fuller design would let it choose between,
     * say, a Class XII path and a diploma path, and that was scoped out.
     *
     * Year and score are kept as strings here, the same way dateOfBirth is:
     * a native number/date-adjacent input sends a string, and turning it into
     * an int or float belongs to validation and saving, not to the property
     * that just mirrors what the field currently holds.
     */
    public ?string $tenthSchoolName = null;

    public ?string $tenthBoard = null;

    public ?string $tenthYearOfPassing = null;

    public ?string $tenthMarkingScheme = null;

    public ?string $tenthScore = null;

    public ?string $afterTenthQualification = null;

    public ?string $twelfthSchoolName = null;

    public ?string $twelfthBoard = null;

    public ?string $twelfthYearOfPassing = null;

    public ?string $twelfthResultStatus = null;

    public ?string $twelfthMarkingScheme = null;

    public ?string $twelfthScore = null;

    public ?string $graduationUniversityName = null;

    public ?string $graduationDegreeName = null;

    public ?string $graduationYearOfPassing = null;

    public ?string $graduationResultStatus = null;

    public ?string $graduationMarkingScheme = null;

    public ?string $graduationScore = null;

    /*
     * Optional on purpose. A blank answer means "no preference", and
     * User::certificateName() then falls back to the display name — a
     * certificate should never print an empty line where a name belongs.
     */
    #[Validate('nullable|string|max:255')]
    public ?string $certificateName = null;

    public string $email = '';

    /** Cleared after every submit — never held in component state longer. */
    public string $currentPassword = '';

    public function mount(): void
    {
        $user = $this->user();

        // Both name reads live on the model: the fallback that splits a legacy
        // display name is a rule about names, not about this screen, and the
        // model is where it can be tested without a component.
        $parts = $user->editableNameParts();

        $this->firstName = $parts['first'];
        $this->lastName = $parts['last'];
        $this->certificateName = $user->statedCertificateName();
        $this->phone = (string) $user->phone;
        // Read from the raw attributes, not the property: on a model whose row
        // was inserted in this same request the column is absent rather than
        // null, and the property read throws under
        // preventAccessingMissingAttributes (see User::nameField).
        // Raw attributes for the same reason, which also hands back the stored
        // strings (enum values, Y-m-d) the form controls expect, rather than
        // cast enum and date objects.
        $attributes = $user->getAttributes();
        $this->personTitle = $attributes['title'] ?? null;
        $this->gender = $attributes['gender'] ?? null;
        $this->dateOfBirth = isset($attributes['date_of_birth'])
            ? substr((string) $attributes['date_of_birth'], 0, 10)
            : null;
        $this->bloodGroup = $attributes['blood_group'] ?? null;
        $this->maritalStatus = $attributes['marital_status'] ?? null;
        $this->nationality = $attributes['nationality'] ?? null;

        $this->addressCountry = $attributes['address_country'] ?? null;
        $this->addressState = $attributes['address_state'] ?? null;
        $this->addressDistrict = $attributes['address_district'] ?? null;
        $this->addressCity = $attributes['address_city'] ?? null;
        $this->addressLine1 = $attributes['address_line_1'] ?? null;
        $this->addressLine2 = $attributes['address_line_2'] ?? null;
        $this->addressPincode = $attributes['address_pincode'] ?? null;

        // Absent on a freshly inserted row, same as every other column read
        // here — default to true rather than casting an absent value to false.
        $this->permanentSameAsCorrespondence = ! array_key_exists('permanent_address_same_as_correspondence', $attributes)
            || (bool) $attributes['permanent_address_same_as_correspondence'];
        $this->permanentCountry = $attributes['permanent_address_country'] ?? null;
        $this->permanentState = $attributes['permanent_address_state'] ?? null;
        $this->permanentDistrict = $attributes['permanent_address_district'] ?? null;
        $this->permanentCity = $attributes['permanent_address_city'] ?? null;
        $this->permanentLine1 = $attributes['permanent_address_line_1'] ?? null;
        $this->permanentLine2 = $attributes['permanent_address_line_2'] ?? null;
        $this->permanentPincode = $attributes['permanent_address_pincode'] ?? null;

        $this->tenthSchoolName = $attributes['tenth_school_name'] ?? null;
        $this->tenthBoard = $attributes['tenth_board'] ?? null;
        $this->tenthYearOfPassing = isset($attributes['tenth_year_of_passing']) ? (string) $attributes['tenth_year_of_passing'] : null;
        $this->tenthMarkingScheme = $attributes['tenth_marking_scheme'] ?? null;
        $this->tenthScore = isset($attributes['tenth_score']) ? (string) $attributes['tenth_score'] : null;
        $this->afterTenthQualification = $attributes['after_tenth_qualification'] ?? null;
        $this->twelfthSchoolName = $attributes['twelfth_school_name'] ?? null;
        $this->twelfthBoard = $attributes['twelfth_board'] ?? null;
        $this->twelfthYearOfPassing = isset($attributes['twelfth_year_of_passing']) ? (string) $attributes['twelfth_year_of_passing'] : null;
        $this->twelfthResultStatus = $attributes['twelfth_result_status'] ?? null;
        $this->twelfthMarkingScheme = $attributes['twelfth_marking_scheme'] ?? null;
        $this->twelfthScore = isset($attributes['twelfth_score']) ? (string) $attributes['twelfth_score'] : null;
        $this->graduationUniversityName = $attributes['graduation_university_name'] ?? null;
        $this->graduationDegreeName = $attributes['graduation_degree_name'] ?? null;
        $this->graduationYearOfPassing = isset($attributes['graduation_year_of_passing']) ? (string) $attributes['graduation_year_of_passing'] : null;
        $this->graduationResultStatus = $attributes['graduation_result_status'] ?? null;
        $this->graduationMarkingScheme = $attributes['graduation_marking_scheme'] ?? null;
        $this->graduationScore = isset($attributes['graduation_score']) ? (string) $attributes['graduation_score'] : null;

        $this->email = $user->email;
    }

    /**
     * Name, phone numbers, certificate name, and the optional personal,
     * address and academic details. No password required — see the class
     * docblock.
     */
    public function saveDetails(): void
    {
        $this->validateOnly('firstName');
        $this->validateOnly('lastName');
        $this->validateOnly('phone');
        $this->validateOnly('certificateName');

        $this->validate([
            'personTitle' => ['nullable', Rule::enum(PersonTitle::class)],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            // A real, past date. 1900 is a floor against typos (a year of
            // 0208), not a claim about anyone's age.
            'dateOfBirth' => ['nullable', 'date_format:Y-m-d', 'after:1900-01-01', 'before:today'],
            'bloodGroup' => ['nullable', Rule::enum(BloodGroup::class)],
            'maritalStatus' => ['nullable', Rule::enum(MaritalStatus::class)],
            'nationality' => ['nullable', 'string', 'max:100'],
        ], attributes: [
            'personTitle' => 'title',
            'dateOfBirth' => 'date of birth',
            'bloodGroup' => 'blood group',
            'maritalStatus' => 'marital status',
        ]);

        // Normalised BEFORE validating, so the rules see what will be stored:
        // people type Aadhaar in groups of four and PAN in any case.
        $this->aadhaarNumber = (string) preg_replace('/\s+/', '', $this->aadhaarNumber);
        $this->panNumber = strtoupper((string) preg_replace('/\s+/', '', $this->panNumber));

        $this->validate([
            'aadhaarNumber' => ['nullable', new Aadhaar],
            // Five letters, four digits, one letter — the PAN format.
            'panNumber' => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
        ], messages: [
            'panNumber.regex' => 'The PAN must be 10 characters: five letters, four digits, then a letter (e.g. ABCDE1234F).',
        ], attributes: [
            'aadhaarNumber' => 'Aadhaar number',
            'panNumber' => 'PAN',
        ]);

        $this->validate([
            'addressCountry' => ['nullable', 'string', 'max:100'],
            'addressState' => ['nullable', 'string', 'max:100'],
            'addressDistrict' => ['nullable', 'string', 'max:100'],
            'addressCity' => ['nullable', 'string', 'max:100'],
            'addressLine1' => ['nullable', 'string', 'max:255'],
            'addressLine2' => ['nullable', 'string', 'max:255'],
            'addressPincode' => ['nullable', 'string', 'max:12'],
            'permanentSameAsCorrespondence' => ['boolean'],
            // required_if, not nullable: once someone says the permanent
            // address differs, an empty second address is not a real answer
            // to that question — the same "asked explicitly" reasoning the
            // main phone number already follows.
            'permanentCountry' => ['nullable', 'required_if:permanentSameAsCorrespondence,false', 'string', 'max:100'],
            'permanentState' => ['nullable', 'string', 'max:100'],
            'permanentDistrict' => ['nullable', 'string', 'max:100'],
            'permanentCity' => ['nullable', 'string', 'max:100'],
            'permanentLine1' => ['nullable', 'required_if:permanentSameAsCorrespondence,false', 'string', 'max:255'],
            'permanentLine2' => ['nullable', 'string', 'max:255'],
            'permanentPincode' => ['nullable', 'required_if:permanentSameAsCorrespondence,false', 'string', 'max:12'],
        ], attributes: [
            'addressCountry' => 'country',
            'addressState' => 'state',
            'addressDistrict' => 'district',
            'addressCity' => 'city',
            'addressLine1' => 'address line 1',
            'addressLine2' => 'address line 2',
            'addressPincode' => 'pincode',
            'permanentCountry' => 'permanent address country',
            'permanentLine1' => 'permanent address line 1',
            'permanentPincode' => 'permanent address pincode',
        ]);

        // A year in the recent past through a decade out — wide enough to
        // hold Graduation's "awaited" case (a result not due until later this
        // year or next), the same bound used for all three stages rather than
        // guessing which ones can legitimately be in the future.
        $yearRule = ['nullable', 'integer', 'min:1950', 'max:'.(int) now()->addYears(10)->format('Y')];

        $this->validate([
            'tenthYearOfPassing' => $yearRule,
            'tenthMarkingScheme' => ['nullable', Rule::enum(MarkingScheme::class)],
            'tenthScore' => ['nullable', 'numeric', 'min:0', 'max:'.self::maxScoreFor($this->tenthMarkingScheme)],
            'afterTenthQualification' => ['nullable', Rule::enum(AfterTenthQualification::class)],
            'twelfthYearOfPassing' => $yearRule,
            'twelfthResultStatus' => ['nullable', Rule::enum(AcademicResultStatus::class)],
            'twelfthMarkingScheme' => ['nullable', Rule::enum(MarkingScheme::class)],
            'twelfthScore' => ['nullable', 'numeric', 'min:0', 'max:'.self::maxScoreFor($this->twelfthMarkingScheme)],
            'graduationYearOfPassing' => $yearRule,
            'graduationResultStatus' => ['nullable', Rule::enum(AcademicResultStatus::class)],
            'graduationMarkingScheme' => ['nullable', Rule::enum(MarkingScheme::class)],
            'graduationScore' => ['nullable', 'numeric', 'min:0', 'max:'.self::maxScoreFor($this->graduationMarkingScheme)],
        ], attributes: [
            'tenthYearOfPassing' => 'Class Xth year of passing',
            'tenthMarkingScheme' => 'Class Xth marking scheme',
            'tenthScore' => 'Class Xth percentage/CGPA',
            'afterTenthQualification' => 'after Xth qualification',
            'twelfthYearOfPassing' => 'Class XIIth year of passing',
            'twelfthResultStatus' => 'Class XIIth result status',
            'twelfthMarkingScheme' => 'Class XIIth marking scheme',
            'twelfthScore' => 'Class XIIth percentage/CGPA',
            'graduationYearOfPassing' => 'graduation year of passing',
            'graduationResultStatus' => 'graduation result status',
            'graduationMarkingScheme' => 'graduation marking scheme',
            'graduationScore' => 'graduation percentage/CGPA',
        ]);

        // Never store a second address that is a duplicate of the first: when
        // the two are the same, the permanent_address_* columns stay null and
        // whatever was typed into that (hidden) block is discarded rather
        // than saved.
        $permanent = $this->permanentSameAsCorrespondence
            ? array_fill_keys([
                'permanent_address_country', 'permanent_address_state', 'permanent_address_district', 'permanent_address_city',
                'permanent_address_line_1', 'permanent_address_line_2', 'permanent_address_pincode',
            ], null)
            : [
                'permanent_address_country' => self::blankToNull($this->permanentCountry),
                'permanent_address_state' => self::blankToNull($this->permanentState),
                'permanent_address_district' => self::blankToNull($this->permanentDistrict),
                'permanent_address_city' => self::blankToNull($this->permanentCity),
                'permanent_address_line_1' => self::blankToNull($this->permanentLine1),
                'permanent_address_line_2' => self::blankToNull($this->permanentLine2),
                'permanent_address_pincode' => self::blankToNull($this->permanentPincode),
            ];

        // Blank means "keep what is stored", so an identity number is only
        // sent when one was actually typed — see the property docblock.
        $identity = array_filter([
            'aadhaar_number' => $this->aadhaarNumber,
            'pan_number' => $this->panNumber,
        ], static fn (string $value): bool => $value !== '');

        app(UpdateProfile::class)->handle($this->user(), [
            'first_name' => trim($this->firstName),
            'last_name' => trim($this->lastName),
            // Empty means "no preference" and must be stored as null, not as
            // an empty string, so certificateName()'s fallback triggers.
            'certificate_name' => $this->certificateName === null || trim($this->certificateName) === ''
                ? null
                : trim($this->certificateName),
            'phone' => trim($this->phone),
            // An unselected <select> sends '', which is "not stated" and is
            // stored as null — never as an empty string a CHECK would reject.
            'title' => self::blankToNull($this->personTitle),
            'gender' => self::blankToNull($this->gender),
            'date_of_birth' => self::blankToNull($this->dateOfBirth),
            'blood_group' => self::blankToNull($this->bloodGroup),
            'marital_status' => self::blankToNull($this->maritalStatus),
            'nationality' => self::blankToNull($this->nationality),
            ...$identity,
            'address_country' => self::blankToNull($this->addressCountry),
            'address_state' => self::blankToNull($this->addressState),
            'address_district' => self::blankToNull($this->addressDistrict),
            'address_city' => self::blankToNull($this->addressCity),
            'address_line_1' => self::blankToNull($this->addressLine1),
            'address_line_2' => self::blankToNull($this->addressLine2),
            'address_pincode' => self::blankToNull($this->addressPincode),
            'permanent_address_same_as_correspondence' => $this->permanentSameAsCorrespondence,
            ...$permanent,
            'tenth_school_name' => self::blankToNull($this->tenthSchoolName),
            'tenth_board' => self::blankToNull($this->tenthBoard),
            'tenth_year_of_passing' => self::blankToInt($this->tenthYearOfPassing),
            'tenth_marking_scheme' => self::blankToNull($this->tenthMarkingScheme),
            'tenth_score' => self::blankToNull($this->tenthScore),
            'after_tenth_qualification' => self::blankToNull($this->afterTenthQualification),
            'twelfth_school_name' => self::blankToNull($this->twelfthSchoolName),
            'twelfth_board' => self::blankToNull($this->twelfthBoard),
            'twelfth_year_of_passing' => self::blankToInt($this->twelfthYearOfPassing),
            'twelfth_result_status' => self::blankToNull($this->twelfthResultStatus),
            'twelfth_marking_scheme' => self::blankToNull($this->twelfthMarkingScheme),
            'twelfth_score' => self::blankToNull($this->twelfthScore),
            'graduation_university_name' => self::blankToNull($this->graduationUniversityName),
            'graduation_degree_name' => self::blankToNull($this->graduationDegreeName),
            'graduation_year_of_passing' => self::blankToInt($this->graduationYearOfPassing),
            'graduation_result_status' => self::blankToNull($this->graduationResultStatus),
            'graduation_marking_scheme' => self::blankToNull($this->graduationMarkingScheme),
            'graduation_score' => self::blankToNull($this->graduationScore),
        ]);

        $this->aadhaarNumber = '';
        $this->panNumber = '';

        session()->flash('status', 'Your details have been updated.');
    }

    /**
     * Email, gated on the current password.
     */
    public function saveEmail(): void
    {
        $user = $this->user();

        $this->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                // Ignores this user's own row so re-submitting an unchanged
                // address reports "already yours" rather than "taken".
                Rule::unique('users', 'email')->ignore($user->getKey()),
            ],
            'currentPassword' => ['required', 'string'],
        ], attributes: ['currentPassword' => 'current password']);

        $hashed = $user->password;

        // An account still awaiting activation has NO password (ADR-004), and
        // Hash::check against null would be a type error rather than a refusal.
        // Refuse explicitly: someone who has never set a password cannot prove
        // they are present, which is exactly what this gate is checking for.
        if ($hashed === null || ! Hash::check($this->currentPassword, $hashed)) {
            // Cleared before returning: a wrong password must not stay in the
            // component's serialised state to be replayed.
            $this->currentPassword = '';

            $this->addError('currentPassword', 'That password is incorrect.');

            return;
        }

        try {
            app(ChangeEmail::class)->handle($user, $this->email);
        } catch (InvalidArgumentException $e) {
            $this->addError('email', $e->getMessage());

            return;
        } finally {
            $this->currentPassword = '';
        }

        session()->flash('status', 'Check your new address — we have sent a verification link. You will need it before signing in again.');
    }

    /**
     * Clear the stored Aadhaar number. A blank field means "keep", so removal
     * needs its own explicit action.
     */
    public function removeAadhaar(): void
    {
        app(UpdateProfile::class)->handle($this->user(), ['aadhaar_number' => null]);
        $this->aadhaarNumber = '';

        session()->flash('status', 'Your Aadhaar number has been removed.');
    }

    /**
     * Clear the stored PAN.
     */
    public function removePan(): void
    {
        app(UpdateProfile::class)->handle($this->user(), ['pan_number' => null]);
        $this->panNumber = '';

        session()->flash('status', 'Your PAN has been removed.');
    }

    public function render(): View
    {
        $user = $this->user();

        return view('livewire.student.profile-form', [
            'maskedAadhaar' => $user->maskedAadhaar(),
            'maskedPan' => $user->maskedPan(),
            'titles' => PersonTitle::cases(),
            'genders' => Gender::cases(),
            'bloodGroups' => BloodGroup::cases(),
            'maritalStatuses' => MaritalStatus::cases(),
            'ageOnFirstApril' => $this->ageOnFirstApril(),
            'markingSchemes' => MarkingScheme::cases(),
            'academicResultStatuses' => AcademicResultStatus::cases(),
            'afterTenthQualifications' => AfterTenthQualification::cases(),
        ]);
    }

    /**
     * Age as on the most recent 1 April — the academic-year convention the
     * form's "Age as on 1st April" field follows — e.g. "18 years, 0 months,
     * 0 days". Computed for display from whatever is in the date field right
     * now, saved or not; it is never stored, so it cannot go stale.
     *
     * Null when there is no usable date, or the date falls after that 1 April.
     */
    private function ageOnFirstApril(): ?string
    {
        if ($this->dateOfBirth === null || $this->dateOfBirth === '') {
            return null;
        }

        // This runs on every render, against whatever is half-typed in the
        // field — it must shrug off garbage, not throw.
        try {
            $born = CarbonImmutable::createFromFormat('!Y-m-d', $this->dateOfBirth);
        } catch (InvalidFormatException) {
            return null;
        }

        // createFromFormat rolls an impossible date (2008-02-31) forward rather
        // than failing, so round-trip it to make sure it is the date typed.
        if (! $born instanceof CarbonImmutable || $born->format('Y-m-d') !== $this->dateOfBirth) {
            return null;
        }

        $today = CarbonImmutable::today();
        $firstApril = $today->setDate($today->year, 4, 1);

        if ($today->lessThan($firstApril)) {
            $firstApril = $firstApril->subYear();
        }

        if ($born->greaterThan($firstApril)) {
            return null;
        }

        $age = $born->diff($firstApril);

        return sprintf(
            '%d %s, %d %s, %d %s',
            $age->y, $age->y === 1 ? 'year' : 'years',
            $age->m, $age->m === 1 ? 'month' : 'months',
            $age->d, $age->d === 1 ? 'day' : 'days',
        );
    }

    private static function blankToNull(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }

    private static function blankToInt(?string $value): ?int
    {
        $trimmed = self::blankToNull($value);

        return $trimmed === null ? null : (int) $trimmed;
    }

    /**
     * The upper bound a score is allowed to reach, given the marking scheme
     * currently selected. Defaults to a percentage's bound (100) when no
     * scheme is chosen yet — the more permissive of the two, so an
     * as-yet-unselected scheme is not read as "this must be a CGPA".
     */
    private static function maxScoreFor(?string $markingScheme): float
    {
        return MarkingScheme::tryFrom((string) $markingScheme)?->maxScore() ?? MarkingScheme::Percentage->maxScore();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
