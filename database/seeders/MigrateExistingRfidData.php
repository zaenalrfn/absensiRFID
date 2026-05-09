<?php

namespace Database\Seeders;

use App\Models\RfidCard;
use App\Models\User;
use Illuminate\Database\Seeder;

class MigrateExistingRfidData extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $usersWithRfid = User::whereNotNull('rfid_uid')
            ->where('rfid_uid', '!=', '')
            ->get();

        foreach ($usersWithRfid as $user) {
            RfidCard::updateOrCreate(
                ['uid' => $user->rfid_uid],
                [
                    'user_id' => $user->id,
                    'label' => 'Kartu '.$user->name,
                ]
            );
        }
    }
}
