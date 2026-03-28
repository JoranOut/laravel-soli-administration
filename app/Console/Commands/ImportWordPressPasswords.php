<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportWordPressPasswords extends Command
{
    protected $signature = 'wp:import-passwords {file : Path to CSV file with email,password_hash columns}';

    protected $description = 'Import WordPress password hashes so users can log in with their existing passwords';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error("Could not open file: {$path}");

            return self::FAILURE;
        }

        $header = fgetcsv($handle);

        if ($header === false || count($header) < 2) {
            $this->error('Invalid CSV: expected at least two columns (email, password_hash).');
            fclose($handle);

            return self::FAILURE;
        }

        $updated = 0;
        $skipped = 0;
        $errors = 0;
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            if (count($row) < 2) {
                $this->warn("Line {$line}: skipped — not enough columns.");
                $errors++;

                continue;
            }

            [$email, $passwordHash] = $row;
            $email = trim($email);
            $passwordHash = trim($passwordHash);

            if (! str_starts_with($passwordHash, '$P$') && ! str_starts_with($passwordHash, '$H$')) {
                $this->warn("Line {$line}: skipped — not a WordPress phpass hash ({$email}).");
                $skipped++;

                continue;
            }

            if (strlen($passwordHash) !== 34) {
                $this->warn("Line {$line}: skipped — malformed hash, expected 34 characters ({$email}).");
                $skipped++;

                continue;
            }

            $affected = DB::table('users')
                ->where('email', $email)
                ->update(['password' => $passwordHash]);

            if ($affected === 0) {
                $this->warn("Line {$line}: no matching user found for {$email}.");
                $skipped++;

                continue;
            }

            $updated++;
        }

        fclose($handle);

        $this->info("Done. Updated: {$updated}, Skipped: {$skipped}, Errors: {$errors}.");

        return self::SUCCESS;
    }
}
