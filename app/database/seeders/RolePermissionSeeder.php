<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roleNames = [
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'teacher' => 'Teacher',
            'parent' => 'Parent',
        ];

        $roles = [];
        foreach ($roleNames as $key => $name) {
            $roles[$key] = Role::updateOrCreate(['key' => $key], ['name' => $name]);
        }

        $definitions = [
            'system.manage' => ['Manage system settings', 'system'],
            'system.audit.view' => ['View audit history', 'system'],
            'system.backups.manage' => ['Manage backups', 'system'],
            'roles.super-admin.grant' => ['Grant Super Admin role', 'accounts'],
            'accounts.manage' => ['Invite and manage accounts', 'accounts'],
            'accounts.delete' => ['Delete login accounts', 'accounts'],
            'students.manage' => ['Manage student records', 'students'],
            'students.view-class' => ['View assigned class roster', 'students'],
            'students.view-linked' => ['View linked children', 'students'],
            'parents.manage' => ['Manage parent profiles and links', 'parents'],
            'staff.manage' => ['Manage teacher profiles', 'staff'],
            'classes.manage' => ['Manage classes, subjects and assignments', 'school setup'],
            'classes.view-assigned' => ['View assigned class and subjects', 'school setup'],
            'results.view-all' => ['View all results', 'results'],
            'results.enter-assigned' => ['Enter marks for assigned subjects', 'results'],
            'results.submit-assigned' => ['Submit assigned marks', 'results'],
            'results.review' => ['Review submitted results', 'results'],
            'results.approve' => ['Approve and publish results', 'results'],
            'results.incomplete-override' => ['Approve incomplete-result publication', 'results'],
            'results.reopen' => ['Reopen a published submission', 'results'],
            'results.view-assigned' => ['View assigned-subject results', 'results'],
            'results.view-linked' => ['View linked-child published results', 'results'],
            'reports.view-all' => ['View school reports and report cards', 'reports'],
            'reports.view-linked' => ['View linked-child report cards', 'reports'],
            'announcements.manage' => ['Create, edit and archive announcements', 'communications'],
            'announcements.view-school' => ['View school announcements', 'communications'],
            'announcements.view-class' => ['View class announcements', 'communications'],
            'calendar.manage' => ['Manage school calendar', 'communications'],
            'calendar.view-school' => ['View school calendar', 'communications'],
            'calendar.view-class' => ['View class calendar', 'communications'],
            'correction-requests.create' => ['Send result correction requests', 'results'],
            'correction-requests.review' => ['Review parent correction requests', 'results'],
            'promotions.review' => ['Review promotion proposals', 'students'],
            'promotions.confirm' => ['Confirm and apply promotion', 'students'],
            'imports.commit' => ['Commit approved student/staff imports', 'data'],
        ];

        $permissions = [];
        foreach ($definitions as $key => [$name, $group]) {
            $permissions[$key] = Permission::updateOrCreate(
                ['key' => $key],
                ['name' => $name, 'category' => $group],
            );
        }

        $adminPermissions = [
            'accounts.manage', 'students.manage', 'parents.manage', 'staff.manage', 'classes.manage',
            'results.view-all', 'results.review', 'results.approve', 'results.incomplete-override',
            'reports.view-all', 'announcements.manage', 'calendar.manage', 'correction-requests.review',
            'promotions.review', 'promotions.confirm', 'imports.commit',
        ];
        $teacherPermissions = [
            'students.view-class', 'classes.view-assigned', 'results.enter-assigned', 'results.submit-assigned',
            'results.view-assigned', 'announcements.view-school', 'announcements.view-class',
            'calendar.view-school', 'calendar.view-class',
        ];
        $parentPermissions = [
            'students.view-linked', 'results.view-linked', 'reports.view-linked', 'announcements.view-school',
            'announcements.view-class', 'calendar.view-school', 'calendar.view-class', 'correction-requests.create',
        ];

        $roles['super_admin']->permissions()->sync(array_map(fn (Permission $permission) => $permission->id, $permissions));
        $roles['admin']->permissions()->sync(array_map(fn (string $key) => $permissions[$key]->id, $adminPermissions));
        $roles['teacher']->permissions()->sync(array_map(fn (string $key) => $permissions[$key]->id, $teacherPermissions));
        $roles['parent']->permissions()->sync(array_map(fn (string $key) => $permissions[$key]->id, $parentPermissions));
    }
}
