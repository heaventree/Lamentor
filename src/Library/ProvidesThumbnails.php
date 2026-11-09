<?php

namespace Lamentor\Library;

/** A source whose thumbnails are local image files, served through the library thumbnail route. */
interface ProvidesThumbnails
{
    /** Absolute path of the block's thumbnail image, or null. */
    public function thumbnailPath(string $id): ?string;
}
