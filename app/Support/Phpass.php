<?php

namespace App\Support;

class Phpass
{
    private const ITOA64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    /**
     * Verify a plaintext password against a WordPress phpass hash.
     */
    public static function check(string $password, string $storedHash): bool
    {
        if (strlen($storedHash) !== 34) {
            return false;
        }

        $iterationCountLog2 = strpos(self::ITOA64, $storedHash[3]);

        if ($iterationCountLog2 === false || $iterationCountLog2 < 7 || $iterationCountLog2 > 30) {
            return false;
        }

        $count = 1 << $iterationCountLog2;
        $salt = substr($storedHash, 4, 8);

        if (strlen($salt) !== 8) {
            return false;
        }

        $hash = md5($salt.$password, true);

        do {
            $hash = md5($hash.$password, true);
        } while (--$count);

        $output = substr($storedHash, 0, 12);
        $output .= self::encode64($hash, 16);

        return hash_equals($storedHash, $output);
    }

    private static function encode64(string $input, int $count): string
    {
        $output = '';
        $i = 0;

        do {
            $value = ord($input[$i++]);
            $output .= self::ITOA64[$value & 0x3f];

            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }
            $output .= self::ITOA64[($value >> 6) & 0x3f];

            if ($i++ >= $count) {
                break;
            }

            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }
            $output .= self::ITOA64[($value >> 12) & 0x3f];

            if ($i++ >= $count) {
                break;
            }

            $output .= self::ITOA64[($value >> 18) & 0x3f];
        } while ($i < $count);

        return $output;
    }
}
