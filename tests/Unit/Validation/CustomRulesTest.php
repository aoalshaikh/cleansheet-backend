<?php

namespace Tests\Unit\Validation;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CustomRulesTest extends TestCase
{
    public function test_phone_validation_rule_accepts_valid_e164_numbers(): void
    {
        $validNumbers = [
            '+1234567890',
            '+44123456789',
            '+12345678901234',
            '+491234567890',
        ];

        foreach ($validNumbers as $number) {
            $validator = Validator::make(
                ['phone' => $number],
                ['phone' => 'phone']
            );

            $this->assertFalse(
                $validator->fails(),
                "Phone number {$number} should be valid"
            );
        }
    }

    public function test_phone_validation_rule_rejects_invalid_numbers(): void
    {
        $invalidNumbers = [
            '1234567890', // Missing +
            '+', // Just plus
            '+a1234567890', // Contains letter
            '+0234567890', // Starts with 0
            '+12', // Too short
            '+123456789012345', // Too long
            'invalid',
            '',
            null,
        ];

        foreach ($invalidNumbers as $number) {
            $validator = Validator::make(
                ['phone' => $number],
                ['phone' => 'phone']
            );

            $this->assertTrue(
                $validator->fails(),
                "Phone number {$number} should be invalid"
            );
        }
    }

    public function test_otp_validation_rule_accepts_valid_codes(): void
    {
        $validCodes = [
            '123456',
            '000000',
            '999999',
        ];

        foreach ($validCodes as $code) {
            $validator = Validator::make(
                ['code' => $code],
                ['code' => 'otp']
            );

            $this->assertFalse(
                $validator->fails(),
                "OTP code {$code} should be valid"
            );
        }
    }

    public function test_otp_validation_rule_rejects_invalid_codes(): void
    {
        $invalidCodes = [
            '12345', // Too short
            '1234567', // Too long
            '12345a', // Contains letter
            'abcdef', // All letters
            '12 345', // Contains space
            '12.345', // Contains dot
            '',
            null,
        ];

        foreach ($invalidCodes as $code) {
            $validator = Validator::make(
                ['code' => $code],
                ['code' => 'otp']
            );

            $this->assertTrue(
                $validator->fails(),
                "OTP code {$code} should be invalid"
            );
        }
    }

    public function test_validation_messages_are_correct(): void
    {
        // Test phone validation message
        $validator = Validator::make(
            ['phone' => 'invalid'],
            ['phone' => 'phone']
        );

        $this->assertTrue($validator->fails());
        $this->assertEquals(
            'The phone must be a valid E.164 phone number.',
            $validator->errors()->first('phone')
        );

        // Test OTP validation message
        $validator = Validator::make(
            ['code' => 'invalid'],
            ['code' => 'otp']
        );

        $this->assertTrue($validator->fails());
        $this->assertEquals(
            'The code must be a 6-digit code.',
            $validator->errors()->first('code')
        );
    }

    public function test_rules_can_be_combined_with_other_rules(): void
    {
        // Test phone with required rule
        $validator = Validator::make(
            ['phone' => ''],
            ['phone' => ['required', 'phone']]
        );

        $this->assertTrue($validator->fails());
        $this->assertEquals(
            'The phone field is required.',
            $validator->errors()->first('phone')
        );

        // Test OTP with required rule
        $validator = Validator::make(
            ['code' => ''],
            ['code' => ['required', 'otp']]
        );

        $this->assertTrue($validator->fails());
        $this->assertEquals(
            'The code field is required.',
            $validator->errors()->first('code')
        );
    }

    public function test_rules_work_with_custom_attribute_names(): void
    {
        $validator = Validator::make(
            ['mobile_number' => 'invalid'],
            ['mobile_number' => 'phone'],
            [],
            ['mobile_number' => 'Mobile Phone']
        );

        $this->assertTrue($validator->fails());
        $this->assertEquals(
            'The Mobile Phone must be a valid E.164 phone number.',
            $validator->errors()->first('mobile_number')
        );
    }

    public function test_rules_handle_null_values_correctly(): void
    {
        // Test phone with nullable rule
        $validator = Validator::make(
            ['phone' => null],
            ['phone' => ['nullable', 'phone']]
        );

        $this->assertFalse($validator->fails());

        // Test OTP with nullable rule
        $validator = Validator::make(
            ['code' => null],
            ['code' => ['nullable', 'otp']]
        );

        $this->assertFalse($validator->fails());
    }
}
