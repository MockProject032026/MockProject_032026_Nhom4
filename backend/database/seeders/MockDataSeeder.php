<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MockDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create a Notary User
        $notaryId = (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::table('users')->insert([
            'id' => $notaryId,
            'full_name' => 'John Doe Notary',
            'email' => 'notary@example.com',
            'phone_number' => '1234567890',
            'password_hash' => bcrypt('password'),
            'id_role' => 3, // Assuming 3 is Notary
            'status' => 'active',
            'created_at' => now(),
        ]);

        // 2. Create some Journal Entries
        for ($i = 1; $i <= 5; $i++) {
            $entryId = (string) \Illuminate\Support\Str::uuid();
            $status = ($i % 2 == 0) ? 'completed' : 'pending';
            $state = ($i % 2 == 0) ? 'CA' : 'NY';
            
            \Illuminate\Support\Facades\DB::table('journal_entries')->insert([
                'id' => $entryId,
                'notary_id' => $notaryId,
                'execution_date' => now()->subDays($i),
                'venue_state' => $state,
                'venue_county' => 'Orange',
                'status' => $status,
                'notarial_fee' => 15.00 * $i,
                'act_type' => 'Acknowledgment',
                'is_holiday' => 0,
                'thumbprint_waived' => ($i == 3) ? 1 : 0, // entry #3 waived
                'risk_flag' => 'LOW',
                'verification_method' => 'ID_CARD',
            ]);

            // 3. Create Signers
            $signerId = (string) \Illuminate\Support\Str::uuid();
            \Illuminate\Support\Facades\DB::table('signers')->insert([
                'id' => $signerId,
                'journal_entry_id' => $entryId,
                'full_name' => "Signer $i",
                'email' => "signer$i@example.com",
            ]);

            // 4. Biometric Data (Only for CA entries, others missing for test)
            if ($state == 'CA') {
                \Illuminate\Support\Facades\DB::table('biometric_data')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'signer_id' => $signerId,
                    'signature_image' => 'mock_signature_data',
                    'thumbprint_image' => 'mock_thumb_data',
                ]);
            }
        }

        // 5. Audit Logs
        for ($j = 1; $j <= 3; $j++) {
            \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'timestamp' => now()->subHours($j),
                'initiator_name' => 'John Doe Notary',
                'action' => 'LOGIN',
                'resource_id' => '-',
                'flags' => 'INFO',
            ]);
        }
    }
}
