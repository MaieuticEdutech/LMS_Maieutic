<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Collect Aadhaar and PAN
    |--------------------------------------------------------------------------
    |
    | Whether the profile asks for an Aadhaar number, and a PAN. OFF by default.
    |
    | Storing these does NOT verify anyone: nothing checks them against an
    | official source, so any 12 digits or any image is accepted. Verifying an
    | Aadhaar properly needs UIDAI authentication through a licensed agency,
    | which a private platform cannot simply add. Until there is a real
    | requirement, not holding the data is the safer position — it cannot be
    | breached or lost from a server that is not yet backed up.
    |
    | When OFF:
    |   - the inputs are not shown;
    |   - the backend refuses to store a new number (UpdateProfile), because hiding a control is never the
    |     control — a crafted request must not get through either;
    |   - a learner who already saved one can still SEE it masked and DELETE
    |     it. Removal is never switched off.
    |
    */

    'collect_identity' => (bool) env('PROFILE_COLLECT_IDENTITY', false),

];
