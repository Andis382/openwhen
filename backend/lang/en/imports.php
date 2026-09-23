<?php

// Row errors in CSV imports. Shown next to the row, so they do not repeat the column name.
return [
    'required' => 'Missing.',
    'too_long' => 'Too long.',
    'coordinate' => 'Not a coordinate.',
    'phone' => 'Not a phone number.',
    'money' => 'Not an amount.',
    'hours' => 'Write hours like 07:00-13:00, 15:00-20:00 or "closed".',
    'duplicate_code' => 'Same code as line :line.',
    'shop_unknown' => 'No shop with this code or name.',
    'shop_ambiguous' => 'Several shops have this name; use the code.',
    'datetime' => 'Use a date like 2026-09-14 and a time like 08:40.',
    'future' => 'This is in the future.',
    'yes_no' => 'Write yes or no.',
];
