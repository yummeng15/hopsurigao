<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'users.view', 'label' => 'View Users', 'group' => 'users'],
            ['name' => 'users.create', 'label' => 'Create Users', 'group' => 'users'],
            ['name' => 'users.edit', 'label' => 'Edit Users', 'group' => 'users'],
            ['name' => 'users.delete', 'label' => 'Delete Users', 'group' => 'users'],

            ['name' => 'roles.view', 'label' => 'View Roles', 'group' => 'roles'],
            ['name' => 'roles.create', 'label' => 'Create Roles', 'group' => 'roles'],
            ['name' => 'roles.edit', 'label' => 'Edit Roles', 'group' => 'roles'],
            ['name' => 'roles.delete', 'label' => 'Delete Roles', 'group' => 'roles'],

            ['name' => 'permissions.view', 'label' => 'View Permissions', 'group' => 'permissions'],
            ['name' => 'permissions.assign', 'label' => 'Assign Permissions', 'group' => 'permissions'],

            // Products
            ['name' => 'products.view', 'label' => 'View Products', 'group' => 'products'],
            ['name' => 'products.create', 'label' => 'Create Products', 'group' => 'products'],
            ['name' => 'products.edit', 'label' => 'Edit Products', 'group' => 'products'],
            ['name' => 'products.delete', 'label' => 'Delete Products', 'group' => 'products'],
            ['name' => 'products.import', 'label' => 'Import Products', 'group' => 'products'],
            ['name' => 'products.export', 'label' => 'Export Products', 'group' => 'products'],

            // Categories
            ['name' => 'categories.view', 'label' => 'View Categories', 'group' => 'categories'],
            ['name' => 'categories.create', 'label' => 'Create Categories', 'group' => 'categories'],
            ['name' => 'categories.edit', 'label' => 'Edit Categories', 'group' => 'categories'],
            ['name' => 'categories.delete', 'label' => 'Delete Categories', 'group' => 'categories'],

            // Orders
            ['name' => 'orders.view', 'label' => 'View Orders', 'group' => 'orders'],
            ['name' => 'orders.create', 'label' => 'Create Orders', 'group' => 'orders'],
            ['name' => 'orders.edit', 'label' => 'Edit Orders', 'group' => 'orders'],
            ['name' => 'orders.delete', 'label' => 'Delete Orders', 'group' => 'orders'],
            ['name' => 'orders.cancel', 'label' => 'Cancel Orders', 'group' => 'orders'],
            ['name' => 'orders.refund', 'label' => 'Refund Orders', 'group' => 'orders'],

            // Invoices
            ['name' => 'invoices.view', 'label' => 'View Invoices', 'group' => 'invoices'],
            ['name' => 'invoices.create', 'label' => 'Create Invoices', 'group' => 'invoices'],
            ['name' => 'invoices.edit', 'label' => 'Edit Invoices', 'group' => 'invoices'],
            ['name' => 'invoices.delete', 'label' => 'Delete Invoices', 'group' => 'invoices'],
            ['name' => 'invoices.send', 'label' => 'Send Invoices', 'group' => 'invoices'],

            // Payments
            ['name' => 'payments.view', 'label' => 'View Payments', 'group' => 'payments'],
            ['name' => 'payments.create', 'label' => 'Create Payments', 'group' => 'payments'],
            ['name' => 'payments.edit', 'label' => 'Edit Payments', 'group' => 'payments'],
            ['name' => 'payments.delete', 'label' => 'Delete Payments', 'group' => 'payments'],
            ['name' => 'payments.approve', 'label' => 'Approve Payments', 'group' => 'payments'],

            // Inventory
            ['name' => 'inventory.view', 'label' => 'View Inventory', 'group' => 'inventory'],
            ['name' => 'inventory.create', 'label' => 'Create Inventory', 'group' => 'inventory'],
            ['name' => 'inventory.edit', 'label' => 'Edit Inventory', 'group' => 'inventory'],
            ['name' => 'inventory.delete', 'label' => 'Delete Inventory', 'group' => 'inventory'],
            ['name' => 'inventory.adjust', 'label' => 'Adjust Inventory', 'group' => 'inventory'],

            // Warehouses
            ['name' => 'warehouses.view', 'label' => 'View Warehouses', 'group' => 'warehouses'],
            ['name' => 'warehouses.create', 'label' => 'Create Warehouses', 'group' => 'warehouses'],
            ['name' => 'warehouses.edit', 'label' => 'Edit Warehouses', 'group' => 'warehouses'],
            ['name' => 'warehouses.delete', 'label' => 'Delete Warehouses', 'group' => 'warehouses'],

            // Suppliers
            ['name' => 'suppliers.view', 'label' => 'View Suppliers', 'group' => 'suppliers'],
            ['name' => 'suppliers.create', 'label' => 'Create Suppliers', 'group' => 'suppliers'],
            ['name' => 'suppliers.edit', 'label' => 'Edit Suppliers', 'group' => 'suppliers'],
            ['name' => 'suppliers.delete', 'label' => 'Delete Suppliers', 'group' => 'suppliers'],

            // Customers
            ['name' => 'customers.view', 'label' => 'View Customers', 'group' => 'customers'],
            ['name' => 'customers.create', 'label' => 'Create Customers', 'group' => 'customers'],
            ['name' => 'customers.edit', 'label' => 'Edit Customers', 'group' => 'customers'],
            ['name' => 'customers.delete', 'label' => 'Delete Customers', 'group' => 'customers'],

            // Reports
            ['name' => 'reports.view', 'label' => 'View Reports', 'group' => 'reports'],
            ['name' => 'reports.create', 'label' => 'Create Reports', 'group' => 'reports'],
            ['name' => 'reports.export', 'label' => 'Export Reports', 'group' => 'reports'],
            ['name' => 'reports.schedule', 'label' => 'Schedule Reports', 'group' => 'reports'],

            // Dashboards
            ['name' => 'dashboards.view', 'label' => 'View Dashboards', 'group' => 'dashboards'],
            ['name' => 'dashboards.create', 'label' => 'Create Dashboards', 'group' => 'dashboards'],
            ['name' => 'dashboards.edit', 'label' => 'Edit Dashboards', 'group' => 'dashboards'],
            ['name' => 'dashboards.delete', 'label' => 'Delete Dashboards', 'group' => 'dashboards'],

            // Notifications
            ['name' => 'notifications.view', 'label' => 'View Notifications', 'group' => 'notifications'],
            ['name' => 'notifications.create', 'label' => 'Create Notifications', 'group' => 'notifications'],
            ['name' => 'notifications.delete', 'label' => 'Delete Notifications', 'group' => 'notifications'],
            ['name' => 'notifications.send', 'label' => 'Send Notifications', 'group' => 'notifications'],

            // Settings
            ['name' => 'settings.view', 'label' => 'View Settings', 'group' => 'settings'],
            ['name' => 'settings.edit', 'label' => 'Edit Settings', 'group' => 'settings'],

            // Logs
            ['name' => 'logs.view', 'label' => 'View Logs', 'group' => 'logs'],
            ['name' => 'logs.delete', 'label' => 'Delete Logs', 'group' => 'logs'],
            ['name' => 'logs.export', 'label' => 'Export Logs', 'group' => 'logs'],

            // Backups
            ['name' => 'backups.view', 'label' => 'View Backups', 'group' => 'backups'],
            ['name' => 'backups.create', 'label' => 'Create Backups', 'group' => 'backups'],
            ['name' => 'backups.restore', 'label' => 'Restore Backups', 'group' => 'backups'],
            ['name' => 'backups.delete', 'label' => 'Delete Backups', 'group' => 'backups'],

            // Media
            ['name' => 'media.view', 'label' => 'View Media', 'group' => 'media'],
            ['name' => 'media.upload', 'label' => 'Upload Media', 'group' => 'media'],
            ['name' => 'media.edit', 'label' => 'Edit Media', 'group' => 'media'],
            ['name' => 'media.delete', 'label' => 'Delete Media', 'group' => 'media'],

            // Pages
            ['name' => 'pages.view', 'label' => 'View Pages', 'group' => 'pages'],
            ['name' => 'pages.create', 'label' => 'Create Pages', 'group' => 'pages'],
            ['name' => 'pages.edit', 'label' => 'Edit Pages', 'group' => 'pages'],
            ['name' => 'pages.delete', 'label' => 'Delete Pages', 'group' => 'pages'],
            ['name' => 'pages.publish', 'label' => 'Publish Pages', 'group' => 'pages'],

            // Posts
            ['name' => 'posts.view', 'label' => 'View Posts', 'group' => 'posts'],
            ['name' => 'posts.create', 'label' => 'Create Posts', 'group' => 'posts'],
            ['name' => 'posts.edit', 'label' => 'Edit Posts', 'group' => 'posts'],
            ['name' => 'posts.delete', 'label' => 'Delete Posts', 'group' => 'posts'],
            ['name' => 'posts.publish', 'label' => 'Publish Posts', 'group' => 'posts'],

            // Comments
            ['name' => 'comments.view', 'label' => 'View Comments', 'group' => 'comments'],
            ['name' => 'comments.create', 'label' => 'Create Comments', 'group' => 'comments'],
            ['name' => 'comments.edit', 'label' => 'Edit Comments', 'group' => 'comments'],
            ['name' => 'comments.delete', 'label' => 'Delete Comments', 'group' => 'comments'],
            ['name' => 'comments.moderate', 'label' => 'Moderate Comments', 'group' => 'comments'],

            // Tags
            ['name' => 'tags.view', 'label' => 'View Tags', 'group' => 'tags'],
            ['name' => 'tags.create', 'label' => 'Create Tags', 'group' => 'tags'],
            ['name' => 'tags.edit', 'label' => 'Edit Tags', 'group' => 'tags'],
            ['name' => 'tags.delete', 'label' => 'Delete Tags', 'group' => 'tags'],

            // Menus
            ['name' => 'menus.view', 'label' => 'View Menus', 'group' => 'menus'],
            ['name' => 'menus.create', 'label' => 'Create Menus', 'group' => 'menus'],
            ['name' => 'menus.edit', 'label' => 'Edit Menus', 'group' => 'menus'],
            ['name' => 'menus.delete', 'label' => 'Delete Menus', 'group' => 'menus'],

            // Files
            ['name' => 'files.view', 'label' => 'View Files', 'group' => 'files'],
            ['name' => 'files.upload', 'label' => 'Upload Files', 'group' => 'files'],
            ['name' => 'files.download', 'label' => 'Download Files', 'group' => 'files'],
            ['name' => 'files.delete', 'label' => 'Delete Files', 'group' => 'files'],

            // API Tokens
            ['name' => 'api_tokens.view', 'label' => 'View API Tokens', 'group' => 'api_tokens'],
            ['name' => 'api_tokens.create', 'label' => 'Create API Tokens', 'group' => 'api_tokens'],
            ['name' => 'api_tokens.revoke', 'label' => 'Revoke API Tokens', 'group' => 'api_tokens'],

            // Integrations
            ['name' => 'integrations.view', 'label' => 'View Integrations', 'group' => 'integrations'],
            ['name' => 'integrations.create', 'label' => 'Create Integrations', 'group' => 'integrations'],
            ['name' => 'integrations.edit', 'label' => 'Edit Integrations', 'group' => 'integrations'],
            ['name' => 'integrations.delete', 'label' => 'Delete Integrations', 'group' => 'integrations'],

            // Subscriptions
            ['name' => 'subscriptions.view', 'label' => 'View Subscriptions', 'group' => 'subscriptions'],
            ['name' => 'subscriptions.create', 'label' => 'Create Subscriptions', 'group' => 'subscriptions'],
            ['name' => 'subscriptions.edit', 'label' => 'Edit Subscriptions', 'group' => 'subscriptions'],
            ['name' => 'subscriptions.cancel', 'label' => 'Cancel Subscriptions', 'group' => 'subscriptions'],

            // Audit Trail
            ['name' => 'audit.view', 'label' => 'View Audit Trail', 'group' => 'audit'],
            ['name' => 'audit.export', 'label' => 'Export Audit Trail', 'group' => 'audit'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission['name']], $permission);
        }
    }
}