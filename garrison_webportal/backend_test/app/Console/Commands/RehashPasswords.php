<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RehashPasswords extends Command
{
    protected $signature = 'rehash:passwords';
    protected $description = 'Rehash existing passwords except for one account';

    public function handle()
    {
        // Define the account to skip
        $excludedUserId = 1; // Change to the actual user_id

        // Fetch users except the excluded one
        $users = User::where('user_id', '!=', $excludedUserId)->get();

        foreach ($users as $user) {
            // Check if the password is already hashed using bcrypt
            if (!password_get_info($user->password)['algo']) {
                // Rehash the password
                $user->password = Hash::make($user->password);
                $user->save();

                $this->info("Rehashed password for user ID: {$user->id}");
            }
        }

        $this->info('Password rehashing complete.');
    }
}
