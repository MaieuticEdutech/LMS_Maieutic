{{--
    Resource lesson editor (App\Services\Content\Handlers\ResourceContentHandler).
    Included inside LessonEditor's form.

    A resource lesson's content IS a downloadable file, and ResourceContentHandler
    maps it to MediaPurpose::Attachment — the same purpose every lesson type's
    supplementary-attachments uploader uses. There is no separate "primary vs
    supplementary" distinction at the database level for this type, so this
    partial adds no uploader of its own; the "Files" uploader LessonEditor
    renders below (labelled per-type) is where files for this lesson go.
--}}
<p class="text-sm text-neutral-600">
    A downloadable resource lesson. Add one or more files below — students see them as
    downloads, with no video or text body.
</p>
