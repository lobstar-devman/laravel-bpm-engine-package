#!/usr/bin/env bash
#
# Generate a Sanctum token for a test user, for interactively exercising
# the MCP server (see README.md, "Testing the MCP server interactively").
#
# Usage:
#   scripts/generate-sanctum-token.sh [email] [role]
#
# email  defaults to test@example.com
# role   one of: employee (default), manager, finance, investigator,
#        finance_director — matches App\Enums\UserRole's backing values.
#
# Creates the user if it doesn't already exist (existing users are left
# untouched) and prints a plaintext token to stdout.

set -euo pipefail

TOKEN_EMAIL="${1:-test@example.com}"
TOKEN_ROLE="${2:-employee}"

export TOKEN_EMAIL TOKEN_ROLE

php artisan tinker --execute='
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Str;

$email = getenv("TOKEN_EMAIL");
$role = UserRole::from(getenv("TOKEN_ROLE"));

$user = User::firstOrCreate(
    ["email" => $email],
    ["name" => $email, "password" => bcrypt(Str::random(32)), "role" => $role]
);

echo $user->createToken("manual-test")->plainTextToken;
'
