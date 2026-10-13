{{--
    Read-only. Role and status are displayed but NOT editable: a user may
    never change their own, and UserPolicy refuses it even for a super admin
    (FR-RBAC-08). Rendering them read-only is presentation; the policy is the
    control.
--}}
<div class="relative flex flex-col items-center gap-1.5 overflow-hidden rounded-hero border border-neutral-200 bg-white px-7 pb-7 pt-12 text-center shadow-xs">
    {{-- A deep-teal band behind the avatar, as on the storefront heroes. --}}
    <div class="absolute inset-x-0 top-0 h-20 bg-teal-900" aria-hidden="true"></div>

    {{-- The uploaded photo once there is one (Upload Documents, elsewhere on
         this page), the initials disc until then. Both are decorative here —
         the name text beside it already identifies the account, so an empty
         alt is correct, not an oversight. --}}
    @if (($user->getAttributes()['avatar_path'] ?? null) !== null)
        <img
            src="{{ route('profile.photo') }}"
            alt=""
            class="relative mb-2 h-[76px] w-[76px] rounded-full border-4 border-white object-cover"
        >
    @else
        <div class="relative mb-2 flex h-[76px] w-[76px] items-center justify-center rounded-full border-4 border-white bg-teal-600 text-2xl font-semibold text-white"
             aria-hidden="true">
            {{ $user->initials() }}
        </div>
    @endif

    <div class="font-sans text-lg font-semibold tracking-normal text-neutral-900">{{ $user->name }}</div>
    <div class="text-[13.5px] text-neutral-500">{{ $user->email }}</div>

    <div class="text-[12.5px] text-neutral-400">
        Learning since {{ $user->created_at?->format('F Y') }}
    </div>

    <div class="mt-4 flex w-full items-center justify-center gap-2 border-t border-neutral-100 pt-4">
        <x-badge variant="brand">{{ $user->role->label() }}</x-badge>
        <x-badge :variant="$user->status->badgeVariant()">{{ $user->status->label() }}</x-badge>
    </div>
</div>
