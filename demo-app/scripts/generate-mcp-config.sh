#!/usr/bin/env bash
#
# Generate mcp-inspector.config.json with a real Sanctum bearer token
# and a role-specific server name in place of the template's
# defaults, ready to import into the MCP Inspector or pass via
# --config. Prints the resulting JSON to stdout — it does not overwrite
# the committed template, so a real token never lands in version
# control.
#
# Usage:
#   scripts/generate-mcp-config.sh [email] [role] > mcp-inspector.local.json
#
# [email] and [role] are passed straight through to
# generate-sanctum-token.sh — see that script for defaults and accepted
# role values. The server's "name" field is set to
# bpm-engine-mcp-demo-<role>, matching the template's default of
# bpm-engine-mcp-demo-employee when role is omitted.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEMPLATE="$SCRIPT_DIR/../mcp-inspector.config.json"
TOKEN_PLACEHOLDER="REPLACE_WITH_TOKEN_FROM_scripts/generate-sanctum-token.sh"
DEFAULT_NAME="bpm-engine-mcp-demo-employee"

ROLE="${2:-employee}"
NAME="bpm-engine-mcp-demo-${ROLE}"

TOKEN="$("$SCRIPT_DIR/generate-sanctum-token.sh" "$@")"

# Escape sed metacharacters in case the token ever contains any.
ESCAPED_TOKEN="$(printf '%s' "$TOKEN" | sed -e 's/[&\]/\\&/g')"

sed \
  -e "s#${TOKEN_PLACEHOLDER}#${ESCAPED_TOKEN}#" \
  -e "s#${DEFAULT_NAME}#${NAME}#" \
  "$TEMPLATE"
