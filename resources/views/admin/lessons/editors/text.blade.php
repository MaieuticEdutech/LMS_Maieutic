{{--
    Text lesson editor (App\Services\Content\Handlers\TextContentHandler).
    Included inside LessonEditor's form. No file upload — content lives in
    lessons.body, sanitised server-side on save (NFR-SEC-06).
--}}
<div>
    <label for="lesson-body-{{ $lesson->id }}" class="block text-sm font-medium text-neutral-900">
        Lesson text
        <span class="text-red-600" aria-hidden="true">*</span>
        <span class="sr-only">(required)</span>
    </label>
    <textarea
        wire:model="body"
        id="lesson-body-{{ $lesson->id }}"
        rows="10"
        class="mt-1.5 block w-full rounded-control border border-neutral-200 px-3 py-2 text-sm text-neutral-900 hover:border-neutral-300"
        placeholder="Write the lesson content&hellip;"
    ></textarea>
</div>
