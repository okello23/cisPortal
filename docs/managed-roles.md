# Managed users and roles

Deploy all code and migrations together. Run these commands from the deployed project directory before reopening the application:

    php artisan migrate --force
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear

Do not use migrate:fresh or rerun the database seeder on an existing deployment.

The first roles migration creates the seven existing managed roles. The multiple-role migration creates role_user, backfills each existing user's role assignment, and copies nutrition designation and place of work into users. It preserves passwords, account IDs, nutrition profile IDs, and task assignment references. Apply migrations before using the new code; otherwise queries to role_user will fail.

Manage Users is the shared user-management page for Global and Micronutrient users. ICT admins can assign any combination of managed roles through grouped checkboxes. Task admins see only Micronutrient choices and can manage accounts without Global access. Accounts with both categories must be edited by an ICT admin. The former nutrition-user URL redirects to the shared page.

Users with Global roles get the Support Tickets workspace. Users with Micronutrient roles get the Micronutrient workspace. Users with both see both tabs and can switch without signing out. Neither category grants the permissions of the other: an ICT manager who is a nutrition member only sees their assigned nutrition tasks; Task Admin is required for team-wide nutrition administration. Users with no assigned roles cannot access either workspace or internal support-ticket routes.

Role assignments are stored in role_user. users.role and managed_role_id retain a primary role for compatibility; current authorization and recipient/assignee queries consider all assigned roles. The users table holds account and work details. nutrition_team_users remains as a compatibility link for historical task foreign keys, not a separate login or user-management module. Do not drop it.

Manage Roles allows ICT admins to add, rename, and delete unused custom roles. Each role belongs to Global or Micronutrient and uses an existing access level from that category. Built-in or assigned roles cannot change category/access level, and cannot be deleted. Role and user updates are audited, including assigned roles. Password reset is not available in the shared user edit form.