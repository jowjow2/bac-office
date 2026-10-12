<?php

use App\Rules\PhilippineContactNumber;

it('accepts Philippine mobile and landline numbers in common formats', function (string $number) {
    expect(PhilippineContactNumber::matches($number))->toBeTrue();
})->with(['09171234567', '0917 123 4567', '0917-123-4567', '+639171234567', '+63 917 123 4567', '639171234567', '(043) 457-1234', '02 8123 4567']);

it('rejects numbers with too many or too few digits and text', function (string $number) {
    expect(PhilippineContactNumber::matches($number))->toBeFalse();
})->with(['098723237214124', '091712345678901', '0917123456', '12345', '+1 415 555 2671', 'call me maybe', '']);
