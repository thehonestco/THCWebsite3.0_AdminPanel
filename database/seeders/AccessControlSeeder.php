<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\{
    User,
    Role,
    Section,
    Module,
    Action,
    Permission
};

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * 1️⃣ ROLES
         */
        $roles = ['Super Admin','Team Lead','Executive','Intern'];
        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r]);
        }

        /**
         * 2️⃣ ACTIONS
         */
        $actions = ['view','add','edit','delete'];
        foreach ($actions as $a) {
            Action::firstOrCreate(['name' => $a]);
        }

        /**
         * 3️⃣ SECTIONS + MODULES + PERMISSIONS
         */
        $data = [
            'MIS' => ['Overview','Business','Marketing','Campaigns','Talent Acquisition','Accounts'],
            'Business' => ['Clients','Leads'],
            'Marketing' => ['Resources','Campaigns','Inbox','Templates'],
            'Talent Acquisition' => ['Positions','Applicants','JD Database'],
            'Accounts' => ['Invoices','Ledgers'],
            'Bottom Menu' => ['User Management','Calendar','Media Center','Settings'],
        ];

        foreach ($data as $sectionName => $modules) {
            $section = Section::firstOrCreate(
                ['slug' => str($sectionName)->slug()],
                ['name' => $sectionName]
            );

            foreach ($modules as $mName) {
                $module = Module::firstOrCreate(
                    [
                        'section_id' => $section->id,
                        'slug' => str($mName)->slug()
                    ],
                    ['name' => $mName]
                );

                foreach (Action::all() as $action) {
                    Permission::firstOrCreate([
                        'module_id' => $module->id,
                        'action_id' => $action->id,
                        'name'      => "{$section->slug}.{$module->slug}.{$action->name}"
                    ]);
                }
            }
        }

        /**
         * 4️⃣ SUPER ADMIN → ALL PERMISSIONS
         */
        $superAdminRole = Role::where('name','Super Admin')->first();
        $superAdminRole->permissions()->sync(
            Permission::pluck('id')->toArray()
        );

        /**
         * 5️⃣ CREATE DEFAULT SUPER ADMIN USER
         */
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'     => 'Super Admin',
                'password' => Hash::make('12345678'),
            ]
        );

        /**
         * 6️⃣ ATTACH ROLE TO USER (user_role pivot)
         */
        $admin->roles()->sync([$superAdminRole->id]);

        /**
         * 7️⃣ (OPTIONAL) DEMO TEAM LEAD USER
         */
        $tlRole = Role::where('name','Team Lead')->first();

        $tlUser = User::firstOrCreate(
            ['email' => 'tl@example.com'],
            [
                'name'     => 'Team Lead',
                'password' => Hash::make('12345678'),
            ]
        );

        $tlUser->roles()->sync([$tlRole->id]);
    }
}
